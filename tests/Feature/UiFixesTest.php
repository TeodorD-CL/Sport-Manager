<?php

namespace Tests\Feature;

use App\Livewire\FacilityDetail;
use App\Livewire\SearchFacilities;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiFixesTest extends TestCase
{
    use RefreshDatabase;

    private function createFacility(array $attributes = []): Facility
    {
        return Facility::create($attributes + [
            'name' => 'Test Facility',
            'description' => 'Test',
            'city' => 'Skopje',
            'address' => 'Test St 1',
            'opening_hour' => 8,
            'closing_hour' => 22,
        ]);
    }

    private function createCourt(Facility $facility, array $attributes = []): Court
    {
        return Court::create($attributes + [
            'facility_id' => $facility->id,
            'name' => 'Test Court',
            'type' => 'Tennis',
            'base_price_per_hour' => 10000,
        ]);
    }

    public function test_facility_page_hides_hours_that_already_started(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 14:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        $slots = Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('selectedDate', '2030-06-03')
            ->get('availableSlots')[$court->id]['slots'];

        $this->assertSame(['15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'], array_column($slots, 'start'));
    }

    public function test_facility_page_opens_on_tomorrow_when_today_is_over(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 22:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        $component = Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('selectedDate', '2030-06-04');

        $this->assertCount(14, $component->get('availableSlots')[$court->id]['slots']);
    }

    public function test_day_with_no_hours_left_shows_a_message(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 22:30'));
        $facility = $this->createFacility();
        $this->createCourt($facility);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertDontSee('No more times left on this day.')
            ->set('selectedDate', '2030-06-03')
            ->assertSee('No more times left on this day.');
    }

    public function test_booked_slot_is_announced_as_booked(): void
    {
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $start = Carbon::tomorrow()->setTime(10, 0);
        Booking::create([
            'user_id' => User::factory()->create()->id,
            'court_id' => $court->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'confirmed',
            'total_price' => 10000,
            'qr_code' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', $start->format('Y-m-d'))
            ->assertSeeHtml('aria-label="10:00 booked"');
    }

    public function test_duplicate_photos_are_shown_once(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/hall.jpg']);
        $this->createCourt($facility, ['image_path' => 'photos/hall.jpg']);

        $html = Livewire::test(FacilityDetail::class, ['id' => $facility->id])->html();

        $this->assertSame(1, substr_count($html, '/storage/photos/hall.jpg'));
    }

    public function test_search_shows_count_filter_chips_and_chat_card(): void
    {
        $this->createCourt($this->createFacility());
        $this->createCourt($this->createFacility(['name' => 'Other', 'city' => 'Bitola']));

        Livewire::test(SearchFacilities::class)
            ->assertSee('2 facilities')
            ->assertDontSee('05 available')
            ->assertDontSee('Remove filter')
            ->assertSee('Not sure where to play?')
            ->set('city', 'Skopje')
            ->assertSee('1 facility')
            ->assertSeeHtml('aria-label="Remove filter Skopje"')
            ->assertSee('Clear all');
    }

    public function test_chat_card_is_hidden_when_the_last_row_is_full(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->createCourt($this->createFacility(['name' => $name]));
        }

        Livewire::test(SearchFacilities::class)
            ->assertSee('3 facilities')
            ->assertDontSee('Not sure where to play?');
    }

    public function test_fully_booked_today_opens_on_tomorrow(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 19:30'));
        $user = User::factory()->create();
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        foreach ([20, 21] as $hour) {
            $start = Carbon::parse('2030-06-03')->setTime($hour, 0);
            Booking::create([
                'user_id' => $user->id,
                'court_id' => $court->id,
                'start_time' => $start,
                'end_time' => $start->copy()->addHour(),
                'status' => 'confirmed',
                'total_price' => 10000,
                'qr_code' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        }

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('selectedDate', '2030-06-04');
    }

    public function test_one_free_hour_left_keeps_today(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 22:30'));
        $facility = $this->createFacility(['closing_hour' => 24]);
        $court = $this->createCourt($facility);

        $slots = Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('selectedDate', '2030-06-03')
            ->get('availableSlots')[$court->id]['slots'];

        $this->assertSame([['start' => '23:00', 'end' => '24:00']], array_map(fn ($s) => ['start' => $s['start'], 'end' => $s['end']], $slots));
    }

    public function test_facility_without_courts_still_renders(): void
    {
        $facility = $this->createFacility();

        $this->get('/facility/' . $facility->id)
            ->assertOk()
            ->assertSee('Test Facility');
    }

    public function test_hour_that_already_started_cannot_be_booked_today(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 14:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        Livewire::actingAs(User::factory()->create())
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->call('selectSlot', $court->id, '14:00')
            ->call('confirmBooking');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_facility_page_has_back_link_and_free_times_anchor(): void
    {
        $facility = $this->createFacility();
        $this->createCourt($facility);

        $this->get('/facility/' . $facility->id)
            ->assertOk()
            ->assertSee('All facilities')
            ->assertSeeHtml('href="#availability"')
            ->assertSeeHtml('id="availability"')
            ->assertSee('Pick a free time to book it.');
    }

    public function test_single_photo_spans_full_width_and_two_photos_split(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/a.jpg']);
        $this->createCourt($facility);

        $this->get('/facility/' . $facility->id)->assertDontSeeHtml('md:grid-cols-2 gap-4 mb-12');

        $this->createCourt($facility, ['image_path' => 'photos/b.jpg']);

        $this->get('/facility/' . $facility->id)
            ->assertSeeHtml('md:grid-cols-2 gap-4 mb-12')
            ->assertSeeHtml('/storage/photos/a.jpg')
            ->assertSeeHtml('/storage/photos/b.jpg');
    }

    public function test_layout_has_skip_link_and_highlights_only_the_current_page(): void
    {
        $browse = '<a href="/" class="text-[13px] font-bold border-b-2 pb-1 mt-1 text-black border-black">Browse</a>';
        $facility = $this->createFacility();

        $this->get('/')
            ->assertSee('Skip to content')
            ->assertSeeHtml('href="#main"')
            ->assertSeeHtml('<main id="main"')
            ->assertSeeHtml($browse);

        $this->get('/facility/' . $facility->id)->assertDontSeeHtml($browse);
    }

    public function test_type_date_and_amenity_filters_show_as_chips(): void
    {
        $amenity = \App\Models\Amenity::create(['name' => 'Parking', 'icon' => 'car']);
        $facility = $this->createFacility();
        $facility->amenities()->attach($amenity);
        $this->createCourt($facility);
        $date = Carbon::tomorrow()->format('Y-m-d');

        Livewire::test(SearchFacilities::class)
            ->set('type', 'Tennis')
            ->set('date', $date)
            ->set('amenityFilters', [$amenity->id])
            ->assertSeeHtml('aria-label="Remove filter Tennis"')
            ->assertSeeHtml('aria-label="Remove filter ' . $date . '"')
            ->assertSeeHtml('aria-label="Remove filter Parking"')
            ->assertSee('1 facility')
            ->call('clearFilters')
            ->assertDontSee('Remove filter');
    }

    public function test_unknown_amenity_in_url_shows_no_chip(): void
    {
        $this->createCourt($this->createFacility());

        Livewire::withQueryParams(['amenities' => ['not-a-uuid']])
            ->test(SearchFacilities::class)
            ->assertDontSee('Remove filter')
            ->assertSee('1 facility');
    }

    public function test_chat_suggestions_have_no_emoji_and_still_send(): void
    {
        $component = Livewire::test(\App\Livewire\Chatbot::class);

        foreach ($component->get('suggestions') as $suggestion) {
            $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', $suggestion);
        }

        $count = count($component->get('messages'));
        $component->call('sendPreset', $component->get('suggestions')[0]);
        $this->assertGreaterThan($count, count($component->get('messages')));
    }
}
