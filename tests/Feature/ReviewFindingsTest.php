<?php

namespace Tests\Feature;

use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Livewire\Chatbot;
use App\Livewire\SearchFacilities;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewFindingsTest extends TestCase
{
    use RefreshDatabase;

    private function facility(string $name = 'Hall'): Facility
    {
        return Facility::create(['name' => $name, 'description' => 'x', 'city' => 'Skopje', 'address' => 'x', 'opening_hour' => 8, 'closing_hour' => 22]);
    }

    private function court(Facility $facility, string $type = 'Tennis'): Court
    {
        return Court::create(['facility_id' => $facility->id, 'name' => "$type court", 'type' => $type, 'base_price_per_hour' => 10000]);
    }

    private function book(Court $court, Carbon $start, int $hours = 1): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'court_id' => $court->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours($hours),
            'status' => 'confirmed',
            'total_price' => 10000,
        ]);
    }

    public function test_deleting_a_facility_deletes_its_rentals_instead_of_making_them_global(): void
    {
        $facility = $this->facility();
        Rental::create(['facility_id' => $facility->id, 'name' => 'Racket', 'price' => 500]);
        $other = $this->court($this->facility('Other'));

        $facility->delete();

        $this->assertDatabaseCount('rentals', 0);
        $this->assertCount(0, $other->availableRentals());
    }

    public function test_editing_a_booking_to_a_new_slot_recalculates_the_price(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $court = $this->court($this->facility());
        $monday = Carbon::parse('next monday');
        $booking = $this->book($court, $monday->copy()->setTime(10, 0));
        $booking->update(['total_price' => Booking::calculatePrice($court, $booking->start_time, $booking->end_time)]);

        // 18:00 is peak: 100 MKD + 20%.
        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->fillForm(['start_time' => $monday->copy()->setTime(18, 0)->format('Y-m-d H:i'), 'end_time' => $monday->copy()->setTime(19, 0)->format('Y-m-d H:i')])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(12000, $booking->fresh()->total_price);

        // A price the admin typed on purpose is kept.
        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->fillForm(['start_time' => $monday->copy()->setTime(10, 0)->format('Y-m-d H:i'), 'end_time' => $monday->copy()->setTime(11, 0)->format('Y-m-d H:i'), 'total_price' => 50])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(5000, $booking->fresh()->total_price);
    }

    public function test_registration_rate_limit_is_per_visitor_behind_the_proxy(): void
    {
        $register = fn (string $ip, int $n) => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => $ip])
            ->post('/register', ['name' => 'x', 'email' => "u$n-" . str_replace('.', '', $ip) . '@test.com', 'password' => 'password123', 'password_confirmation' => 'password123']);

        for ($i = 0; $i < 6; $i++) {
            $register('1.1.1.1', $i);
            auth()->logout();
        }
        $register('1.1.1.1', 99)->assertStatus(429);
        auth()->logout();

        $register('2.2.2.2', 1)->assertRedirect('/');
    }

    public function test_date_filter_respects_the_chosen_sport(): void
    {
        $facility = $this->facility();
        $tennis = $this->court($facility, 'Tennis');
        $this->court($facility, 'Football');
        $tomorrow = Carbon::tomorrow();
        for ($hour = 8; $hour < 22; $hour++) {
            $this->book($tennis, $tomorrow->copy()->setTime($hour, 0));
        }

        Livewire::test(SearchFacilities::class)
            ->set('date', $tomorrow->format('Y-m-d'))
            ->set('type', 'Tennis')
            ->assertDontSee('Hall')
            ->set('type', 'Football')
            ->assertSee('Hall');
    }

    public function test_date_filter_ignores_hours_already_passed_today(): void
    {
        $this->court($this->facility());
        Carbon::setTestNow(Carbon::today()->setTime(21, 30));

        Livewire::test(SearchFacilities::class)
            ->set('date', Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Hall');

        Carbon::setTestNow(Carbon::today()->setTime(9, 30));
        Livewire::test(SearchFacilities::class)
            ->set('date', Carbon::today()->format('Y-m-d'))
            ->assertSee('Hall');

        Livewire::test(SearchFacilities::class)
            ->set('date', Carbon::yesterday()->format('Y-m-d'))
            ->assertDontSee('Hall');

        Carbon::setTestNow();
    }

    public function test_chatbot_prompt_lists_global_rentals(): void
    {
        $this->court($this->facility());
        Rental::create(['facility_id' => null, 'name' => 'Water Bottle', 'price' => 10000]);

        $prompt = (new \ReflectionMethod(Chatbot::class, 'getSystemPrompt'))->invoke(new Chatbot());

        $this->assertStringContainsString('Rentals available at every facility', $prompt);
        $this->assertStringContainsString('Water Bottle', $prompt);
    }

    public function test_chatbot_links_reject_backslash_hosts(): void
    {
        $this->assertStringNotContainsString('<a ', Chatbot::parseMarkdown('[x](/\\evil.test) [y](/a\\b)'));
        $this->assertStringContainsString('<a href="/dashboard"', Chatbot::parseMarkdown('[Bookings](/dashboard)'));
    }
}
