<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'phone' => '555-0001',
        ]);

        $customer = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'phone' => '555-0002',
        ]);

        $facilityManager = User::create([
            'name' => 'Facility Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'phone' => '555-0003',
        ]);

        $superAdmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $facilityManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);
        $userRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $admin->assignRole($superAdmin);
        $customer->assignRole($userRole);
        $facilityManager->assignRole($facilityManagerRole);

        $parking = Amenity::create(['name' => 'Parking', 'icon' => 'heroicon-o-truck']);
        $wifi = Amenity::create(['name' => 'Wifi', 'icon' => 'heroicon-o-wifi']);
        $showers = Amenity::create(['name' => 'Showers', 'icon' => 'heroicon-o-beaker']);
        $lockers = Amenity::create(['name' => 'Lockers', 'icon' => 'heroicon-o-lock-closed']);
        $cafe = Amenity::create(['name' => 'Cafe', 'icon' => 'heroicon-o-cake']);

        $central = Facility::create([
            'name' => 'Central Sports Hall',
            'description' => 'Modern sports complex with multiple courts and facilities. Perfect for team sports and individual training.',
            'city' => 'Skopje',
            'address' => 'ul. Filip II Makedonski 12',
            'image_path' => '/images/sports-hall.jpg',
        ]);
        $central->amenities()->attach([$parking->id, $wifi->id, $showers->id, $lockers->id, $cafe->id]);
        Court::create(['facility_id' => $central->id, 'name' => 'Football Court A', 'type' => 'Football', 'base_price_per_hour' => 120000, 'image_path' => '/images/football-pitch.jpg']);
        Court::create(['facility_id' => $central->id, 'name' => 'Tennis Court 1', 'type' => 'Tennis', 'base_price_per_hour' => 80000, 'image_path' => '/images/tennis-court.jpg']);
        Court::create(['facility_id' => $central->id, 'name' => 'Indoor Pool', 'type' => 'Swimming', 'base_price_per_hour' => 120000, 'image_path' => '/images/swimming-pool.jpg']);

        $bitola = Facility::create([
            'name' => 'Bitola Sports Arena',
            'description' => 'Premier sports facility in Bitola with state-of-the-art equipment and professional-grade courts.',
            'city' => 'Bitola',
            'address' => 'bul. 1 Maj 45',
            'image_path' => '/images/football-pitch.jpg',
        ]);
        $bitola->amenities()->attach([$parking->id, $showers->id, $lockers->id]);
        Court::create(['facility_id' => $bitola->id, 'name' => 'Main Football Pitch', 'type' => 'Football', 'base_price_per_hour' => 100000, 'image_path' => '/images/football-pitch.jpg']);
        Court::create(['facility_id' => $bitola->id, 'name' => 'Padel Court 1', 'type' => 'Padel', 'base_price_per_hour' => 90000, 'image_path' => '/images/padel-court.jpg']);
        Court::create(['facility_id' => $bitola->id, 'name' => 'Tennis Court A', 'type' => 'Tennis', 'base_price_per_hour' => 70000, 'image_path' => '/images/tennis-court.jpg']);

        $ohrid = Facility::create([
            'name' => 'Ohrid Aquatic Center',
            'description' => 'Beautiful swimming and water sports facility located near Lake Ohrid.',
            'city' => 'Ohrid',
            'address' => 'ul. Kej Makedonija 8',
            'image_path' => '/images/swimming-pool.jpg',
        ]);
        $ohrid->amenities()->attach([$parking->id, $showers->id, $lockers->id, $cafe->id]);
        Court::create(['facility_id' => $ohrid->id, 'name' => 'Olympic Pool', 'type' => 'Swimming', 'base_price_per_hour' => 120000, 'image_path' => '/images/swimming-pool.jpg']);
        Court::create(['facility_id' => $ohrid->id, 'name' => 'Training Pool', 'type' => 'Swimming', 'base_price_per_hour' => 80000, 'image_path' => '/images/swimming-pool.jpg']);
        Court::create(['facility_id' => $ohrid->id, 'name' => 'Tennis Court', 'type' => 'Tennis', 'base_price_per_hour' => 75000, 'image_path' => '/images/tennis-court.jpg']);

        $tetovo = Facility::create([
            'name' => 'Tetovo Sports Complex',
            'description' => 'Multi-purpose sports facility with modern amenities and excellent maintenance.',
            'city' => 'Tetovo',
            'address' => 'ul. Ilindenska 22',
            'image_path' => '/images/sports-hall.jpg',
        ]);
        $tetovo->amenities()->attach([$wifi->id, $showers->id, $parking->id]);
        Court::create(['facility_id' => $tetovo->id, 'name' => 'Football Pitch', 'type' => 'Football', 'base_price_per_hour' => 110000, 'image_path' => '/images/football-pitch.jpg']);
        Court::create(['facility_id' => $tetovo->id, 'name' => 'Padel Court', 'type' => 'Padel', 'base_price_per_hour' => 85000, 'image_path' => '/images/padel-court.jpg']);
        Court::create(['facility_id' => $tetovo->id, 'name' => 'Indoor Court', 'type' => 'Tennis', 'base_price_per_hour' => 70000, 'image_path' => '/images/tennis-court.jpg']);

        $prilep = Facility::create([
            'name' => 'Prilep Professional Arena',
            'description' => 'Professional-grade sports arena with top-tier facilities for competitive and recreational sports.',
            'city' => 'Prilep',
            'address' => 'ul. Marksova 15',
            'image_path' => '/images/football-pitch.jpg',
        ]);
        $prilep->amenities()->attach([$parking->id, $wifi->id, $showers->id, $lockers->id, $cafe->id]);
        Court::create(['facility_id' => $prilep->id, 'name' => 'Professional Pitch', 'type' => 'Football', 'base_price_per_hour' => 200000, 'image_path' => '/images/football-pitch.jpg']);
        Court::create(['facility_id' => $prilep->id, 'name' => 'Pro Tennis Court', 'type' => 'Tennis', 'base_price_per_hour' => 100000, 'image_path' => '/images/tennis-court.jpg']);
        Court::create(['facility_id' => $prilep->id, 'name' => 'Padel Arena', 'type' => 'Padel', 'base_price_per_hour' => 95000, 'image_path' => '/images/padel-court.jpg']);

        foreach ([$central, $bitola, $ohrid, $tetovo, $prilep] as $facility) {
            Rental::create(['facility_id' => $facility->id, 'name' => 'Racket', 'price' => 500, 'suitable_for' => ['Tennis', 'Padel']]);
            Rental::create(['facility_id' => $facility->id, 'name' => 'Ball', 'price' => 300, 'suitable_for' => ['Football']]);
            Rental::create(['facility_id' => $facility->id, 'name' => 'Towel', 'price' => 200, 'suitable_for' => ['Swimming', 'Tennis', 'Padel', 'Football']]);
            Rental::create(['facility_id' => $facility->id, 'name' => 'Goggles', 'price' => 400, 'suitable_for' => ['Swimming']]);
            Rental::create(['facility_id' => $facility->id, 'name' => 'Swim Cap', 'price' => 250, 'suitable_for' => ['Swimming']]);
            Rental::create(['facility_id' => $facility->id, 'name' => 'Shin Guards', 'price' => 350, 'suitable_for' => ['Football']]);
        }

        $facilityManager->managedFacilities()->sync([$central->id, $tetovo->id]);

        Review::create(['user_id' => $customer->id, 'facility_id' => $central->id, 'rating' => 5, 'comment' => 'Amazing facilities!']);
        Review::create(['user_id' => $customer->id, 'facility_id' => $bitola->id, 'rating' => 4, 'comment' => 'Great courts, could use better parking']);
        Review::create(['user_id' => $customer->id, 'facility_id' => $ohrid->id, 'rating' => 5, 'comment' => 'Best swimming pool in the region']);
        Review::create(['user_id' => $customer->id, 'facility_id' => $prilep->id, 'rating' => 5, 'comment' => 'Top-notch professional facilities']);
    }
}
