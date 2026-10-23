<?php

use App\Models\Booking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:complete', function () {
    $count = Booking::where('status', 'confirmed')->where('end_time', '<', now())->update(['status' => 'completed']);
    $this->info("Marked {$count} past bookings as completed.");
})->purpose('Mark confirmed bookings that have ended as completed');

Schedule::command('bookings:complete')->hourly();
