<?php

namespace App\Livewire;

use App\Enums\CourtType;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Court;
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

    #[Url(as: 'time')]
    public string $time = '';

    #[Url(as: 'amenities')]
    public array $amenityFilters = [];

    #[Url(as: 'sort')]
    public string $sort = '';

    public function updatingCity() { $this->resetPage(); }
    public function updatingType() { $this->resetPage(); }
    public function updatingDate() { $this->resetPage(); }
    public function updatingTime() { $this->resetPage(); }
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
        $this->time = '';
        $this->amenityFilters = [];
        $this->sort = '';
        $this->resetPage();
    }

    #[Computed]
    public function cities()
    {
        return Facility::distinct()->pluck('city')->filter()->sort()->values();
    }

    /** The searched day: the date filter, or today when only a time is chosen. Null for no date, a malformed one, or one outside the bookable range. */
    private function searchDay(): ?Carbon
    {
        $date = $this->date ?: ($this->searchTime() ? Carbon::today()->format('Y-m-d') : '');
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }
        $day = Carbon::instance($parsed);

        return $day->lt(Carbon::today()) || $day->gt(Booking::horizonEnd()) ? null : $day;
    }

    /** The time filter as "H:00", or null. */
    private function searchTime(): ?string
    {
        return preg_match('/^([01]\d|2[0-3]):00$/', $this->time) ? $this->time : null;
    }

    /**
     * Free times per facility on the searched day, from the same slots the facility page shows (Court::slotsOn),
     * so search and booking can't disagree. With a time: 'exact' lists courts free at that hour with their prices,
     * 'near' the nearest other free start times within two hours. Facilities with nothing to offer are left out.
     */
    private function availability(Carbon $day, ?string $time): array
    {
        $courts = Court::with('facility')
            ->when(CourtType::tryFrom($this->type), fn ($q) => $q->where('type', $this->type))
            ->when($this->city, fn ($q) => $q->whereHas('facility', fn ($f) => $f->where('city', $this->city)))
            ->get();

        $byFacility = [];
        foreach (Court::slotsOn($courts, $day) as $courtId => $slots) {
            $court = $courts->firstWhere('id', $courtId);
            foreach ($slots as $slot) {
                if ($slot['available']) {
                    $byFacility[$court->facility_id][] = $slot;
                }
            }
        }

        $result = [];
        foreach ($byFacility as $facilityId => $free) {
            $free = collect($free);
            if (!$time) {
                $result[$facilityId] = ['exact' => null, 'near' => []];
                continue;
            }
            $exact = $free->where('start', $time);
            $near = $free->pluck('start')->unique()
                ->reject(fn ($start) => $start === $time || abs((int) $start - (int) $time) > 2)
                ->sortBy(fn ($start) => [abs((int) $start - (int) $time), $start])
                ->take(3)->sort()->values()->all();
            if ($exact->isNotEmpty() || $near) {
                $result[$facilityId] = ['exact' => $exact->isNotEmpty() ? $exact->min('price') : null, 'near' => $near];
            }
        }

        return $result;
    }

    public function render()
    {
        $sport = CourtType::tryFrom($this->type)?->value;

        // Courts in the order they were added, so each card shows the same (first) court's photo every time.
        $query = Facility::with(['courts' => fn ($courts) => $courts->orderBy('created_at')->orderBy('id'), 'amenities'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($this->city) {
            $query->where('city', $this->city);
        }

        if ($sport) {
            $query->whereHas('courts', fn ($q) => $q->where('type', $sport));
        }

        $availability = null;
        if ($this->date || $this->searchTime()) {
            $day = $this->searchDay();
            $availability = $day ? $this->availability($day, $this->searchTime()) : [];
            $query->whereIn('facilities.id', array_keys($availability));
            if ($this->searchTime()) {
                // Venues free at the exact time first; the rest only offer nearby times.
                $exactIds = array_keys(array_filter($availability, fn ($a) => $a['exact'] !== null));
                if ($exactIds) {
                    $query->orderByRaw('case when facilities.id in (' . implode(',', array_fill(0, count($exactIds), '?')) . ') then 0 else 1 end', $exactIds);
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

        // Price sorts look only at courts of the chosen sport. NULLS LAST: facilities without reviews or courts go to the end.
        $courtPrice = fn (string $aggregate) => "(select {$aggregate}(base_price_per_hour) from courts where courts.facility_id = facilities.id"
            . ($sport ? ' and courts.type = ?' : '') . ')';
        match ($this->sort) {
            'rating'     => $query->orderByRaw('(select avg(rating) from reviews where reviews.facility_id = facilities.id) desc nulls last'),
            'reviews'    => $query->orderByDesc('reviews_count'),
            'price_asc'  => $query->orderByRaw($courtPrice('min') . ' asc nulls last', $sport ? [$sport] : []),
            'price_desc' => $query->orderByRaw($courtPrice('max') . ' desc nulls last', $sport ? [$sport] : []),
            default => null,
        };
        // Ties are common, so finish with a unique order or pagination can repeat or skip facilities.
        $query->orderBy('name')->orderBy('facilities.id');

        $facilities = $query->paginate(12);

        return view('livewire.search-facilities', [
            'facilities'   => $facilities,
            'cities'       => $this->cities,
            'amenities'    => Amenity::orderBy('name')->get(),
            'selectedSport' => $sport,
            'availability' => $availability,
            'searchTime'   => $this->searchTime(),
            'searchDay'    => $this->searchDay(),
        ]);
    }
}
