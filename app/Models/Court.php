<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facility_id',
        'name',
        'type',
        'base_price_per_hour',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'base_price_per_hour' => 'integer',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Rentals that can be added to a booking of this court: the facility's own plus global ones, filtered by sport. */
    public function availableRentals(): Collection
    {
        return Rental::query()
            ->where(fn ($query) => $query->where('facility_id', $this->facility_id)->orWhereNull('facility_id'))
            ->get()
            ->filter(fn (Rental $rental) => $rental->isSuitableFor($this->type))
            ->values();
    }

    /**
     * Hourly slots for these courts on one day, keyed by court id, using one query for all their bookings.
     * Each slot has start and end ("H:i", with "24:00" for midnight), available (free and still ahead), peak and price in cents.
     * Opening hours come from $court->facility, so set that relation to avoid a query per court.
     */
    public static function slotsOn(iterable $courts, Carbon $day): array
    {
        $courts = collect($courts);
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        $bookingsByCourt = Booking::whereIn('court_id', $courts->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['court_id', 'start_time', 'end_time'])
            ->groupBy('court_id');

        $slots = [];
        foreach ($courts as $court) {
            $bookings = $bookingsByCourt->get($court->id, collect());
            $opening = max(0, min(23, (int) $court->facility->opening_hour));
            $closing = min(24, (int) $court->facility->closing_hour); // no slots if it isn't after opening
            $slots[$court->id] = [];

            for ($hour = $opening; $hour < $closing; $hour++) {
                $start = $dayStart->copy()->setTime($hour, 0);
                $end = $start->copy()->addHour();

                $slots[$court->id][] = [
                    'start' => $start->format('H:i'),
                    'end' => $end->isSameDay($start) ? $end->format('H:i') : '24:00',
                    'available' => $start->isFuture()
                        && !$bookings->contains(fn ($b) => $b->start_time < $end && $b->end_time > $start),
                    'peak' => $hour >= Booking::PEAK_START_HOUR && $hour < Booking::PEAK_END_HOUR,
                    'price' => Booking::calculatePrice($court, $start, $end),
                ];
            }
        }

        return $slots;
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->base_price_per_hour / 100, 0) . ' MKD'
        );
    }
}
