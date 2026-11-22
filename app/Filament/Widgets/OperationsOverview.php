<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ScopesToManagedFacilities;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Facility;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The day at a glance for the facilities this user runs. Deliberately no revenue: nothing is paid online. */
class OperationsOverview extends StatsOverviewWidget
{
    use ScopesToManagedFacilities;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $bookings = fn () => BookingResource::getEloquentQuery()->where('status', '!=', 'cancelled');
        $today = $bookings()->whereBetween('start_time', [today(), today()->endOfDay()]);
        $closures = static::scopeToManagedFacilities(Closure::query())
            ->where('ends_at', '>', now())
            ->where('starts_at', '<', now()->addDays(7))
            ->get();

        // Bookings that can't go ahead as booked: inside a closure, or on a court now out of season.
        // ponytail: checks upcoming bookings in PHP; fine for a few thousand, move to SQL if it grows.
        $upcoming = $bookings()->whereIn('status', ['confirmed', 'checked_in'])->where('start_time', '>', now())->with('court')->get();
        $futureClosures = static::scopeToManagedFacilities(Closure::query())->where('ends_at', '>', now())->get();
        $affected = $upcoming->filter(fn (Booking $booking) => !$booking->court->isInSeason($booking->start_time)
            || $futureClosures->contains(fn (Closure $c) => $c->covers($booking->court) && $c->starts_at < $booking->end_time && $c->ends_at > $booking->start_time));

        $stats = [
            Stat::make('Bookings today', (clone $today)->count())
                ->description((clone $today)->where('start_time', '>', now())->count() . ' still to come'),
            Stat::make('Next 7 days', $bookings()->whereBetween('start_time', [now(), now()->addDays(7)])->count()),
            Stat::make('Closures this week', $closures->count())
                ->description($closures->isEmpty() ? 'Nothing closed' : 'Facilities → Edit → Closures'),
            Stat::make('Bookings in closed times', $affected->count())
                ->description($affected->isEmpty() ? 'None' : 'Cancel or move them: ' . $affected->take(3)->map(fn (Booking $b) => $b->start_time->format('j M H:i') . ' ' . $b->court->name)->join(', '))
                ->color($affected->isEmpty() ? 'gray' : 'danger'),
        ];

        if (static::getCurrentAdminUser()?->isSuperAdmin()) {
            $stats[] = Stat::make('Facilities', Facility::count());
        }

        return $stats;
    }
}
