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

    /** The gallery grid only, so the viewer and court thumbnails further down don't count. */
    private function gallery(Facility $facility): string
    {
        $html = Livewire::test(FacilityDetail::class, ['id' => $facility->id])->html();
        $start = strpos($html, 'gap-4 mb-12');

        return substr($html, $start, strpos($html, '<template x-teleport', $start) - $start);
    }

    /** The photo list handed to the full-screen viewer. */
    private function viewerPhotos(Facility $facility): array
    {
        $html = Livewire::test(FacilityDetail::class, ['id' => $facility->id])->html();
        preg_match("/photos: JSON\\.parse\\('(.*?)'\\)/", $html, $match);

        // @js writes a JS string literal whose escapes are also valid JSON string escapes.
        return json_decode(json_decode('"' . $match[1] . '"'), true);
    }

    public function test_duplicate_photos_are_shown_once(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/hall.jpg']);
        $this->createCourt($facility, ['image_path' => 'photos/hall.jpg']);

        $this->assertSame(1, substr_count($this->gallery($facility), '/storage/photos/hall.jpg'));
    }

    public function test_gallery_counts_every_photo_and_shows_at_most_three(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->createCourt($facility, ['name' => "Court $name", 'image_path' => "photos/$name.jpg"]);
        }

        $gallery = $this->gallery($facility);

        $this->assertSame(3, substr_count($gallery, '<img '));
        $this->assertStringContainsString('1 / 5', $gallery);      // phone
        $this->assertStringContainsString('+3 photos', $gallery);  // tablet
        $this->assertStringContainsString('+2 photos', $gallery);  // desktop
        $this->assertStringContainsString('lg:grid-cols-3', $gallery);
    }

    public function test_three_photos_use_singular_and_no_desktop_overlay(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        $this->createCourt($facility, ['name' => 'Court A', 'image_path' => 'photos/a.jpg']);
        $this->createCourt($facility, ['name' => 'Court B', 'image_path' => 'photos/b.jpg']);

        $gallery = $this->gallery($facility);

        $this->assertStringContainsString('1 / 3', $gallery);
        $this->assertSame(1, preg_match_all('/\+\d+ photos?</', $gallery));
        $this->assertStringContainsString('+1 photo<', $gallery);
    }

    public function test_gallery_alt_text_names_the_court_and_only_the_first_photo_loads_eagerly(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        $this->createCourt($facility, ['name' => 'Football Court A', 'image_path' => 'photos/a.jpg']);
        $this->createCourt($facility, ['name' => 'Court B', 'image_path' => 'photos/b.jpg']);

        $gallery = $this->gallery($facility);

        $this->assertStringContainsString('aria-label="Open photo 1 of 3: Test Facility"', $gallery);
        $this->assertStringContainsString('aria-label="Open photo 2 of 3: Football Court A at Test Facility"', $gallery);
        $this->assertSame(1, substr_count($gallery, 'loading="eager"'));
        $this->assertSame(2, substr_count($gallery, 'loading="lazy"'));
    }

    public function test_viewer_gets_every_photo_in_gallery_order(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->createCourt($facility, ['name' => "Court $name", 'image_path' => "photos/$name.jpg"]);
        }
        $this->createCourt($facility, ['name' => 'Court E', 'image_path' => 'photos/main.jpg']);
        $this->createCourt($facility, ['name' => 'No Photo']);

        $photos = $this->viewerPhotos($facility);

        $this->assertCount(5, $photos);
        $this->assertSame(['src' => '/storage/photos/main.jpg', 'alt' => 'Test Facility'], $photos[0]);
        $this->assertEqualsCanonicalizing(
            ['/storage/photos/A.jpg', '/storage/photos/B.jpg', '/storage/photos/C.jpg', '/storage/photos/D.jpg'],
            array_column(array_slice($photos, 1), 'src')
        );
    }

    public function test_every_visible_tile_opens_the_viewer_at_its_own_photo(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->createCourt($facility, ['name' => "Court $name", 'image_path' => "photos/$name.jpg"]);
        }

        $gallery = $this->gallery($facility);

        foreach ([0, 1, 2] as $n) {
            $this->assertStringContainsString("@click=\"show($n)\"", $gallery);
        }
        $this->assertStringNotContainsString('show(3)', $gallery);
    }

    public function test_viewer_is_an_accessible_dialog_outside_the_page_content(): void
    {
        $facility = $this->createFacility(['image_path' => 'photos/main.jpg']);
        $this->createCourt($facility, ['image_path' => 'photos/a.jpg']);

        $this->get('/facility/' . $facility->id)
            ->assertOk()
            ->assertSeeHtml('<template x-teleport="body">')
            ->assertSeeHtml('role="dialog" aria-modal="true" aria-label="Photos of Test Facility"')
            ->assertSeeHtml('x-trap.noscroll="open"')
            ->assertSeeHtml('aria-label="Close photos"')
            ->assertSeeHtml('aria-label="Previous photo"')
            ->assertSeeHtml('aria-label="Next photo"');
    }

    public function test_quotes_and_tags_in_names_cannot_break_out_of_the_viewer_data(): void
    {
        $facility = $this->createFacility(['name' => 'Bob\'s "Arena" <script>alert(1)</script>', 'image_path' => 'photos/main.jpg']);
        $this->createCourt($facility, ['name' => "Court ' \" </div>", 'image_path' => 'photos/a.jpg']);

        $html = Livewire::test(FacilityDetail::class, ['id' => $facility->id])->html();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('Bob\'s "Arena" <script>alert(1)</script>', $this->viewerPhotos($facility)[0]['alt']);
        $this->assertSame("Court ' \" </div> at Bob's \"Arena\" <script>alert(1)</script>", $this->viewerPhotos($facility)[1]['alt']);
    }

    public function test_facility_without_photos_falls_back_to_the_default_image(): void
    {
        $facility = $this->createFacility();
        $this->createCourt($facility);

        $gallery = $this->gallery($facility);

        $this->assertStringContainsString('/images/sports-hall.jpg', $gallery);
        $this->assertStringNotContainsString('1 / ', $gallery);
        $this->assertStringNotContainsString('photo', strip_tags($gallery));
    }

    public function test_court_card_shows_its_photo_only_when_it_has_one(): void
    {
        $facility = $this->createFacility();
        $this->createCourt($facility, ['name' => 'With Photo', 'image_path' => 'https://example.com/court.jpg']);
        $this->createCourt($facility, ['name' => 'No Photo']);

        $html = Livewire::test(FacilityDetail::class, ['id' => $facility->id])->html();
        $thumbnails = substr_count($html, 'w-16 h-16 sm:w-20 sm:h-20');

        $this->assertSame(1, $thumbnails);
        $this->assertStringContainsString('<img src="https://example.com/court.jpg" alt="" loading="lazy" width="80" height="80" class="w-16 h-16', $html);
    }

    public function test_court_thumbnail_survives_a_date_change(): void
    {
        $facility = $this->createFacility();
        $this->createCourt($facility, ['image_path' => 'photos/court.jpg']);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', Carbon::today()->addDays(3)->format('Y-m-d'))
            ->assertSeeHtml('<img src="/storage/photos/court.jpg" alt="" loading="lazy" width="80" height="80" class="w-16 h-16');
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

        $this->assertStringContainsString('sm:col-span-2', $this->gallery($facility));
        $this->assertStringContainsString('lg:h-[420px]', $this->gallery($facility));

        $this->createCourt($facility, ['image_path' => 'photos/b.jpg']);
        $gallery = $this->gallery($facility);

        $this->assertStringNotContainsString('sm:col-span-2', $gallery);
        $this->assertStringNotContainsString('lg:grid-cols-3', $gallery);
        $this->assertStringContainsString('lg:h-[420px]', $gallery);
        $this->assertStringContainsString('/storage/photos/a.jpg', $gallery);
        $this->assertStringContainsString('/storage/photos/b.jpg', $gallery);
        $this->assertStringContainsString('1 / 2', $gallery);
        $this->assertStringNotContainsString(' photos', $gallery);
    }

    public function test_layout_has_skip_link_and_highlights_only_the_current_page(): void
    {
        $browse = '<a href="/" class="text-[13px] font-bold border-b-2 py-2.5 text-black border-black">Browse</a>';
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
        $dateLabel = Carbon::tomorrow()->format('D, j M');

        Livewire::test(SearchFacilities::class)
            ->set('type', 'Tennis')
            ->set('date', $date)
            ->set('amenityFilters', [$amenity->id])
            ->assertSeeHtml('aria-label="Remove filter Tennis"')
            ->assertSeeHtml('aria-label="Remove filter ' . $dateLabel . '"')
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

    public function test_guest_returns_to_the_same_slot_after_signing_in(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 09:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->call('selectSlot', $court->id, '19:00')
            ->call('loginToBook')
            ->assertRedirect(route('login', ['book' => 1]));

        $intended = session('url.intended');
        $this->assertStringContainsString('date=2030-06-03', $intended);
        $this->assertStringContainsString('slot=19%3A00', $intended);

        parse_str(parse_url($intended, PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', true)
            ->assertSet('selectedCourtId', $court->id)
            ->assertSet('selectedSlot.start', '19:00');
    }

    public function test_peak_slots_are_marked(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 09:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        $slots = collect(Livewire::test(FacilityDetail::class, ['id' => $facility->id])->get('availableSlots')[$court->id]['slots']);

        $this->assertSame(['18:00', '19:00', '20:00', '21:00'], $slots->where('peak', true)->pluck('start')->values()->all());
    }

    public function test_return_link_does_not_reopen_a_slot_that_was_taken_meanwhile(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 09:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);
        $start = Carbon::parse('2030-06-03 19:00');
        Booking::create([
            'user_id' => User::factory()->create()->id,
            'court_id' => $court->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'confirmed',
            'total_price' => 12000,
        ]);

        Livewire::withQueryParams(['date' => '2030-06-03', 'court' => $court->id, 'slot' => '19:00'])
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', false)
            ->assertSee('That time is no longer available.')
            ->call('selectSlot', $court->id, '20:00')
            ->assertDontSee('That time is no longer available.');

        Livewire::withQueryParams(['date' => '2030-06-03', 'court' => $court->id, 'slot' => '03:00'])
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', false)
            ->assertSee('That time is no longer available.')
            ->set('selectedDate', '2030-06-04')
            ->assertDontSee('That time is no longer available.');
    }

    public function test_return_link_with_a_bad_or_past_date_keeps_the_default_date(): void
    {
        $this->travelTo(Carbon::parse('2030-06-03 09:30'));
        $facility = $this->createFacility();
        $court = $this->createCourt($facility);

        foreach (['garbage', '2030-02-30', '2020-01-01', '2030-06-02'] as $date) {
            Livewire::withQueryParams(['date' => $date, 'court' => $court->id, 'slot' => '10:00'])
                ->test(FacilityDetail::class, ['id' => $facility->id])
                ->assertSet('selectedDate', '2030-06-03')
                ->assertSet('showBookingModal', false)
                ->assertSee('That time is no longer available.');
        }

        Livewire::withQueryParams(['date' => '2030-06-03', 'court' => $court->id, 'slot' => '10:00'])
            ->test(FacilityDetail::class, ['id' => $facility->id])
            ->assertSet('showBookingModal', true);
    }

    public function test_signing_in_from_elsewhere_forgets_an_abandoned_booking(): void
    {
        $booking = '/facility/x?date=2030-06-03&court=y&slot=19%3A00';

        $this->withSession(['url.intended' => $booking])->get('/login?book=1');
        $this->assertSame($booking, session('url.intended'));

        $this->get('/login');
        $this->assertNull(session('url.intended'));

        // Other return addresses, like a guest opening /dashboard, are kept.
        $this->withSession(['url.intended' => '/dashboard'])->get('/register');
        $this->assertSame('/dashboard', session('url.intended'));
    }

    public function test_search_card_shows_the_first_added_court_photo(): void
    {
        // Inserted second but dated earlier, so insertion order alone would pick the wrong photo.
        $facility = $this->createFacility();
        $this->createCourt($facility, ['name' => 'Second', 'image_path' => '/images/second.jpg']);
        $this->travelTo(now()->subMinute());
        $this->createCourt($facility, ['name' => 'First', 'image_path' => '/images/first.jpg']);
        $this->travelBack();

        Livewire::test(SearchFacilities::class)
            ->assertSeeHtml('src="/images/first.jpg"')
            ->assertDontSeeHtml('src="/images/second.jpg"');
    }
}
