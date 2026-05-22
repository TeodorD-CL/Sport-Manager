<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function createFacility(): Facility
    {
        return Facility::create([
            'name' => 'Test Facility',
            'description' => 'Test',
            'city' => 'Skopje',
            'address' => 'Test St 1',
        ]);
    }

    private function createCourt(Facility $facility, int $price = 10000): Court
    {
        return Court::create([
            'facility_id' => $facility->id,
            'name' => 'Test Court',
            'type' => 'Tennis',
            'base_price_per_hour' => $price,
        ]);
    }

    private function createBooking(Court $court, User $user, string $start, string $end, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'start_time' => Carbon::parse($start),
            'end_time' => Carbon::parse($end),
            'status' => $status,
            'total_price' => 10000,
            'qr_code' => \Illuminate\Support\Str::uuid()->toString(),
        ]);
    }

    // --- Cancellation window ---

    public function test_booking_can_be_cancelled_more_than_24h_before_start(): void
    {
        $user = User::factory()->create();
        $court = $this->createCourt($this->createFacility());
        $booking = $this->createBooking(
            $court, $user,
            now()->addHours(25)->toDateTimeString(),
            now()->addHours(26)->toDateTimeString(),
        );

        Livewire::actingAs($user)
            ->test(\App\Livewire\UserDashboard::class)
            ->call('cancelBooking', $booking->id);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_booking_cannot_be_cancelled_within_24h_of_start(): void
    {
        $user = User::factory()->create();
        $court = $this->createCourt($this->createFacility());
        $booking = $this->createBooking(
            $court, $user,
            now()->addHours(23)->toDateTimeString(),
            now()->addHours(24)->toDateTimeString(),
        );

        Livewire::actingAs($user)
            ->test(\App\Livewire\UserDashboard::class)
            ->call('cancelBooking', $booking->id);

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $court = $this->createCourt($this->createFacility());
        $booking = $this->createBooking(
            $court, $owner,
            now()->addHours(48)->toDateTimeString(),
            now()->addHours(49)->toDateTimeString(),
        );

        Livewire::actingAs($other)
            ->test(\App\Livewire\UserDashboard::class)
            ->call('cancelBooking', $booking->id);

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    // --- Price calculation with rentals ---

    public function test_rental_cost_is_added_to_price(): void
    {
        $court = $this->createCourt($this->createFacility(), 10000);
        $rental = Rental::create([
            'name' => 'Racket',
            'price' => 500,
            'suitable_for' => ['Tennis'],
        ]);

        $start = Carbon::parse('2026-06-01 10:00'); // weekday, off-peak
        $end = Carbon::parse('2026-06-01 11:00');

        $price = Booking::calculatePrice($court, $start, $end, [
            ['id' => $rental->id, 'quantity' => 1],
        ]);

        $this->assertSame(10500, $price); // 10000 base + 500 rental
    }

    public function test_rental_quantity_multiplies_cost(): void
    {
        $court = $this->createCourt($this->createFacility(), 10000);
        $rental = Rental::create([
            'name' => 'Ball',
            'price' => 300,
            'suitable_for' => ['Tennis'],
        ]);

        $start = Carbon::parse('2026-06-01 10:00');
        $end = Carbon::parse('2026-06-01 11:00');

        $price = Booking::calculatePrice($court, $start, $end, [
            ['id' => $rental->id, 'quantity' => 3],
        ]);

        $this->assertSame(10900, $price); // 10000 + 300*3
    }

    public function test_unknown_rental_id_is_silently_skipped(): void
    {
        $court = $this->createCourt($this->createFacility(), 10000);

        $start = Carbon::parse('2026-06-01 10:00');
        $end = Carbon::parse('2026-06-01 11:00');

        $price = Booking::calculatePrice($court, $start, $end, [
            ['id' => 'non-existent-uuid', 'quantity' => 1],
        ]);

        $this->assertSame(10000, $price); // rental not found → no change
    }

    public function test_end_time_before_start_time_throws(): void
    {
        $court = $this->createCourt($this->createFacility(), 10000);

        $this->expectException(\InvalidArgumentException::class);

        Booking::calculatePrice(
            $court,
            Carbon::parse('2026-06-01 11:00'),
            Carbon::parse('2026-06-01 10:00'),
        );
    }

    // --- Booking confirmation flow ---

    public function test_authenticated_user_can_confirm_a_booking(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        Livewire::actingAs($user)
            ->test(\App\Livewire\FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', $tomorrow)
            ->set('selectedCourtId', $court->id)
            ->set('selectedSlot', ['start' => '10:00', 'end' => '11:00'])
            ->call('confirmBooking')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_booking_confirmation_with_rentals_attaches_them(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $rental = Rental::create([
            'name' => 'Racket',
            'price' => 500,
            'suitable_for' => ['Tennis'],
        ]);
        $weekday = Carbon::parse('2026-06-01')->format('Y-m-d'); // Monday, off-peak

        Livewire::actingAs($user)
            ->test(\App\Livewire\FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', $weekday)
            ->set('selectedCourtId', $court->id)
            ->set('selectedSlot', ['start' => '10:00', 'end' => '11:00'])
            ->set('selectedRentals', [$rental->id])
            ->call('confirmBooking')
            ->assertRedirect(route('dashboard'));

        $booking = \App\Models\Booking::where('court_id', $court->id)->first();
        $this->assertNotNull($booking);
        $this->assertTrue($booking->rentals->contains($rental->id));
        $this->assertSame(10500, $booking->total_price); // 10000 base + 500 rental
    }

    public function test_booking_an_already_taken_slot_fails(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $tomorrow = Carbon::tomorrow();

        $this->createBooking(
            $court, $user1,
            $tomorrow->copy()->setHour(10)->setMinute(0)->toDateTimeString(),
            $tomorrow->copy()->setHour(11)->setMinute(0)->toDateTimeString(),
        );

        Livewire::actingAs($user2)
            ->test(\App\Livewire\FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', $tomorrow->format('Y-m-d'))
            ->set('selectedCourtId', $court->id)
            ->set('selectedSlot', ['start' => '10:00', 'end' => '11:00'])
            ->call('confirmBooking');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_unauthenticated_user_is_redirected_to_login_on_booking(): void
    {
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        Livewire::test(\App\Livewire\FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', Carbon::tomorrow()->format('Y-m-d'))
            ->set('selectedCourtId', $court->id)
            ->set('selectedSlot', ['start' => '10:00', 'end' => '11:00'])
            ->call('confirmBooking')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_past_slot_cannot_be_booked(): void
    {
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        Livewire::actingAs($user)
            ->test(\App\Livewire\FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', Carbon::yesterday()->format('Y-m-d'))
            ->set('selectedCourtId', $court->id)
            ->set('selectedSlot', ['start' => '10:00', 'end' => '11:00'])
            ->call('confirmBooking');

        $this->assertDatabaseCount('bookings', 0);
    }

    // --- Cancellation error feedback ---

    public function test_cancellation_within_24h_shows_error_message(): void
    {
        $user = User::factory()->create();
        $court = $this->createCourt($this->createFacility());
        $booking = $this->createBooking(
            $court, $user,
            now()->addHours(23)->toDateTimeString(),
            now()->addHours(24)->toDateTimeString(),
        );

        Livewire::actingAs($user)
            ->test(\App\Livewire\UserDashboard::class)
            ->call('cancelBooking', $booking->id)
            ->assertSee('Cancellations must be made more than 24 hours');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
