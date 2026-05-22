<?php

namespace Tests\Feature;

use App\Filament\Resources\AmenityResource;
use App\Filament\Resources\FacilityResource;
use App\Filament\Resources\RentalResource;
use App\Filament\Resources\ReviewResource;
use App\Livewire\FacilityDetail;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createFacility(string $name): Facility
    {
        return Facility::create([
            'name' => $name,
            'description' => 'Test facility',
            'city' => 'Skopje',
            'address' => 'Address 1',
            'opening_hour' => 8,
            'closing_hour' => 22,
        ]);
    }

    private function createCourt(Facility $facility): Court
    {
        return Court::create([
            'facility_id' => $facility->id,
            'name' => 'Court ' . $facility->name,
            'type' => 'Tennis',
            'base_price_per_hour' => 10000,
        ]);
    }

    private function ensureRoles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_admin_panel_access_requires_super_admin_or_assigned_facility_manager(): void
    {
        $this->ensureRoles();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->assertTrue($superAdmin->hasAdminPanelAccess());

        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $this->assertFalse($manager->hasAdminPanelAccess());

        $facility = $this->createFacility('Managed Facility');
        $manager->managedFacilities()->attach($facility->id);
        $this->assertTrue($manager->fresh()->hasAdminPanelAccess());

        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');
        $this->assertFalse($regularUser->hasAdminPanelAccess());
    }

    public function test_facility_manager_only_sees_managed_facilities_in_admin_resource(): void
    {
        $this->ensureRoles();

        $facilityA = $this->createFacility('Facility A');
        $facilityB = $this->createFacility('Facility B');

        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $manager->managedFacilities()->attach($facilityA->id);

        $this->actingAs($manager, 'admin');

        $visibleFacilityIds = FacilityResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$facilityA->id], $visibleFacilityIds);
        $this->assertNotContains($facilityB->id, $visibleFacilityIds);
    }

    public function test_facility_manager_cannot_access_global_amenity_resource(): void
    {
        $this->ensureRoles();

        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $facility = $this->createFacility('Managed Facility');
        $manager->managedFacilities()->attach($facility->id);

        $this->actingAs($manager, 'admin');

        $this->assertFalse(AmenityResource::canViewAny());
    }

    public function test_facility_manager_can_manage_only_own_facility_rentals(): void
    {
        $this->ensureRoles();

        $facilityA = $this->createFacility('Facility A');
        $facilityB = $this->createFacility('Facility B');

        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $manager->managedFacilities()->attach($facilityA->id);

        $ownRental = Rental::create([
            'facility_id' => $facilityA->id,
            'name' => 'Own Rental',
            'price' => 10000,
            'suitable_for' => ['Tennis'],
        ]);

        $otherRental = Rental::create([
            'facility_id' => $facilityB->id,
            'name' => 'Other Rental',
            'price' => 12000,
            'suitable_for' => ['Tennis'],
        ]);

        $this->actingAs($manager, 'admin');

        $visibleRentalIds = RentalResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($ownRental->id, $visibleRentalIds);
        $this->assertNotContains($otherRental->id, $visibleRentalIds);
        $this->assertTrue(RentalResource::canEdit($ownRental));
        $this->assertFalse(RentalResource::canEdit($otherRental));
    }

    public function test_facility_manager_reviews_are_scoped_and_read_only(): void
    {
        $this->ensureRoles();

        $facilityA = $this->createFacility('Facility A');
        $facilityB = $this->createFacility('Facility B');

        $manager = User::factory()->create();
        $manager->assignRole('facility_manager');
        $manager->managedFacilities()->attach($facilityA->id);

        $reviewAuthor = User::factory()->create();

        $ownReview = Review::create([
            'user_id' => $reviewAuthor->id,
            'facility_id' => $facilityA->id,
            'rating' => 5,
            'comment' => 'Great place',
        ]);

        $otherReview = Review::create([
            'user_id' => $reviewAuthor->id,
            'facility_id' => $facilityB->id,
            'rating' => 4,
            'comment' => 'Good place',
        ]);

        $this->actingAs($manager, 'admin');

        $visibleReviewIds = ReviewResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($ownReview->id, $visibleReviewIds);
        $this->assertNotContains($otherReview->id, $visibleReviewIds);
        $this->assertFalse(ReviewResource::canCreate());
        $this->assertFalse(ReviewResource::canEdit($ownReview));
        $this->assertFalse(ReviewResource::canDelete($ownReview));
    }

    public function test_facility_schedule_defines_public_booking_slots(): void
    {
        $facility = $this->createFacility('Scheduled Facility');
        $facility->update([
            'opening_hour' => 10,
            'closing_hour' => 14,
        ]);

        $court = $this->createCourt($facility);
        $tomorrow = now()->addDay()->format('Y-m-d');

        $component = Livewire::test(FacilityDetail::class, ['id' => $facility->id])
            ->set('selectedDate', $tomorrow);

        $slots = $component->get('availableSlots');
        $courtSlots = $slots[$court->id]['slots'] ?? [];
        $slotStarts = array_map(fn (array $slot) => $slot['start'], $courtSlots);

        $this->assertSame(['10:00', '11:00', '12:00', '13:00'], $slotStarts);
    }
}
