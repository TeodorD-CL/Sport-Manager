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
        'season_start_month',
        'season_end_month',
    ];

    protected function casts(): array
    {
        return [
            'base_price_per_hour' => 'integer',
            'season_start_month' => 'integer',
            'season_end_month' => 'integer',
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

    public function closures(): HasMany
    {
        return $this->hasMany(Closure::class);
    }

    /** True when the court is open in $day's month. No season set means open all year; a season like November–March wraps over New Year. */
    public function isInSeason(Carbon $day): bool
    {
        [$from, $to] = [$this->season_start_month, $this->season_end_month];
        if (!$from || !$to) {
            return true;
        }

        return $from <= $to
            ? $day->month >= $from && $day->month <= $to
            : $day->month >= $from || $day->month <= $to;
    }

    /** "May–September", or null for a court open all year. */
    public function seasonLabel(): ?string
    {
        if (!$this->season_start_month || !$this->season_end_month) {
            return null;
        }

        return Carbon::create(2000, $this->season_start_month)->format('F') . '–' . Carbon::create(2000, $this->season_end_month)->format('F');
    }

    /** Why this court can't be booked at all on $day (out of season), or null. */
    public function seasonClosedReason(): string
    {
        return 'Closed for the season (open ' . $this->seasonLabel() . ')';
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
     * Hourly slots for these courts on one day, keyed by court id, using one query for bookings and one for closures.
     * Each slot has start and end ("H:i", with "24:00" for midnight), available (free and bookable now), peak, price in cents,
     * and closed: why the venue can't take it (season, closure, too far ahead), or null.
     * Every availability shown to users (facility page, search, assistant) comes from here, so it matches Booking::bookingError().
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

        $closures = Closure::whereIn('facility_id', $courts->pluck('facility_id')->unique())
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get();
        $horizonEnd = Booking::horizonEnd();

        $slots = [];
        foreach ($courts as $court) {
            $bookings = $bookingsByCourt->get($court->id, collect());
            $courtClosures = $closures->filter(fn (Closure $closure) => $closure->covers($court));
            $offSeason = !$court->isInSeason($dayStart);
            $opening = max(0, min(23, (int) $court->facility->opening_hour));
            $closing = min(24, (int) $court->facility->closing_hour); // no slots if it isn't after opening
            $slots[$court->id] = [];

            for ($hour = $opening; $hour < $closing; $hour++) {
                $start = $dayStart->copy()->setTime($hour, 0);
                $end = $start->copy()->addHour();
                $closure = $courtClosures->first(fn (Closure $c) => $c->starts_at < $end && $c->ends_at > $start);
                $closed = match (true) {
                    $offSeason => $court->seasonClosedReason(),
                    $closure !== null => 'Closed' . ($closure->reason ? ": {$closure->reason}" : ''),
                    $start->gt($horizonEnd) => 'Not open for booking yet',
                    default => null,
                };

                $slots[$court->id][] = [
                    'start' => $start->format('H:i'),
                    'end' => $end->isSameDay($start) ? $end->format('H:i') : '24:00',
                    'available' => $closed === null && $start->isFuture()
                        && !$bookings->contains(fn ($b) => $b->start_time < $end && $b->end_time > $start),
                    'closed' => $closed,
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
