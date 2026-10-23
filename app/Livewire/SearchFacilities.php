<?php

namespace App\Livewire;

use App\Enums\CourtType;
use App\Models\Amenity;
use App\Models\Facility;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class SearchFacilities extends Component
{
    use WithPagination;

    #[Url(as: 'city')]
    public string $city = '';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'date')]
    public string $date = '';

    #[Url(as: 'amenities')]
    public array $amenityFilters = [];

    #[Url(as: 'sort')]
    public string $sort = '';

    public function updatingCity() { $this->resetPage(); }
    public function updatingType() { $this->resetPage(); }
    public function updatingDate() { $this->resetPage(); }
    public function updatingAmenityFilters() { $this->resetPage(); }
    public function updatingSort() { $this->resetPage(); }

    public function search()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->city = '';
        $this->type = '';
        $this->date = '';
        $this->amenityFilters = [];
        $this->sort = '';
        $this->resetPage();
    }

    #[Computed]
    public function cities()
    {
        return Facility::distinct()->pluck('city')->filter()->sort()->values();
    }

    public function render()
    {
        $query = Facility::with(['courts', 'amenities'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($this->city) {
            $query->where('city', $this->city);
        }

        if (CourtType::tryFrom($this->type)) {
            $query->whereHas('courts', function ($q) {
                $q->where('type', $this->type);
            });
        }

        if ($this->date) {
            $parsed = \DateTime::createFromFormat('Y-m-d', $this->date);
            if ($parsed && $parsed->format('Y-m-d') === $this->date) {
                $dayStart = Carbon::instance($parsed)->startOfDay();
                // Today, only hours that haven't started yet can be booked.
                $firstHour = $dayStart->isToday() ? now()->hour + 1 : 0;

                // A court (of the chosen sport) has room while it has fewer bookings than bookable hours left that day.
                // ponytail: counts bookings, not booked hours, so multi-hour admin bookings undercount; sum durations if that matters.
                $query->whereHas('courts', function ($q) use ($dayStart, $firstHour) {
                    if (CourtType::tryFrom($this->type)) {
                        $q->where('type', $this->type);
                    }
                    $q->whereRaw(
                        '(select count(*) from bookings where bookings.court_id = courts.id and bookings.status <> ? and bookings.start_time >= ? and bookings.start_time < ?)'
                        . ' < facilities.closing_hour - (case when facilities.opening_hour > ? then facilities.opening_hour else ? end)',
                        ['cancelled', $dayStart->copy()->setTime($firstHour, 0), $dayStart->copy()->addDay(), $firstHour, $firstHour]
                    );
                });

                if ($dayStart->isPast() && !$dayStart->isToday()) {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if (!empty($this->amenityFilters)) {
            // Values come from the URL; ignore anything that is not a UUID so PostgreSQL does not throw.
            foreach (array_filter($this->amenityFilters, [\Illuminate\Support\Str::class, 'isUuid']) as $amenityId) {
                $query->whereHas('amenities', function ($q) use ($amenityId) {
                    $q->where('amenities.id', $amenityId);
                });
            }
        }

        // NULLS LAST: facilities without reviews or courts go to the end in both PostgreSQL and SQLite.
        match ($this->sort) {
            'rating'     => $query->orderByRaw('(select avg(rating) from reviews where reviews.facility_id = facilities.id) desc nulls last'),
            'reviews'    => $query->orderByDesc('reviews_count'),
            'price_asc'  => $query->orderByRaw('(select min(base_price_per_hour) from courts where courts.facility_id = facilities.id) asc nulls last'),
            'price_desc' => $query->orderByRaw('(select max(base_price_per_hour) from courts where courts.facility_id = facilities.id) desc nulls last'),
            default => null,
        };
        // Ties are common, so finish with a unique order or pagination can repeat or skip facilities.
        $query->orderBy('name')->orderBy('facilities.id');

        $facilities = $query->paginate(12);

        return view('livewire.search-facilities', [
            'facilities' => $facilities,
            'cities'     => $this->cities,
            'amenities'  => Amenity::orderBy('name')->get(),
        ]);
    }
}
