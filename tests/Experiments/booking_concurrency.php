<?php

// Run only after migrating the disposable sport_manager_concurrency_test PostgreSQL database.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

check(app()->environment('testing') && DB::getDriverName() === 'pgsql'
    && DB::connection()->getDatabaseName() === 'sport_manager_concurrency_test', 'Use the isolated experiment database.');
check(function_exists('pcntl_fork'), 'The experiment requires pcntl.');
$constraint = DB::selectOne("SELECT conname, convalidated FROM pg_constraint WHERE conrelid = 'bookings'::regclass AND contype = 'x'");
check($constraint !== null && $constraint->convalidated, 'The overlap exclusion constraint must be active.');
$report = [
    'executed_at_utc' => gmdate(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'postgresql_version' => DB::selectOne('SHOW server_version')->server_version,
    'isolation' => DB::selectOne('SHOW transaction_isolation')->transaction_isolation,
    'constraint' => $constraint->conname,
    'method' => '20 independent PHP processes and PostgreSQL connections call Booking::book after a common barrier. A coordinator holds the court row until all 20 workers are waiting on database locks, then releases it without writing a booking.',
    'rounds' => [],
];
$facility = Facility::create(['name' => 'Concurrency experiment', 'description' => 'Disposable test fixture', 'city' => 'Skopje', 'address' => 'Test', 'opening_hour' => 8, 'closing_hour' => 23]);
$court = Court::create(['facility_id' => $facility->id, 'name' => 'Concurrency court', 'type' => 'Tennis', 'base_price_per_hour' => 10000]);
$user = User::factory()->create();
$day = Carbon::tomorrow()->startOfDay();

for ($round = 1; $round <= 3; $round++) {
    $start = $day->copy()->addHours(9 + $round);
    $end = $start->copy()->addHour();
    $directory = sys_get_temp_dir() . '/booking-race-' . getmypid() . '-' . $round;
    check(mkdir($directory, 0700), 'Cannot create synchronization directory.');
    $label = 'thesis-race-' . getmypid() . '-' . $round . '-';
    $children = [];
    DB::disconnect(); // Never share a PDO connection across forked processes.
    for ($worker = 0; $worker < 20; $worker++) {
        $pid = pcntl_fork();
        check($pid >= 0, 'Cannot fork worker.');
        if ($pid === 0) {
            $result = ['worker' => $worker, 'outcome' => 'unexpected_error'];
            try {
                DB::purge();
                DB::select("SELECT set_config('application_name', ?, false)", [$label . $worker]);
                DB::statement("SET statement_timeout = '15s'");
                $result['backend_pid'] = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
                touch($directory . '/' . $worker . '.ready');
                $until = microtime(true) + 15;
                while (!is_file($directory . '/go') && microtime(true) < $until) {
                    usleep(1000);
                }
                check(is_file($directory . '/go'), 'Barrier timed out.');
                $began = hrtime(true);
                try {
                    $booking = Booking::book($court, $start, $end, $user);
                    $result['outcome'] = 'created';
                    $result['booking_id'] = $booking->id;
                } catch (BookingException $e) {
                    check($e->getMessage() === 'This slot is no longer available.', $e->getMessage());
                    $result['outcome'] = 'slot_unavailable';
                }
                $result['elapsed_ms'] = round((hrtime(true) - $began) / 1e6, 3);
            } catch (Throwable $e) {
                $result['error'] = get_class($e) . ': ' . $e->getMessage();
            }
            file_put_contents($directory . '/' . $worker . '.json', json_encode($result, JSON_THROW_ON_ERROR));
            DB::disconnect();
            exit($result['outcome'] === 'unexpected_error' ? 1 : 0);
        }
        $children[] = $pid;
    }
    $until = microtime(true) + 15;
    while (count(glob($directory . '/*.ready')) < 20 && microtime(true) < $until) {
        usleep(1000);
    }
    check(count(glob($directory . '/*.ready')) === 20, 'Not all workers reached the barrier.');
    DB::purge();
    DB::beginTransaction();
    Court::whereKey($court->id)->lockForUpdate()->firstOrFail();
    $began = hrtime(true);
    touch($directory . '/go');
    $until = microtime(true) + 5;
    do {
        $waiting = (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE application_name LIKE ? AND wait_event_type = 'Lock'", [$label . '%'])->n;
        if ($waiting === 20) {
            break;
        }
        // PostgreSQL caches statistics within a transaction; refresh before the next observation.
        DB::select('SELECT pg_stat_clear_snapshot()');
        usleep(10000);
    } while (microtime(true) < $until);
    DB::rollBack();
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'A worker failed. Inspect ' . $directory);
    }
    $results = array_map(fn ($path) => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR), glob($directory . '/*.json'));
    $counts = array_count_values(array_column($results, 'outcome'));
    $persisted = Booking::overlapping($court->id, $start, $end)->count();
    check(count($results) === 20 && count(array_unique(array_column($results, 'backend_pid'))) === 20, 'Expected 20 independent connections.');
    check($waiting === 20, 'Workers did not all overlap in time.');
    check(($counts['created'] ?? 0) === 1 && ($counts['slot_unavailable'] ?? 0) === 19 && $persisted === 1, 'Double-booking protection failed.');
    $report['rounds'][] = ['round' => $round, 'slot_start' => $start->toDateTimeString(), 'workers_waiting_before_release' => $waiting, 'created' => 1, 'rejected_as_unavailable' => 19, 'unexpected_errors' => 0, 'persisted_active_bookings' => $persisted, 'elapsed_ms' => round((hrtime(true) - $began) / 1e6, 3), 'workers' => $results];
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

// Bypass application validation to independently exercise the database constraint.
$first = Booking::where('court_id', $court->id)->orderBy('start_time')->firstOrFail();
$row = ['user_id' => $user->id, 'court_id' => $court->id, 'start_time' => $first->start_time, 'end_time' => $first->end_time, 'status' => 'confirmed', 'total_price' => 10000];
$sqlstate = null;
try {
    Booking::create($row);
} catch (QueryException $e) {
    $sqlstate = $e->getCode();
}
check($sqlstate === '23P01', 'The database did not reject a direct overlapping insert.');
$adjacent = Booking::create(array_replace($row, ['start_time' => $day->copy()->addHours(13), 'end_time' => $day->copy()->addHours(14)]));
$first->update(['status' => 'cancelled']);
$replacement = Booking::book($court, $first->start_time, $first->end_time, $user);
check($adjacent->exists && $replacement->exists && Booking::overlapping($court->id, $first->start_time, $first->end_time)->count() === 1, 'Boundary or cancellation control failed.');
$report['controls'] = ['direct_overlap_sqlstate' => $sqlstate, 'adjacent_interval_accepted' => true, 'cancelled_slot_rebooked' => true];
$report['passed'] = true;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
