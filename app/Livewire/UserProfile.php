<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class UserProfile extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update([
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
        ]);

        session()->flash('profile_success', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($this->current_password, Auth::user()->password)) {
            $this->addError('current_password', 'The current password is incorrect.');
            return;
        }

        Auth::user()->update(['password' => $this->new_password]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        session()->flash('password_success', 'Password updated successfully.');
    }

    public function render()
    {
        $user = Auth::user();

        $totalBookings = Booking::where('user_id', $user->id)->count();

        $upcomingBookings = Booking::where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_time', '>', now())
            ->count();

        $reviewsCount = Review::where('user_id', $user->id)->count();

        $favouriteSport = Booking::where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled'])
            ->join('courts', 'bookings.court_id', '=', 'courts.id')
            ->selectRaw('courts.type, COUNT(*) as total')
            ->groupBy('courts.type')
            ->orderByDesc('total')
            ->value('type');

        return view('livewire.user-profile', [
            'totalBookings'    => $totalBookings,
            'upcomingBookings' => $upcomingBookings,
            'reviewsCount'     => $reviewsCount,
            'favouriteSport'   => $favouriteSport,
        ]);
    }
}
