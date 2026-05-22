<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
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
    public int $calculatedPrice = 0;
    public $rentals = [];

    public function mount($id)
    {
        $this->facility = Facility::with(['courts', 'amenities', 'reviews.user'])->findOrFail($id);
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadAvailableSlots();
        $this->rentals = collect();
    }

    public function updatedSelectedDate()
    {
        $this->loadAvailableSlots();
    }

    public function loadAvailableSlots()
    {
        $slots = [];
        $parsed = \DateTime::createFromFormat('Y-m-d', $this->selectedDate ?? '');
        if (!$parsed || $parsed->format('Y-m-d') !== $this->selectedDate) {
            $this->availableSlots = [];
            return;
        }
        $date = Carbon::instance($parsed);
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $courtIds = $this->facility->courts->pluck('id');

        $bookingsByCourt = Booking::whereIn('court_id', $courtIds)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['court_id', 'start_time', 'end_time'])
            ->groupBy('court_id');

        foreach ($this->facility->courts as $court) {
            $courtSlots = [];
            $courtBookings = $bookingsByCourt->get($court->id, collect());
            $openingHour = max(0, min(23, (int) $this->facility->opening_hour));
            $closingHour = max($openingHour + 1, min(24, (int) $this->facility->closing_hour));

            for ($hour = $openingHour; $hour < $closingHour; $hour++) {
                $startTime = $date->copy()->setHour($hour)->setMinute(0)->setSecond(0);
                $endTime = $startTime->copy()->addHour();

                $isBooked = $courtBookings->some(
                    fn ($b) => $b->start_time < $endTime && $b->end_time > $startTime
                );

                $courtSlots[] = [
                    'start' => $startTime->format('H:i'),
                    'end' => $endTime->format('H:i'),
                    'available' => !$isBooked && $startTime->isFuture(),
                    'price' => $court->base_price_per_hour,
                ];
            }

            $slots[$court->id] = [
                'court' => $court,
                'slots' => $courtSlots,
            ];
        }

        $this->availableSlots = $slots;
    }

    #[On('reviewSubmitted')]
    public function refreshReviews(): void
    {
        $this->facility->load('reviews.user');
    }

    public function selectSlot($courtId, $slot)
    {
        $this->selectedCourtId = $courtId;
        $this->selectedSlot = $slot;
        $this->selectedRentals = [];
        $this->showBookingModal = true;

        $court = $this->facility->courts->firstWhere('id', $courtId);
        if ($court) {
            $this->rentals = Rental::query()
                ->where(function ($query) {
                    $query->where('facility_id', $this->facility->id)
                        ->orWhereNull('facility_id');
                })
                ->get()
                ->filter(fn ($rental) => $rental->isSuitableFor($court->type));
        } else {
            $this->rentals = collect();
        }

        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        if (!$this->selectedCourtId || empty($this->selectedSlot)) {
            $this->calculatedPrice = 0;
            return;
        }

        $court = $this->facility->courts->firstWhere('id', $this->selectedCourtId);
        if (!$court) {
            $this->calculatedPrice = 0;
            return;
        }

        $startTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedSlot['start']);
        $endTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedSlot['end']);

        $rentals = [];
        foreach ($this->selectedRentals as $rentalId) {
            $rentals[] = ['id' => $rentalId, 'quantity' => 1];
        }

        $this->calculatedPrice = Booking::calculatePrice($court, $startTime, $endTime, $rentals);
    }

    public function toggleRental($rentalId)
    {
        if (in_array($rentalId, $this->selectedRentals)) {
            $this->selectedRentals = array_values(array_diff($this->selectedRentals, [$rentalId]));
        } else {
            $this->selectedRentals[] = $rentalId;
        }
        $this->calculateTotal();
    }

    public function confirmBooking()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $court = $this->facility->courts->firstWhere('id', $this->selectedCourtId);
        if (!$court) {
            session()->flash('error', 'Invalid court selection.');
            $this->showBookingModal = false;
            return;
        }

        if (empty($this->selectedSlot) || !isset($this->selectedSlot['start']) || !isset($this->selectedSlot['end'])) {
            session()->flash('error', 'Invalid time slot.');
            $this->showBookingModal = false;
            return;
        }

        $openingHour = max(0, min(23, (int) $this->facility->opening_hour));
        $closingHour = max($openingHour + 1, min(24, (int) $this->facility->closing_hour));
        $startHour = (int) explode(':', $this->selectedSlot['start'])[0];
        $endHour = (int) explode(':', $this->selectedSlot['end'])[0];

        if ($startHour < $openingHour || $endHour > $closingHour || $startHour >= $closingHour) {
            session()->flash('error', "Invalid booking hours. Must be between {$openingHour}:00 and {$closingHour}:00.");
            $this->showBookingModal = false;
            return;
        }

        $startTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedSlot['start']);
        $endTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedSlot['end']);

        if ($startTime->isPast()) {
            session()->flash('error', 'Cannot book a time slot in the past.');
            $this->showBookingModal = false;
            $this->loadAvailableSlots();
            return;
        }

        $rentals = [];
        foreach ($this->selectedRentals as $rentalId) {
            $rentals[] = ['id' => $rentalId, 'quantity' => 1];
        }

        try {
            $booking = DB::transaction(function () use ($court, $startTime, $endTime, $rentals) {
                $isBooked = Booking::where('court_id', $court->id)
                    ->where('status', '!=', 'cancelled')
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->lockForUpdate()
                    ->exists();

                if ($isBooked) {
                    throw new \RuntimeException('slot_taken');
                }

                $totalPrice = Booking::calculatePrice($court, $startTime, $endTime, $rentals);

                $newBooking = Booking::create([
                    'user_id' => auth()->id(),
                    'court_id' => $court->id,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => 'confirmed',
                    'total_price' => $totalPrice,
                    'qr_code' => Str::uuid()->toString(),
                ]);

                foreach ($rentals as $rentalData) {
                    $newBooking->rentals()->attach($rentalData['id'], ['quantity' => 1]);
                }

                return $newBooking;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'slot_taken') {
                session()->flash('error', 'This slot is no longer available.');
                $this->showBookingModal = false;
                $this->loadAvailableSlots();
                return;
            }
            throw $e;
        }

        $this->showBookingModal = false;

        return redirect()->route('dashboard')->with('success', 'Booking confirmed successfully!');
    }

    public function render()
    {
        return view('livewire.facility-detail');
    }
}
