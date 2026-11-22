<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reviews_have_played_bookings_and_reseeding_repairs_missing_history(): void
    {
        $this->seed();
        $this->assertDatabaseCount('reviews', 13);
        $this->assertDatabaseCount('bookings', 13);

        // Simulate old demo data, retaining another customer's booking in the original slot.
        $missing = Booking::firstOrFail();
        $missing->update(['user_id' => \App\Models\User::factory()->create()->id]);
        $cancelled = Booking::whereKeyNot($missing->id)->firstOrFail();
        $cancelled->update(['status' => 'cancelled']);
        $this->travel(2)->days();
        $this->seed();
        $this->assertDatabaseCount('bookings', 15);
        $this->seed();
        $this->assertDatabaseCount('bookings', 15);
        $this->assertDatabaseCount('reviews', 13);

        foreach (Review::all() as $review) {
            $this->assertTrue(Booking::where('user_id', $review->user_id)
                ->whereHas('court', fn ($query) => $query->where('facility_id', $review->facility_id))
                ->where('status', '!=', 'cancelled')
                ->where('end_time', '<', $review->created_at)
                ->exists());
        }
        foreach (Booking::where('status', '!=', 'cancelled')->get() as $booking) {
            $this->assertSame(1, Booking::overlapping($booking->court_id, $booking->start_time, $booking->end_time)->count());
            $this->assertSame(Booking::calculatePrice($booking->court, $booking->start_time, $booking->end_time), $booking->total_price);
        }
    }
}
