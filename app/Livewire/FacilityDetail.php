<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FacilityDetail extends Component
{
    public Facility $facility;
    public string $selectedDate = '';
    public array $availableSlots = [];

    public bool $showBookingModal = false;
    public ?string $selectedCourtId = null;
    public array $selectedSlot = [];
    public array $selectedRentals = [];

    // Shown when the slot a guest picked before signing in is gone by the time they return.
    #[Locked]
    public string $notice = '';

    // Kept as plain data so toggling an extra or re-rendering doesn't query reviews and rentals again.
    #[Locked]
    public array $reviewSummary = [];
    #[Locked]
    public array $rentalOptions = [];

    public function mount($id)
    {
        $this->facility = Facility::with(['courts', 'amenities'])->findOrFail($id);
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadAvailableSlots();

        // Late in the day nothing is left, so open on tomorrow instead of a page of past hours.
        if (!collect($this->availableSlots)->contains(fn ($data) => collect($data['slots'])->contains('available', true))) {
            $this->selectedDate = Carbon::tomorrow()->format('Y-m-d');
            $this->loadAvailableSlots();
        }

        $this->refreshReviews();

        // Coming back from "Sign in to Book": reopen the slot the user had picked.
        $date = request()->query('date');
        if (is_string($date) && $date !== $this->selectedDate) {
            $this->selectedDate = $date;
            $this->loadAvailableSlots();
        }
        $court = request()->query('court');
        $start = request()->query('slot');
        // Only a slot that is still free: someone may have taken it while the guest signed in.
        $free = is_string($court) && is_string($start)
            && collect($this->availableSlots[$court]['slots'] ?? [])->contains(fn ($slot) => $slot['start'] === $start && $slot['available']);
        if ($free) {
            $this->selectSlot($court, $start);
        } elseif (is_string($start)) {
            $this->notice = 'That time is no longer available. Pick another one below.';
        }
        if ($court !== null || $start !== null) {
            // Drop the slot from the address so a reload doesn't reopen the modal.
            $this->js('history.replaceState(null, "", location.pathname)');
        }
    }

    public function updatedSelectedDate()
    {
        $this->loadAvailableSlots();
    }

    public function loadAvailableSlots()
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $this->selectedDate ?? '');
        if (!$parsed || $parsed->format('Y-m-d') !== $this->selectedDate) {
            $this->availableSlots = [];
            return;
        }

        // Clones carry the facility for its opening hours without changing the models Livewire stores.
        $courts = $this->facility->courts->map(fn (Court $court) => (clone $court)->setRelation('facility', $this->facility));
        $slotsByCourt = Court::slotsOn($courts, Carbon::instance($parsed));

        $this->availableSlots = [];
        foreach ($this->facility->courts as $court) {
            $this->availableSlots[$court->id] = [
                'court' => $court,
                // Hours that have already started can't be booked, so they aren't shown.
                'slots' => array_values(array_filter(
                    $slotsByCourt[$court->id],
                    fn (array $slot) => Carbon::parse($this->selectedDate . ' ' . $slot['start'])->isFuture()
                )),
            ];
        }
    }

    #[On('reviewSubmitted')]
    public function refreshReviews(): void
    {
        $stats = $this->facility->reviews()->selectRaw('avg(rating) as average, count(*) as total')->first();

        $this->reviewSummary = [
            'average' => round((float) $stats->average, 1),
            'count' => (int) $stats->total,
            'latest' => $this->facility->reviews()->with('user:id,name')->latest()->take(10)->get()
                ->map(fn ($review) => ['author' => $review->user?->name ?? 'Anonymous', 'rating' => $review->rating, 'comment' => $review->comment])
                ->all(),
        ];
    }

    public function selectSlot($courtId, $start)
    {
        $slot = is_string($start) ? Booking::parseSlot($this->selectedDate, $start, $start) : null;
        if (!$slot || !$this->facility->courts->contains('id', $courtId)) {
            return;
        }

        $end = $slot[0]->copy()->addHour();

        $this->selectedCourtId = $courtId;
        $this->selectedSlot = ['start' => $start, 'end' => $end->isSameDay($slot[0]) ? $end->format('H:i') : '24:00'];
        $this->selectedRentals = [];
        $this->rentalOptions = $this->facility->courts->firstWhere('id', $courtId)->availableRentals()
            ->map(fn ($rental) => $rental->only(['id', 'name', 'price']))
            ->all();
        $this->showBookingModal = true;
    }

    /** Sends a guest to sign in, then back here with the same slot open. */
    public function loginToBook()
    {
        session()->put('url.intended', route('facility', [
            'id' => $this->facility->id,
            'date' => $this->selectedDate,
            'court' => $this->selectedCourtId,
            'slot' => $this->selectedSlot['start'] ?? null,
        ]));

        return redirect()->route('login', ['book' => 1]);
    }

    public function toggleRental($rentalId)
    {
        if (in_array($rentalId, $this->selectedRentals)) {
            $this->selectedRentals = array_values(array_diff($this->selectedRentals, [$rentalId]));
        } else {
            $this->selectedRentals[] = $rentalId;
        }
    }

    public function confirmBooking()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $court = $this->facility->courts->firstWhere('id', $this->selectedCourtId);
        $slot = $this->parsedSelectedSlot();

        try {
            if (!$court || !$slot) {
                throw new BookingException('Invalid booking selection.');
            }

            Booking::book($court, $slot[0], $slot[1], auth()->user(), $this->selectedRentals);
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());
            $this->showBookingModal = false;
            $this->loadAvailableSlots();
            return;
        }

        $this->showBookingModal = false;

        return redirect()->route('dashboard')->with('success', 'Booking confirmed successfully!');
    }

    /** The selected slot as [start, end], or null if the client sent something malformed. */
    private function parsedSelectedSlot(): ?array
    {
        $start = $this->selectedSlot['start'] ?? null;
        $end = $this->selectedSlot['end'] ?? null;

        return is_string($start) && is_string($end)
            ? Booking::parseSlot($this->selectedDate, $start, $end)
            : null;
    }

    public function render()
    {
        $court = $this->facility->courts->firstWhere('id', $this->selectedCourtId);
        $slot = $this->parsedSelectedSlot();
        $rentals = collect($this->rentalOptions)->map(fn (array $rental) => (object) $rental);
        $breakdown = null;

        if ($this->showBookingModal && $court && $slot && $slot[1]->gt($slot[0])) {
            // Display only: Booking::book() prices the booking again from the database.
            $breakdown = Booking::priceBreakdown($court, $slot[0], $slot[1]);
            $breakdown['rentals'] = $rentals->whereIn('id', $this->selectedRentals)->sum('price');
            $breakdown['total'] += $breakdown['rentals'];
        }

        return view('livewire.facility-detail', [
            'selectedCourt' => $court,
            'rentals' => $rentals,
            'breakdown' => $breakdown,
            'avgRating' => $this->reviewSummary['average'],
            'reviewCount' => $this->reviewSummary['count'],
            'reviews' => collect($this->reviewSummary['latest'])->map(fn (array $review) => (object) $review),
        ])->title($this->facility->name . ' – Sport Manager');
    }
}
