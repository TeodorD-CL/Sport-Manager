<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Deleting a user or court must not silently delete its booking history.
            $table->dropForeign(['user_id']);
            $table->dropForeign(['court_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('court_id')->references('id')->on('courts')->restrictOnDelete();

            // This unique index also covered cancelled bookings, so a cancelled slot could never be booked again.
            // Overlaps are now prevented by the court lock in Booking::book() and, on PostgreSQL, the exclusion constraint below.
            $table->dropUnique(['court_id', 'start_time', 'end_time']);

            $table->index('user_id');
            $table->index(['court_id', 'start_time']);
        });

        Schema::table('booking_rental', function (Blueprint $table) {
            $table->dropForeign(['rental_id']);
            $table->foreign('rental_id')->references('id')->on('rentals')->restrictOnDelete();
            // The price actually charged, so later price changes don't rewrite past bookings.
            $table->integer('unit_price')->nullable();
        });

        DB::table('booking_rental')->update([
            'unit_price' => DB::raw('(select price from rentals where rentals.id = booking_rental.rental_id)'),
        ]);

        Schema::table('rentals', function (Blueprint $table) {
            // A NULL facility now means "offered at every facility", so a deleted facility's rentals must go with it
            // rather than become global. Rentals used in bookings still block the delete through booking_rental.
            $table->dropForeign(['facility_id']);
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->index('facility_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('facility_id');
        });

        DB::table('bookings')->whereNull('qr_code')->pluck('id')->each(function (string $id) {
            DB::table('bookings')->where('id', $id)->update(['qr_code' => Str::uuid()->toString()]);
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->addOverlapConstraint();
        }
    }

    private function addOverlapConstraint(): void
    {
        $overlaps = DB::select("
            select a.id as first, b.id as second from bookings a
            join bookings b on a.court_id = b.court_id and a.id < b.id
                and a.status <> 'cancelled' and b.status <> 'cancelled'
                and a.start_time < b.end_time and b.start_time < a.end_time
            limit 20
        ");
        if ($overlaps) {
            // Stop with a clear message rather than PostgreSQL's. The whole migration rolls back, so nothing is half applied.
            $pairs = collect($overlaps)->map(fn ($row) => "{$row->first} / {$row->second}")->join(', ');
            throw new \RuntimeException("These active bookings overlap and must be cancelled or moved before this migration can run: {$pairs}");
        }

        try {
            // A savepoint, so a missing privilege doesn't abort the migration's transaction.
            DB::transaction(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist'));
        } catch (QueryException $e) {
            // Booking::book() still locks the court, so this only loses the database-level backstop.
            Log::warning('Skipped the bookings_no_overlap constraint: cannot create the btree_gist extension. ' . $e->getMessage());
            echo "WARNING: skipped the bookings_no_overlap constraint (cannot create the btree_gist extension).\n";

            return;
        }

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap EXCLUDE USING gist (court_id WITH =, tsrange(start_time, end_time) WITH &&) WHERE (status <> 'cancelled')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_no_overlap');
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['facility_id']);
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropIndex(['facility_id']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->foreign('facility_id')->references('id')->on('facilities')->nullOnDelete();
        });

        Schema::table('booking_rental', function (Blueprint $table) {
            $table->dropColumn('unit_price');
            $table->dropForeign(['rental_id']);
            $table->foreign('rental_id')->references('id')->on('rentals')->onDelete('cascade');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['court_id', 'start_time']);
            $table->dropIndex(['user_id']);
            $table->unique(['court_id', 'start_time', 'end_time']);
            $table->dropForeign(['court_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('court_id')->references('id')->on('courts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
