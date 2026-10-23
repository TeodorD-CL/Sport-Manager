<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\ReviewResource\Pages\CreateReview;
use App\Livewire\Chatbot;
use App\Livewire\FacilityDetail;
use App\Livewire\LeaveReview;
use App\Livewire\SearchFacilities;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FullReviewFindingsTest extends TestCase
{
    use RefreshDatabase;

    private function facility(string $name = 'Hall', string $city = 'Skopje'): Facility
    {
        return Facility::create(['name' => $name, 'description' => 'x', 'city' => $city, 'address' => 'x', 'opening_hour' => 8, 'closing_hour' => 22]);
    }

    private function court(Facility $facility, string $type = 'Tennis'): Court
    {
        return Court::create(['facility_id' => $facility->id, 'name' => "$type court", 'type' => $type, 'base_price_per_hour' => 10000]);
    }

    private function book(Court $court, User $user, Carbon $start): Booking
    {
        return Booking::create(['user_id' => $user->id, 'court_id' => $court->id, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'status' => 'confirmed', 'total_price' => 10000]);
    }

    private function manager(Facility $facility): User
    {
        Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $manager->managedFacilities()->attach($facility->id);

        return $manager;
    }

    // 1
    public function test_emails_are_case_insensitive(): void
    {
        $this->post('/register', ['name' => 'Bob', 'email' => ' Bob@Mail.com ', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'bob@mail.com']);
        auth()->logout();

        $this->post('/register', ['name' => 'Bob 2', 'email' => 'BOB@mail.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);

        $this->post('/login', ['email' => 'BOB@MAIL.COM', 'password' => 'password123'])->assertRedirect('/');
        $this->assertAuthenticated();

        // Every other save path (admin panel, seeder) goes through the model.
        $this->assertSame('alice@mail.com', User::factory()->create(['email' => 'Alice@Mail.COM'])->fresh()->email);
    }

    public function test_admin_login_ignores_email_case(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'secret-pass']);
        $admin->assignRole('super_admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'Admin@Test.com', 'password' => 'secret-pass'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }

    // 2, 9
    public function test_unrated_and_courtless_facilities_sort_last(): void
    {
        $reviewed = $this->facility('Zeta Reviewed');
        $this->court($reviewed);
        Review::create(['user_id' => User::factory()->create()->id, 'facility_id' => $reviewed->id, 'rating' => 2]);
        $this->facility('Alpha Empty');

        foreach (['rating', 'price_desc', 'price_asc'] as $sort) {
            Livewire::test(SearchFacilities::class)->set('sort', $sort)->assertSeeInOrder(['Zeta Reviewed', 'Alpha Empty']);
        }
    }

    // 3
    public function test_availability_tool_filters_by_facility_and_hour_and_lists_every_free_slot(): void
    {
        $prilep = $this->facility('Prilep Professional Arena', 'Prilep');
        $court = $this->court($prilep, 'Padel');
        $this->court($this->facility('Other Hall', 'Prilep'), 'Padel');
        $monday = Carbon::parse('next monday');
        $this->book($court, User::factory()->create(), $monday->copy()->setTime(19, 0));

        $run = fn (array $args) => json_decode((new \ReflectionMethod(Chatbot::class, 'executeFunction'))->invoke(new Chatbot(), 'checkAvailability', $args), true);

        $all = $run(['date' => $monday->format('Y-m-d'), 'facility_name' => 'professional']);
        $this->assertCount(1, $all['courts']);
        $this->assertCount(13, $all['courts'][0]['free_slots']); // 8:00-22:00 minus the booked 19:00

        $at20 = $run(['date' => $monday->format('Y-m-d'), 'city' => 'prilep', 'start_time' => '20:00']);
        $this->assertCount(2, $at20['courts']);
        $this->assertSame(['20:00-21:00 (120 MKD)'], $at20['courts'][0]['free_slots']);

        $at19 = $run(['date' => $monday->format('Y-m-d'), 'facility_name' => 'Professional', 'start_time' => '19:00']);
        $this->assertNull($at19);
    }

    // 4
    public function test_function_call_after_a_text_part_still_runs(): void
    {
        $court = $this->court($this->facility());
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [
                    ['text' => 'Let me prepare that.'],
                    ['functionCall' => ['id' => 'call_1', 'name' => 'prepareBooking', 'args' => ['court_id' => $court->id, 'date' => Carbon::parse('next monday')->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00']]],
                ]]]]])
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'Ready, please confirm.']]]]]]),
        ]);

        Livewire::test(Chatbot::class)
            ->set('message', 'Book it')->call('sendMessage')->call('handleBotResponse', 'Book it')
            ->assertSee('Confirmation Request')
            ->assertSee('Ready, please confirm.');

        Http::assertSent(fn ($request) => (last($request['contents'])['parts'][0]['functionResponse']['id'] ?? null) === 'call_1');
    }

    // 5
    public function test_review_double_submit_does_not_error(): void
    {
        $facility = $this->facility();
        $user = User::factory()->create();
        $this->book($this->court($facility), $user, now()->subDays(2));

        // Simulate the other request winning the race between the existence check and the insert.
        Review::creating(function (Review $review) {
            DB::table('reviews')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $review->user_id, 'facility_id' => $review->facility_id, 'rating' => 5]);
        });

        Livewire::actingAs($user)->test(LeaveReview::class, ['facilityId' => $facility->id])
            ->call('submit')
            ->assertSee('You have already submitted a review for this facility.')
            ->assertSet('alreadyReviewed', true);
    }

    public function test_admin_review_form_rejects_duplicates_and_missing_rating(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        $facility = $this->facility();
        $author = User::factory()->create();
        Review::create(['user_id' => $author->id, 'facility_id' => $facility->id, 'rating' => 4]);

        Livewire::test(CreateReview::class)
            ->fillForm(['user_id' => $author->id, 'facility_id' => $facility->id, 'rating' => 5])
            ->call('create')
            ->assertHasFormErrors(['facility_id' => 'unique']);

        Livewire::test(CreateReview::class)
            ->fillForm(['user_id' => User::factory()->create()->id, 'facility_id' => $facility->id, 'rating' => null])
            ->call('create')
            ->assertHasFormErrors(['rating' => 'required']);

        $this->assertDatabaseCount('reviews', 1);
    }

    // 6
    public function test_managers_only_see_their_own_customers(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);
        $manager = $this->manager($facility);
        $customer = User::factory()->create();
        $this->book($court, $customer, now()->addDays(2));
        $stranger = User::factory()->create();
        $this->actingAs($manager);

        $visible = BookingResource::scopeBookableUsers(User::query())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$manager->id, $customer->id], $visible);

        Livewire::test(CreateBooking::class)
            ->fillForm(['user_id' => $stranger->id, 'court_id' => $court->id, 'start_time' => Carbon::parse('next monday 10:00')->format('Y-m-d H:i'), 'end_time' => Carbon::parse('next monday 11:00')->format('Y-m-d H:i'), 'status' => 'confirmed'])
            ->call('create')
            ->assertHasFormErrors(['user_id']);

        // A new customer can still be booked by their exact email.
        $newcomer = User::factory()->create(['email' => 'ana@mail.com']);
        $slot = ['court_id' => $court->id, 'start_time' => Carbon::parse('next monday 12:00')->format('Y-m-d H:i'), 'end_time' => Carbon::parse('next monday 13:00')->format('Y-m-d H:i'), 'status' => 'confirmed'];

        Livewire::test(CreateBooking::class)
            ->fillForm($slot + ['customer_email' => 'nobody@mail.com'])
            ->call('create')
            ->assertHasFormErrors(['customer_email']);

        $otherManager = $this->manager($this->facility());
        Livewire::test(CreateBooking::class)
            ->fillForm($slot + ['customer_email' => $otherManager->email])
            ->call('create')
            ->assertHasFormErrors(['customer_email']);

        Livewire::test(CreateBooking::class)
            ->fillForm($slot + ['customer_email' => 'Ana@Mail.com'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bookings', ['user_id' => $newcomer->id, 'court_id' => $court->id]);
    }

    // 8
    public function test_quick_double_send_leaves_no_typing_indicator(): void
    {
        $component = Livewire::test(Chatbot::class)
            ->set('message', 'Hello')->call('sendMessage')
            ->set('message', 'Hello again')->call('sendMessage')
            ->call('handleBotResponse', 'Hello again');

        $this->assertNotContains('typing...', array_column($component->get('messages'), 'text'));
    }

    // 10
    public function test_toggling_an_extra_does_not_query_reviews_or_rentals(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);
        $rental = Rental::create(['facility_id' => $facility->id, 'name' => 'Racket', 'price' => 500, 'suitable_for' => ['Tennis']]);
        $component = Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', Carbon::parse('next monday')->format('Y-m-d'))
            ->call('selectSlot', $court->id, '10:00');

        DB::enableQueryLog();
        $component->call('toggleRental', $rental->id)->assertSee('105 MKD');
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));

        $this->assertStringNotContainsString('"reviews"', $queries);
        $this->assertStringNotContainsString('"rentals"', $queries);
    }
}
