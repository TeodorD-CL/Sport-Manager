<?php

namespace Tests\Feature;

use App\Livewire\LeaveReview;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createFacility(): Facility
    {
        return Facility::create([
            'name' => 'Test Facility',
            'description' => 'Test facility description',
            'city' => 'Skopje',
            'address' => 'Test Street 1',
        ]);
    }

    private function createCourt(Facility $facility): Court
    {
        return Court::create([
            'facility_id' => $facility->id,
            'name' => 'Test Court',
            'type' => 'Tennis',
            'base_price_per_hour' => 10000,
        ]);
    }

    private function createBooking(Court $court, User $user, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHour(),
            'status' => $status,
            'total_price' => 10000,
            'qr_code' => Str::uuid()->toString(),
        ]);
    }

    public function test_authenticated_user_with_non_cancelled_booking_can_leave_review(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user);

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 4)
            ->set('comment', 'Great courts and helpful staff.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('reviewSubmitted');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'rating' => 4,
            'comment' => 'Great courts and helpful staff.',
        ]);
    }

    public function test_user_without_booking_cannot_leave_review(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 5)
            ->set('comment', 'Looks nice.')
            ->call('submit')
            ->assertSee('You need a booking at this facility before leaving a review.');

        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_user_with_only_cancelled_booking_cannot_leave_review(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user, 'cancelled');

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 5)
            ->set('comment', 'The cancelled booking should not count.')
            ->call('submit')
            ->assertSee('You need a booking at this facility before leaving a review.');

        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_user_cannot_submit_more_than_one_review_for_same_facility(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user);

        Review::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'rating' => 5,
            'comment' => 'Original review.',
        ]);

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->assertSet('alreadyReviewed', true)
            ->set('rating', 1)
            ->set('comment', 'Second review attempt.')
            ->call('submit')
            ->assertSee('You have already submitted a review for this facility.');

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'comment' => 'Second review attempt.',
        ]);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user);

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 0)
            ->call('submit')
            ->assertHasErrors(['rating' => 'min']);

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 6)
            ->call('submit')
            ->assertHasErrors(['rating' => 'max']);

        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_comment_cannot_be_longer_than_1000_characters(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user);

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('comment', str_repeat('a', 1001))
            ->call('submit')
            ->assertHasErrors(['comment' => 'max']);

        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_valid_review_is_saved_in_database(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $this->createBooking($court, $user, 'completed');

        Livewire::actingAs($user)
            ->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->set('rating', 5)
            ->set('comment', 'Excellent facility.')
            ->call('submit');

        $review = Review::where('user_id', $user->id)
            ->where('facility_id', $facility->id)
            ->first();

        $this->assertNotNull($review);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Excellent facility.', $review->comment);
    }
}
