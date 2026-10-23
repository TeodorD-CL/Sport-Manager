<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class UserProfile extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $email_password = '';

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
        $this->email = Str::lower(trim($this->email));
        $emailChanged = $this->email !== $user->email;

        $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Changing the login email takes over the account, so it needs the password.
        if ($emailChanged && !$this->passwordMatches('email_password', $this->email_password)) {
            return;
        }

        $user->update([
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
        ]);

        $this->email_password = '';

        session()->flash('profile_success', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'min:8', 'confirmed'],
        ]);

        if (!$this->passwordMatches('current_password', $this->current_password)) {
            return;
        }

        Auth::user()->update(['password' => $this->new_password]);
        Auth::logoutOtherDevices($this->new_password);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        session()->flash('password_success', 'Password updated successfully.');
    }

    /** Checks the user's password, allowing 5 wrong guesses a minute. Adds an error to $field on failure. */
    private function passwordMatches(string $field, string $password): bool
    {
        $key = 'profile-password:' . Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError($field, 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.');
            return false;
        }

        if (!Hash::check($password, Auth::user()->password)) {
            RateLimiter::hit($key);
            $this->addError($field, 'The current password is incorrect.');
            return false;
        }

        RateLimiter::clear($key);

        return true;
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
