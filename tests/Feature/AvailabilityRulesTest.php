<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\FacilityResource\Pages\EditFacility;
use App\Filament\Resources\FacilityResource\RelationManagers\ClosuresRelationManager;
use App\Filament\Widgets\TodaysBookings;
use App\Livewire\Chatbot;
use App\Livewire\FacilityDetail;
use App\Livewire\SearchFacilities;
use App\Livewire\UserDashboard;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Court;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Seasons, closures, the booking horizon and everything that must agree with them: search, booking, assistant, admin. */
class AvailabilityRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => null, 'app.booking_horizon_days' => 60]);
        // A Monday morning, so "tomorrow" is a weekday and the evening hours are peak.
        $this->travelTo(Carbon::parse('2030-06-03 09:30'));
    }

    private function facility(string $name = 'Hall', string $city = 'Skopje'): Facility
    {
        return Facility::create(['name' => $name, 'description' => 'x', 'city' => $city, 'address' => 'Main St 1', 'opening_hour' => 8, 'closing_hour' => 22]);
    }

    private function court(Facility $facility, string $type = 'Tennis', int $price = 10000, array $extra = []): Court
    {
        return Court::create(['facility_id' => $facility->id, 'name' => "$type court", 'type' => $type, 'base_price_per_hour' => $price, ...$extra]);
    }

    private function book(Court $court, string $start, string $end, ?User $user = null): Booking
    {
        return Booking::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'court_id' => $court->id,
            'start_time' => Carbon::parse($start),
            'end_time' => Carbon::parse($end),
            'status' => 'confirmed',
            'total_price' => 10000,
        ]);
    }

    private function bookingError(Court $court, string $start, string $end): ?string
    {
        return Booking::bookingError($court->load('facility'), Carbon::parse($start), Carbon::parse($end));
    }

    private function slots(Court $court, string $day): \Illuminate\Support\Collection
    {
        return collect(Court::slotsOn([$court->load('facility')], Carbon::parse($day))[$court->id])->keyBy('start');
    }

    // --- Seasons ---

    public function test_court_cannot_be_booked_outside_its_season(): void
    {
        $this->travelTo(Carbon::parse('2030-09-28 09:30'));
        $pool = $this->court($this->facility(), 'Swimming', 10000, ['season_start_month' => 5, 'season_end_month' => 9]);

        $this->assertNull($this->bookingError($pool, '2030-09-30 10:00', '2030-09-30 11:00'));
        $this->assertStringContainsString('closed for the season (open May–September)', $this->bookingError($pool, '2030-10-01 10:00', '2030-10-01 11:00'));
        $this->assertFalse($this->slots($pool, '2030-10-01')['10:00']['available']);
        $this->assertTrue($this->slots($pool, '2030-09-30')['10:00']['available']);

        $this->expectException(BookingException::class);
        Booking::book($pool, Carbon::parse('2030-10-01 10:00'), Carbon::parse('2030-10-01 11:00'), User::factory()->create());
    }

    public function test_season_can_wrap_over_new_year_and_no_season_means_all_year(): void
    {
        $facility = $this->facility();
        $winter = $this->court($facility, 'Tennis', 10000, ['season_start_month' => 11, 'season_end_month' => 3]);
        $allYear = $this->court($facility);

        foreach (['2030-11-01' => true, '2031-01-15' => true, '2031-03-31' => true, '2031-04-01' => false, '2030-07-10' => false] as $day => $open) {
            $this->assertSame($open, $winter->isInSeason(Carbon::parse($day)), $day);
            $this->assertTrue($allYear->isInSeason(Carbon::parse($day)));
        }
        $this->assertSame('November–March', $winter->seasonLabel());
        $this->assertNull($allYear->seasonLabel());
    }

    public function test_facility_page_says_a_court_is_closed_for_the_season(): void
    {
        $this->travelTo(Carbon::parse('2030-10-05 09:30'));
        $facility = $this->facility();
        $this->court($facility, 'Swimming', 10000, ['season_start_month' => 5, 'season_end_month' => 9]);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSee('Closed for the season. Open May–September.')
            ->assertDontSeeHtml('wire:click="selectSlot');
    }

    // --- Closures ---

    public function test_facility_closure_blocks_every_court_and_court_closure_only_its_court(): void
    {
        $facility = $this->facility();
        $a = $this->court($facility);
        $b = $this->court($facility, 'Football');
        Closure::create(['facility_id' => $facility->id, 'starts_at' => '2030-06-04 10:00', 'ends_at' => '2030-06-04 12:00', 'reason' => 'Holiday']);
        Closure::create(['facility_id' => $facility->id, 'court_id' => $a->id, 'starts_at' => '2030-06-04 14:00', 'ends_at' => '2030-06-04 15:00']);

        foreach ([$a, $b] as $court) {
            $slots = $this->slots($court, '2030-06-04');
            $this->assertSame('Closed: Holiday', $slots['10:00']['closed']);
            $this->assertFalse($slots['11:00']['available']);
            $this->assertTrue($slots['12:00']['available']);
        }
        $this->assertFalse($this->slots($a, '2030-06-04')['14:00']['available']);
        $this->assertTrue($this->slots($b, '2030-06-04')['14:00']['available']);

        $this->assertStringContainsString('closed then (Holiday)', $this->bookingError($b, '2030-06-04 11:00', '2030-06-04 12:00'));
        $this->assertNotNull($this->bookingError($a, '2030-06-04 13:00', '2030-06-04 15:00')); // two hours, the second one closed
        $this->assertNull($this->bookingError($b, '2030-06-04 14:00', '2030-06-04 15:00'));
    }

    public function test_full_day_and_overlapping_closures(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);
        $day = Closure::create(['facility_id' => $facility->id, 'starts_at' => '2030-06-04 00:00', 'ends_at' => '2030-06-05 00:00']);
        Closure::create(['facility_id' => $facility->id, 'court_id' => $court->id, 'starts_at' => '2030-06-04 18:00', 'ends_at' => '2030-06-05 10:00']);

        $this->assertTrue($this->slots($court, '2030-06-04')->every(fn ($slot) => !$slot['available']));
        $this->assertFalse($this->slots($court, '2030-06-05')['09:00']['available']);
        $this->assertTrue($this->slots($court, '2030-06-05')['10:00']['available']);

        $day->delete();
        $this->assertTrue($this->slots($court, '2030-06-04')['17:00']['available']);
        $this->assertFalse($this->slots($court, '2030-06-04')['18:00']['available']);
    }

    public function test_admin_bookings_respect_closures(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $facility = $this->facility();
        $court = $this->court($facility);
        Closure::create(['facility_id' => $facility->id, 'starts_at' => '2030-06-04 08:00', 'ends_at' => '2030-06-04 22:00', 'reason' => 'Tournament']);

        $this->actingAs($admin);
        Livewire::test(CreateBooking::class)
            ->fillForm(['customer_email' => User::factory()->create()->email, 'court_id' => $court->id, 'start_time' => '2030-06-04 10:00', 'end_time' => '2030-06-04 12:00', 'status' => 'confirmed'])
            ->call('create')
            ->assertHasFormErrors(['start_time']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_closing_over_a_booking_needs_confirmation_and_keeps_the_booking(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $facility = $this->facility();
        $court = $this->court($facility);
        $booking = $this->book($court, '2030-06-04 10:00', '2030-06-04 11:00');
        $this->actingAs($admin);
        $manager = fn () => Livewire::test(ClosuresRelationManager::class, ['ownerRecord' => $facility, 'pageClass' => EditFacility::class]);
        $data = ['starts_at' => '2030-06-04 08:00', 'ends_at' => '2030-06-04 12:00', 'reason' => 'Maintenance'];

        $manager()->callTableAction('create', data: $data)->assertNotified();
        $this->assertDatabaseCount('closures', 0);

        $manager()->callTableAction('create', data: [...$data, 'keep_bookings' => true]);
        $this->assertDatabaseCount('closures', 1);
        $this->assertSame('confirmed', $booking->fresh()->status);

        // A closure with no bookings in it needs no confirmation.
        $manager()->callTableAction('create', data: ['court_id' => $court->id, 'starts_at' => '2030-06-05 08:00', 'ends_at' => '2030-06-05 12:00']);
        $this->assertDatabaseCount('closures', 2);
    }

    public function test_manager_cannot_open_another_facilitys_closures(): void
    {
        Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $own = $this->facility('Own');
        $other = $this->facility('Other');
        $manager->managedFacilities()->attach($own->id);

        $this->actingAs($manager)->get("/admin/facilities/{$own->id}/edit")->assertOk()->assertSee('Closures');
        $this->actingAs($manager)->get("/admin/facilities/{$other->id}/edit")->assertNotFound();
    }

    // --- Booking horizon ---

    public function test_bookings_beyond_the_horizon_are_refused_and_shown_as_not_open(): void
    {
        $court = $this->court($this->facility());

        $this->assertNull($this->bookingError($court, '2030-08-02 10:00', '2030-08-02 11:00')); // day 60
        $this->assertSame('Bookings open 60 days ahead.', $this->bookingError($court, '2030-08-03 10:00', '2030-08-03 11:00'));
        $this->assertSame('Not open for booking yet', $this->slots($court, '2030-08-03')['10:00']['closed']);
    }

    public function test_facility_page_explains_a_date_it_cannot_show(): void
    {
        $facility = $this->facility();
        $this->court($facility);

        Livewire::withQueryParams(['date' => '2031-01-01'])
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('selectedDate', '2030-06-03')
            ->assertSee('That date can\'t be booked online. Showing Mon, 3 Jun instead.');
    }

    // --- Search ---

    public function test_date_search_counts_booked_hours_not_bookings(): void
    {
        $court = $this->court($this->facility());
        // One booking covering the whole day; counting bookings (1 < 14 hours) used to call this court available.
        $this->book($court, '2030-06-04 08:00', '2030-06-04 22:00');

        Livewire::test(SearchFacilities::class)
            ->set('date', '2030-06-04')
            ->assertDontSee('Hall')
            ->set('date', '2030-06-05')
            ->assertSee('Hall');
    }

    public function test_date_search_leaves_out_closed_and_out_of_season_venues(): void
    {
        $closed = $this->facility('Closed Hall');
        $this->court($closed);
        Closure::create(['facility_id' => $closed->id, 'starts_at' => '2030-06-04 00:00', 'ends_at' => '2030-06-05 00:00']);
        $this->court($this->facility('Winter Rink'), 'Tennis', 10000, ['season_start_month' => 11, 'season_end_month' => 3]);
        $this->court($this->facility('Open Hall'));

        Livewire::test(SearchFacilities::class)
            ->set('date', '2030-06-04')
            ->assertSee('Open Hall')
            ->assertDontSee('Closed Hall')
            ->assertDontSee('Winter Rink')
            ->set('date', '2031-01-01')
            ->assertDontSee('Open Hall')
            ->assertSee('Bookings are open from today up to 60 days ahead');
    }

    public function test_card_price_and_photo_follow_the_selected_sport(): void
    {
        $facility = $this->facility('Central');
        $this->court($facility, 'Tennis', 80000, ['image_path' => '/images/tennis.jpg']);
        $this->court($facility, 'Football', 120000, ['image_path' => '/images/football.jpg']);

        Livewire::test(SearchFacilities::class)
            ->assertSee('Courts from')
            ->assertSeeHtml('text-black">800</span>')
            ->assertSee('Football · Tennis') // always in the same sport order
            ->set('type', 'Football')
            ->assertSee('Football from')
            ->assertSeeHtml('text-black">1,200</span>')
            ->assertDontSeeHtml('text-black">800</span>')
            ->assertSeeHtml('src="/images/football.jpg"');
    }

    public function test_price_sort_uses_the_selected_sport(): void
    {
        $a = $this->facility('Alpha');
        $this->court($a, 'Tennis', 5000);
        $this->court($a, 'Football', 200000);
        $this->court($this->facility('Beta'), 'Football', 100000);

        Livewire::test(SearchFacilities::class)
            ->set('sort', 'price_asc')
            ->assertSeeInOrder(['Alpha', 'Beta'])
            ->set('type', 'Football')
            ->assertSeeInOrder(['Beta', 'Alpha']);
    }

    public function test_time_search_lists_exact_matches_first_and_labels_alternatives(): void
    {
        $exact = $this->court($this->facility('Exact Arena'));
        $near = $this->court($this->facility('Near Arena'));
        $none = $this->court($this->facility('Busy Arena'));
        $this->book($near, '2030-06-04 19:00', '2030-06-04 20:00');
        $this->book($none, '2030-06-04 09:00', '2030-06-04 22:00');

        Livewire::test(SearchFacilities::class)
            ->set('date', '2030-06-04')
            ->set('time', '19:00')
            ->assertSeeInOrder(['Exact Arena', '19:00 free', 'Near Arena', 'Not at 19:00', '17:00, 18:00, 20:00 free'])
            ->assertDontSee('Busy Arena')
            ->assertSeeHtml('/facility/' . $exact->facility_id . '?date=2030-06-04&amp;time=19%3A00');
    }

    public function test_time_without_a_date_means_today(): void
    {
        $this->court($this->facility());

        Livewire::test(SearchFacilities::class)
            ->set('time', '08:00')
            ->assertSee('Today 08:00')
            ->assertSee('Not at 08:00') // 08:00 has started; 09:00 is gone too, 10:00 is the nearest
            ->assertSee('10:00 free');
    }

    public function test_facility_page_focuses_the_searched_sport_and_time(): void
    {
        $facility = $this->facility();
        $this->court($facility, 'Tennis');
        $football = $this->court($facility, 'Football');

        $component = Livewire::withQueryParams(['date' => '2030-06-04', 'sport' => 'Football', 'time' => '19:00'])
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('focusSport', 'Football')
            ->assertSet('selectedDate', '2030-06-04')
            ->assertSeeHtml('aria-current="true"');
        $this->assertSame($football->id, array_key_first($component->get('availableSlots')));
    }

    // --- Booking terms, cancellation and duration ---

    public function test_cancel_is_offered_only_before_the_deadline(): void
    {
        $user = User::factory()->create();
        $court = $this->court($this->facility());
        $tooLate = $this->book($court, now()->addHours(24)->format('Y-m-d H:i'), now()->addHours(25)->format('Y-m-d H:i'), $user);

        $this->assertFalse($tooLate->isCancellable());
        Livewire::actingAs($user)->test(UserDashboard::class)
            ->assertDontSeeHtml('wire:click="cancelBooking')
            ->assertSee('can\'t be cancelled online', false)
            ->call('cancelBooking', $tooLate->id);
        $this->assertSame('confirmed', $tooLate->fresh()->status);

        $this->travel(-1)->minutes();
        $this->assertTrue($tooLate->fresh()->isCancellable());
        Livewire::actingAs($user)->test(UserDashboard::class)
            ->assertSeeHtml('wire:click="cancelBooking')
            ->assertSee('Free cancellation until');
    }

    public function test_booking_dialog_states_the_cancellation_deadline_and_payment(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->call('selectSlot', $court->id, '19:00')
            ->assertSee('Starts within 24 hours, so it can\'t be cancelled online.', false)
            ->assertSee('No payment is taken online.')
            ->set('selectedDate', '2030-06-05')
            ->call('selectSlot', $court->id, '19:00')
            ->assertSeeText('Free cancellation until Tue, 4 Jun, 19:00.');
    }

    public function test_consecutive_hours_are_priced_per_hour_and_only_while_free(): void
    {
        $user = User::factory()->create();
        $facility = $this->facility();
        $court = $this->court($facility);
        $this->book($court, '2030-06-04 20:00', '2030-06-04 21:00');

        Livewire::actingAs($user)->test(FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', '2030-06-04')
            ->call('selectSlot', $court->id, '17:00')
            ->call('extendBooking')
            ->call('extendBooking')
            ->assertSet('selectedSlot.end', '20:00')
            ->call('extendBooking') // 20:00 is booked, and three hours is the limit anyway
            ->assertSet('selectedSlot.end', '20:00')
            ->call('shortenBooking')
            ->assertSet('selectedSlot.end', '19:00')
            ->call('confirmBooking')
            ->assertRedirect(route('dashboard'));

        // 17:00 at the base price, 18:00 with the +20% peak surcharge.
        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'total_price' => 10000 + 12000]);
        $this->assertSame('Bookings are 1 to 3 consecutive hours.', $this->bookingError($court, '2030-06-05 08:00', '2030-06-05 12:00'));
    }

    public function test_extra_hours_and_extras_survive_signing_in(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);
        $racket = \App\Models\Rental::create(['facility_id' => $facility->id, 'name' => 'Racket', 'price' => 5000, 'suitable_for' => ['Tennis']]);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->call('selectSlot', $court->id, '15:00')
            ->call('extendBooking')
            ->call('toggleRental', $racket->id)
            ->call('loginToBook');

        parse_str(parse_url(session('url.intended'), PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', true)
            ->assertSet('selectedSlot', ['start' => '15:00', 'end' => '17:00'])
            ->assertSet('selectedRentals', [$racket->id]);
    }

    // --- Assistant ---

    public function test_assistant_reports_closed_courts_instead_of_offering_them(): void
    {
        $this->travelTo(Carbon::parse('2030-10-05 09:30'));
        $facility = $this->facility('Ohrid Aquatic Center', 'Ohrid');
        $this->court($facility, 'Swimming', 10000, ['season_start_month' => 5, 'season_end_month' => 9]);
        $run = fn (array $args) => json_decode((new \ReflectionMethod(Chatbot::class, 'executeFunction'))->invoke(new Chatbot(), 'checkAvailability', $args), true);

        $result = $run(['date' => '2030-10-06', 'city' => 'Ohrid']);
        $this->assertArrayNotHasKey('courts', $result);
        $this->assertSame('Closed for the season (open May–September)', $result['closed_courts'][0]['reason']);

        $prepare = (new \ReflectionMethod(Chatbot::class, 'prepareBookingInternal'))->invoke(new Chatbot(), Court::first()->id, '2030-10-06', '10:00', '11:00');
        $this->assertStringContainsString('closed for the season', $prepare);
    }

    public function test_guest_confirming_an_assistant_booking_continues_on_the_booking_page(): void
    {
        $facility = $this->facility();
        $court = $this->court($facility);
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'prepareBooking', 'args' => ['court_id' => $court->id, 'date' => '2030-06-05', 'start_time' => '10:00', 'end_time' => '12:00']]]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Please confirm.']]]]]])]);

        Livewire::test(Chatbot::class)
            ->set('message', 'Book')->call('sendMessage')->call('handleBotResponse', 'Book')
            ->call('confirmPendingBooking')
            ->assertRedirect(route('login', ['book' => 1]));

        parse_str(parse_url(session('url.intended'), PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', true)
            ->assertSet('selectedSlot', ['start' => '10:00', 'end' => '12:00']);
    }

    public function test_assistant_without_a_model_does_not_promise_live_features_or_show_setup(): void
    {
        Livewire::test(Chatbot::class)
            ->assertDontSee('real-time')
            ->assertDontSee('GEMINI_API_KEY')
            ->set('message', 'what is open tonight?')->call('sendMessage')->call('handleBotResponse', 'what is open tonight?')
            ->assertDontSee('GEMINI_API_KEY')
            ->assertSee('use the search above');
    }

    // --- Password reset ---

    public function test_password_reset_link_answer_is_the_same_for_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $known = $this->post('/forgot-password', ['email' => 'Ana@example.com'])->assertSessionHas('status');
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        $this->assertSame(session('status'), $unknown->getSession()->get('status'));
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->get('/login')->assertSee('Forgot password?');
    }

    public function test_password_reset_link_ignores_a_forged_forwarded_host(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->withHeader('X-Forwarded-Host', 'evil.example')->post('/forgot-password', ['email' => 'ana@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, fn (ResetPassword $n) => !str_contains($n->toMail($user)->actionUrl, 'evil.example'));
    }

    public function test_password_can_be_reset_with_a_valid_token_only(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = Password::createToken($user);

        $this->get("/reset-password/{$token}?email=ana@example.com")->assertOk()->assertSee('Choose a new password');
        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'ana@example.com', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'ana@example.com', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-secret-1', $user->fresh()->password));
    }

    // --- Manager dashboard ---

    public function test_todays_schedule_shows_only_the_managers_facilities(): void
    {
        Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $own = $this->court($this->facility('Own'));
        $other = $this->court($this->facility('Other'));
        $manager->managedFacilities()->attach($own->facility_id);
        $mine = $this->book($own, '2030-06-03 18:00', '2030-06-03 19:00');
        $theirs = $this->book($other, '2030-06-03 18:00', '2030-06-03 19:00');
        $tomorrow = $this->book($own, '2030-06-04 18:00', '2030-06-04 19:00');

        $this->actingAs($manager);
        Livewire::test(TodaysBookings::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $tomorrow])
            ->callTableAction('checkIn', $mine);

        $this->assertSame('checked_in', $mine->fresh()->status);
    }
}
