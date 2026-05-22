<?php

namespace App\Livewire;

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Layout('components.layouts.app')]
class UserDashboard extends Component
{
    public string $tab = 'upcoming';

    public function cancelBooking($bookingId)
    {
        $booking = Booking::where('id', $bookingId)
            ->where('user_id', auth()->id())
            ->where('status', 'confirmed')
            ->first();

        if (!$booking) {
            session()->flash('error', 'Booking not found or already cancelled.');
            return;
        }

        if ($booking->start_time->gt(now()->addHours(24))) {
            $booking->update(['status' => 'cancelled']);
            session()->flash('success', 'Booking cancelled successfully.');
        } else {
            session()->flash('error', 'Cancellations must be made more than 24 hours before the booking start time.');
        }
    }

    public function render()
    {
        $baseQuery = Booking::with('court.facility')
            ->where('user_id', auth()->id());

        $upcomingCount = (clone $baseQuery)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_time', '>', now())
            ->count();

        $pastCount = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('start_time', '<=', now())->orWhere('status', 'cancelled');
            })
            ->count();

        if ($this->tab === 'upcoming') {
            $bookings = (clone $baseQuery)
                ->whereNotIn('status', ['cancelled'])
                ->where('start_time', '>', now())
                ->orderBy('start_time', 'asc')
                ->get();
        } else {
            $bookings = (clone $baseQuery)
                ->where(function ($q) {
                    $q->where('start_time', '<=', now())->orWhere('status', 'cancelled');
                })
                ->orderBy('start_time', 'desc')
                ->get();
        }

        return view('livewire.user-dashboard', [
            'bookings'      => $bookings,
            'upcomingCount' => $upcomingCount,
            'pastCount'     => $pastCount,
        ]);
    }
}
