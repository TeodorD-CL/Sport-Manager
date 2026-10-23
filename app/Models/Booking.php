<?php

namespace App\Models;

use App\Exceptions\BookingException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasUuids;

    // ponytail: global surcharges, move to per-facility settings when facilities need their own pricing.
    public const PEAK_START_HOUR = 18;
    public const PEAK_END_HOUR = 22;
    public const PEAK_SURCHARGE = 0.2;
    public const WEEKEND_SURCHARGE = 0.1;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'court_id',
        'start_time',
        'end_time',
        'status',
        'total_price',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_price' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->qr_code ??= Str::uuid()->toString();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function rentals(): BelongsToMany
    {
        return $this->belongsToMany(Rental::class)->withPivot('quantity', 'unit_price');
    }

    public function scopeOverlapping(Builder $query, string $courtId, Carbon $start, Carbon $end): void
    {
        $query->where('court_id', $courtId)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }

    /**
     * Parses a Y-m-d date and two H:i times sent by the browser or the chatbot.
     * Returns [start, end], or null when the input is malformed. "24:00" means midnight at the end of the day.
     */
    public static function parseSlot(string $date, string $startTime, string $endTime): ?array
    {
        $day = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            return null;
        }

        $times = [];
        foreach ([$startTime, $endTime] as $time) {
            if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $m) || (int) $m[1] > 24 || (int) $m[2] > 59) {
                return null;
            }
            $times[] = Carbon::instance($day)->setTime((int) $m[1], (int) $m[2]);
        }

        return $times;
    }

    /** Why this court can't take a booking with this shape, or null if it can: whole hours inside opening hours. */
    public static function slotError(Court $court, Carbon $start, Carbon $end): ?string
    {
        $opening = (int) $court->facility->opening_hour;
        $closing = (int) $court->facility->closing_hour;

        $onTheHour = $start->minute === 0 && $start->second === 0 && $end->minute === 0 && $end->second === 0;
        $insideHours = $start->gte($start->copy()->setTime($opening, 0))
            && $end->lte($start->copy()->setTime($closing, 0));

        if (!$onTheHour || $end->lte($start) || !$insideHours) {
            return sprintf('Bookings are whole hours between %02d:00 and %02d:00.', $opening, $closing);
        }

        return null;
    }

    /** Why a user can't book this slot, or null if they can: one future hour inside opening hours. Overlaps are checked separately. */
    public static function bookingError(Court $court, Carbon $start, Carbon $end): ?string
    {
        if ($error = static::slotError($court, $start, $end)) {
            return $error;
        }
        if (!$end->equalTo($start->copy()->addHour())) {
            return 'Bookings are one-hour slots.';
        }

        return $start->isPast() ? 'Cannot book a time slot in the past.' : null;
    }

    /** True when PostgreSQL's no-overlap exclusion constraint rejected a write: another booking got the slot first. */
    public static function isOverlapViolation(QueryException $e): bool
    {
        return $e->getCode() === '23P01';
    }

    /**
     * Books one future hour for a user. Every public booking path goes through here.
     *
     * @throws BookingException when the slot or the extras are not allowed, or the slot is taken.
     */
    public static function book(Court $court, Carbon $start, Carbon $end, User $user, array $rentalIds = []): Booking
    {
        if ($error = static::bookingError($court, $start, $end)) {
            throw new BookingException($error);
        }

        $offered = $court->availableRentals()->keyBy('id');
        $rentalIds = array_unique($rentalIds, SORT_REGULAR);
        foreach ($rentalIds as $rentalId) {
            if (!is_string($rentalId) || !$offered->has($rentalId)) {
                throw new BookingException('One of the selected extras is not available for this court.');
            }
        }
        $rentals = $offered->only($rentalIds);

        try {
            return DB::transaction(function () use ($court, $start, $end, $user, $rentals) {
                // Locking the court row makes concurrent bookings of the same court wait for each other.
                Court::whereKey($court->id)->lockForUpdate()->first();

                if (static::overlapping($court->id, $start, $end)->exists()) {
                    throw new BookingException('This slot is no longer available.');
                }

                $booking = static::create([
                    'user_id' => $user->id,
                    'court_id' => $court->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => 'confirmed',
                    'total_price' => static::calculatePrice($court, $start, $end) + $rentals->sum('price'),
                ]);

                foreach ($rentals as $rental) {
                    $booking->rentals()->attach($rental->id, ['quantity' => 1, 'unit_price' => $rental->price]);
                }

                return $booking;
            });
        } catch (QueryException $e) {
            if (static::isOverlapViolation($e)) {
                throw new BookingException('This slot is no longer available.');
            }
            throw $e;
        }
    }

    public static function calculatePrice(Court $court, $startTime, $endTime, array $rentals = []): int
    {
        return static::priceBreakdown($court, $startTime, $endTime, $rentals)['total'];
    }

    /**
     * Price lines in cents. Surcharges apply per hour, so a 17:00-19:00 booking pays peak for its second hour.
     * Each line is rounded to whole MKD so the lines shown to the user always add up to the total.
     */
    public static function priceBreakdown(Court $court, $startTime, $endTime, array $rentals = []): array
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('end_time must be after start_time');
        }

        // Every started hour is charged in full.
        $hours = max(1, (int) ceil($start->diffInMinutes($end) / 60));

        $base = 0;
        $peak = 0.0;
        $weekend = 0.0;
        for ($i = 0; $i < $hours; $i++) {
            $hourStart = $start->copy()->addHours($i);
            $hourPrice = $court->base_price_per_hour;
            $base += $hourPrice;

            if ($hourStart->hour >= self::PEAK_START_HOUR && $hourStart->hour < self::PEAK_END_HOUR) {
                $peak += $hourPrice * self::PEAK_SURCHARGE;
                $hourPrice *= 1 + self::PEAK_SURCHARGE;
            }
            if ($hourStart->isWeekend()) {
                $weekend += $hourPrice * self::WEEKEND_SURCHARGE;
            }
        }

        $rentalCost = 0;
        if (!empty($rentals)) {
            $rentalIds = array_filter(
                array_column($rentals, 'id'),
                fn($id) => Str::isUuid($id)
            );
            $quantities = array_column($rentals, 'quantity', 'id');

            if (!empty($rentalIds)) {
                $rentalModels = Rental::whereIn('id', $rentalIds)->get()->keyBy('id');

                foreach ($rentalIds as $rentalId) {
                    $rentalModel = $rentalModels->get($rentalId);
                    if ($rentalModel) {
                        $rentalCost += $rentalModel->price * ($quantities[$rentalId] ?? 1);
                    }
                }
            }
        }

        $lines = [
            'base' => $base,
            'peak' => (int) round($peak / 100) * 100,
            'weekend' => (int) round($weekend / 100) * 100,
            'rentals' => $rentalCost,
        ];

        return $lines + ['total' => array_sum($lines)];
    }

    protected function formattedTotalPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_price / 100, 0) . ' MKD'
        );
    }
}
