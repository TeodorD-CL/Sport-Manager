<?php

namespace Tests\Feature;

use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Filament\Resources\CourtResource;
use App\Filament\Resources\FacilityResource;
use App\Filament\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    private Court $court;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);

        $facility = Facility::create(['name' => 'Hall', 'description' => 'x', 'city' => 'Skopje', 'address' => 'x', 'opening_hour' => 8, 'closing_hour' => 22]);
        $this->court = Court::create(['facility_id' => $facility->id, 'name' => 'Court 1', 'type' => 'Tennis', 'base_price_per_hour' => 10000]);
        $this->customer = User::factory()->create();
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function formData(string $start, string $end, array $overrides = []): array
    {
        $monday = Carbon::parse('next monday')->format('Y-m-d');

        return array_merge([
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'start_time' => "$monday $start",
            'end_time' => "$monday $end",
            'status' => 'confirmed',
            'total_price' => null,
        ], $overrides);
    }

    public function test_admin_booking_gets_qr_code_and_calculated_price(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formData('10:00', '12:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::first();
        $this->assertNotNull($booking->qr_code);
        $this->assertSame(20000, $booking->total_price);
    }

    public function test_admin_price_is_entered_in_mkd(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formData('10:00', '11:00', ['total_price' => 1200]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(120000, Booking::first()->total_price);
    }

    public function test_admin_booking_is_validated(): void
    {
        $this->actingAsSuperAdmin();
        $existing = Booking::create([
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'start_time' => Carbon::parse('next monday 14:00'),
            'end_time' => Carbon::parse('next monday 15:00'),
            'status' => 'confirmed',
            'total_price' => 10000,
        ]);

        foreach ([
            $this->formData('11:00', '10:00'),
            $this->formData('06:00', '07:00'),
            $this->formData('10:30', '11:30'),
            $this->formData('14:00', '16:00'),
            $this->formData('10:00', '11:00', ['start_time' => null]),
        ] as $data) {
            Livewire::test(CreateBooking::class)
                ->fillForm($data)
                ->call('create')
                ->assertHasFormErrors();
        }

        $this->assertDatabaseCount('bookings', 1);

        // Editing a booking doesn't clash with itself.
        Livewire::test(EditBooking::class, ['record' => $existing->getRouteKey()])
            ->fillForm(['end_time' => Carbon::parse('next monday 16:00')->format('Y-m-d H:i')])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_manager_cannot_book_courts_of_other_facilities(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $own = Facility::create(['name' => 'Own', 'description' => 'x', 'city' => 'Skopje', 'address' => 'x']);
        $manager->managedFacilities()->attach($own->id);
        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formData('10:00', '11:00'))
            ->call('create')
            ->assertHasFormErrors(['court_id']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_managers_cannot_bulk_delete_reviews_or_delete_facilities(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $manager->managedFacilities()->attach($this->court->facility_id);
        $this->actingAs($manager);

        $this->assertFalse(ReviewResource::canDeleteAny());
        $this->assertFalse(FacilityResource::canDeleteAny());
        $this->assertFalse(FacilityResource::canDelete($this->court->facility));
    }

    public function test_courts_with_bookings_cannot_be_deleted_in_admin(): void
    {
        $this->actingAsSuperAdmin();
        $this->assertTrue(CourtResource::canDelete($this->court));

        Booking::create([
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'confirmed',
            'total_price' => 10000,
        ]);

        $this->assertFalse(CourtResource::canDelete($this->court));
        $this->assertFalse(FacilityResource::canDelete($this->court->facility));
    }

    public function test_admin_panel_uses_the_site_login(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/admin')->assertOk();
    }
}
