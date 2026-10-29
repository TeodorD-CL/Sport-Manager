<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->forgetAbandonedBooking($request);
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->lowercaseEmail($request);

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister(Request $request)
    {
        $this->forgetAbandonedBooking($request);
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->lowercaseEmail($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole($userRole);

        Auth::login($user);

        return redirect()->intended('/');
    }

    // "Sign in to Book" stores the slot to return to and adds ?book=1. Signing in from anywhere else
    // later shouldn't drop the user into that old booking, so the stored slot is forgotten then.
    private function forgetAbandonedBooking(Request $request): void
    {
        if (!$request->has('book') && str_contains((string) session('url.intended'), 'slot=')) {
            session()->forget('url.intended');
        }
    }

    // Emails are stored lowercase (see User::email()), so lookups and the unique check must use the same form.
    private function lowercaseEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
