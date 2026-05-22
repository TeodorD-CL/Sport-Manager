<?php

use App\Http\Controllers\AuthController;
use App\Livewire\FacilityDetail;
use App\Livewire\SearchFacilities;
use App\Livewire\UserDashboard;
use App\Livewire\UserProfile;
use Illuminate\Support\Facades\Route;

Route::get('/', SearchFacilities::class);
Route::get('/facility/{id}', FacilityDetail::class);

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', UserDashboard::class)->name('dashboard');
    Route::get('/profile', UserProfile::class)->name('profile');
});
