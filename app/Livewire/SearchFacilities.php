<?php

namespace App\Livewire;

use App\Models\Amenity;
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

    #[Url(as: 'amenities')]
    public array $amenityFilters = [];

    #[Url(as: 'sort')]
    public string $sort = '';

    public function updatingCity() { $this->resetPage(); }
    public function updatingType() { $this->resetPage(); }
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

        if ($this->type) {
            $query->whereHas('courts', function ($q) {
                $q->where('type', $this->type);
            });
        }

        if ($this->date) {
            $parsed = \DateTime::createFromFormat('Y-m-d', $this->date);
            if ($parsed && $parsed->format('Y-m-d') === $this->date) {
                $date = Carbon::instance($parsed);
                $query->whereHas('courts', function ($q) use ($date) {
                    $q->whereDoesntHave('bookings', function ($bq) use ($date) {
                        $bq->where('status', '!=', 'cancelled')
                            ->whereDate('start_time', $date);
                    })->orWhereHas('bookings', function ($bq) use ($date) {
                        $bq->where('status', '!=', 'cancelled')
                            ->whereDate('start_time', $date);
                    }, '<', 14);
                });
            }
        }

        if (!empty($this->amenityFilters)) {
            foreach ($this->amenityFilters as $amenityId) {
                $query->whereHas('amenities', function ($q) use ($amenityId) {
                    $q->where('amenities.id', $amenityId);
                });
            }
        }

        match ($this->sort) {
            'rating'     => $query->orderByDesc('reviews_avg_rating'),
            'reviews'    => $query->orderByDesc('reviews_count'),
            'price_asc'  => $query->orderBy(
                Court::selectRaw('MIN(base_price_per_hour)')->whereColumn('courts.facility_id', 'facilities.id'),
                'asc'
            ),
            'price_desc' => $query->orderBy(
                Court::selectRaw('MAX(base_price_per_hour)')->whereColumn('courts.facility_id', 'facilities.id'),
                'desc'
            ),
            default => $query->orderBy('name'),
        };

        $facilities = $query->paginate(12);

        return view('livewire.search-facilities', [
            'facilities' => $facilities,
            'cities'     => $this->cities,
            'amenities'  => Amenity::orderBy('name')->get(),
        ]);
    }
}
