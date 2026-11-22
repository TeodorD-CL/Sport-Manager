<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Facility;
use App\Models\Rental;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Safe to run more than once: every record is matched by its natural key before it is created.
    public function run(): void
    {
        // Demo accounts must never get a known password on a live site.
        $password = env('SEED_USER_PASSWORD');
        if (app()->isProduction() && blank($password)) {
            throw new \RuntimeException('Set SEED_USER_PASSWORD before seeding a production database.');
        }
        $password = bcrypt($password ?: 'password');

        $admin = User::firstOrCreate(['email' => 'admin@test.com'], [
            'name' => 'Admin User',
            'password' => $password,
            'phone' => '555-0001',
        ]);

        $customer = User::firstOrCreate(['email' => 'user@test.com'], [
            'name' => 'Stefan Ilievski',
            'password' => $password,
            'phone' => '555-0002',
        ]);

        $facilityManager = User::firstOrCreate(['email' => 'manager@test.com'], [
            'name' => 'Facility Manager',
            'password' => $password,
            'phone' => '555-0003',
        ]);

        $superAdmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $facilityManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'facility_manager', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);
        $userRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $admin->syncRoles($superAdmin);
        $customer->syncRoles($userRole);
        $facilityManager->syncRoles($facilityManagerRole);

        $parking = Amenity::firstOrCreate(['name' => 'Parking'], ['icon' => 'heroicon-o-truck']);
        $wifi = Amenity::firstOrCreate(['name' => 'Wifi'], ['icon' => 'heroicon-o-wifi']);
        $showers = Amenity::firstOrCreate(['name' => 'Showers'], ['icon' => 'heroicon-o-beaker']);
        $lockers = Amenity::firstOrCreate(['name' => 'Lockers'], ['icon' => 'heroicon-o-lock-closed']);
        $cafe = Amenity::firstOrCreate(['name' => 'Cafe'], ['icon' => 'heroicon-o-cake']);

        $central = Facility::firstOrCreate(['name' => 'Central Sports Hall'], [
            'description' => 'Skopje\'s busiest multi-sport venue, two minutes from the city square. A sprung-floor indoor hall for five-a-side, a covered hard tennis court and a heated 25 m pool, with changing rooms, lockers and a cafe overlooking the courts. Floodlit and open late, so after-work games are easy to fit in.',
            'city' => 'Skopje',
            'address' => 'ul. Filip II Makedonski 12',
            'image_path' => 'https://images.unsplash.com/photo-1504450758481-7338eba7524a?auto=format&fit=crop&w=1600&q=80',
            'opening_hour' => 7,
            'closing_hour' => 23,
        ]);
        $central->amenities()->syncWithoutDetaching([$parking->id, $wifi->id, $showers->id, $lockers->id, $cafe->id]);
        Court::firstOrCreate(['facility_id' => $central->id, 'name' => 'Football Court A'], ['type' => 'Football', 'base_price_per_hour' => 120000, 'image_path' => '/images/football-pitch.jpg']);
        Court::firstOrCreate(['facility_id' => $central->id, 'name' => 'Tennis Court 1'], ['type' => 'Tennis', 'base_price_per_hour' => 80000, 'image_path' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $central->id, 'name' => 'Indoor Pool'], ['type' => 'Swimming', 'base_price_per_hour' => 120000, 'image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=1600&q=80']);

        $bitola = Facility::firstOrCreate(['name' => 'Bitola Sports Arena'], [
            'description' => 'A well-kept outdoor complex on the edge of Bitola with a full-size natural grass pitch, a glass-walled padel court and a clay tennis court. Popular with local leagues on weekday evenings, so book early for slots after 18:00. Free parking on site.',
            'city' => 'Bitola',
            'address' => 'bul. 1 Maj 45',
            'image_path' => 'https://images.unsplash.com/photo-1459865264687-595d652de67e?auto=format&fit=crop&w=1600&q=80',
            'opening_hour' => 8,
            'closing_hour' => 22,
        ]);
        $bitola->amenities()->syncWithoutDetaching([$parking->id, $showers->id, $lockers->id]);
        Court::firstOrCreate(['facility_id' => $bitola->id, 'name' => 'Main Football Pitch'], ['type' => 'Football', 'base_price_per_hour' => 100000, 'image_path' => 'https://images.unsplash.com/photo-1551958219-acbc608c6377?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $bitola->id, 'name' => 'Padel Court 1'], ['type' => 'Padel', 'base_price_per_hour' => 90000, 'image_path' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0d/Padel_court12.jpg/1280px-Padel_court12.jpg']);
        Court::firstOrCreate(['facility_id' => $bitola->id, 'name' => 'Tennis Court A'], ['type' => 'Tennis', 'base_price_per_hour' => 70000, 'image_path' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=1600&q=80']);

        $ohrid = Facility::firstOrCreate(['name' => 'Ohrid Aquatic Center'], [
            'description' => 'By the lake, with an outdoor 50 m pool open from May to September, a heated training pool all year round and a hard tennis court between the pine trees. Each pool is booked whole, by the hour; goggles and caps can be rented at reception.',
            'city' => 'Ohrid',
            'address' => 'ul. Kej Makedonija 8',
            'image_path' => 'https://images.unsplash.com/photo-1576013551627-0cc20b96c2a7?auto=format&fit=crop&w=1600&q=80',
            'opening_hour' => 7,
            'closing_hour' => 21,
        ]);
        $ohrid->amenities()->syncWithoutDetaching([$parking->id, $showers->id, $lockers->id, $cafe->id]);
        Court::firstOrCreate(['facility_id' => $ohrid->id, 'name' => 'Olympic Pool'], ['type' => 'Swimming', 'base_price_per_hour' => 120000, 'season_start_month' => 5, 'season_end_month' => 9, 'image_path' => 'https://images.unsplash.com/photo-1519315901367-f34ff9154487?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $ohrid->id, 'name' => 'Training Pool'], ['type' => 'Swimming', 'base_price_per_hour' => 80000, 'image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $ohrid->id, 'name' => 'Tennis Court'], ['type' => 'Tennis', 'base_price_per_hour' => 75000, 'image_path' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1600&q=80']);

        $tetovo = Facility::firstOrCreate(['name' => 'Tetovo Sports Complex'], [
            'description' => 'A complex under the Shar Mountains with a covered padel hall, a floodlit artificial-turf football pitch and an indoor tennis court. The padel court is panoramic glass, and the turf was relaid last season. Wifi and showers included.',
            'city' => 'Tetovo',
            'address' => 'ul. Ilindenska 22',
            'image_path' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/1/16/Padel_Court_in_Nsambya.jpg/1280px-Padel_Court_in_Nsambya.jpg',
            'opening_hour' => 8,
            'closing_hour' => 23,
        ]);
        $tetovo->amenities()->syncWithoutDetaching([$wifi->id, $showers->id, $parking->id]);
        Court::firstOrCreate(['facility_id' => $tetovo->id, 'name' => 'Football Pitch'], ['type' => 'Football', 'base_price_per_hour' => 110000, 'image_path' => 'https://images.unsplash.com/photo-1600679472829-3044539ce8ed?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $tetovo->id, 'name' => 'Padel Court'], ['type' => 'Padel', 'base_price_per_hour' => 85000, 'image_path' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0d/Padel_court12.jpg/1280px-Padel_court12.jpg']);
        Court::firstOrCreate(['facility_id' => $tetovo->id, 'name' => 'Indoor Court'], ['type' => 'Tennis', 'base_price_per_hour' => 70000, 'image_path' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=1600&q=80']);

        $prilep = Facility::firstOrCreate(['name' => 'Prilep Professional Arena'], [
            'description' => 'Used by regional clubs for training. A hybrid-grass pitch, a hard tennis court and an outdoor padel court. Coaching sessions can be arranged with the front desk.',
            'city' => 'Prilep',
            'address' => 'ul. Marksova 15',
            'image_path' => 'https://upload.wikimedia.org/wikipedia/commons/4/4b/Royal_Johannesburg_-_Africa_Padel.jpg',
            'opening_hour' => 8,
            'closing_hour' => 22,
        ]);
        $prilep->amenities()->syncWithoutDetaching([$parking->id, $wifi->id, $showers->id, $lockers->id, $cafe->id]);
        Court::firstOrCreate(['facility_id' => $prilep->id, 'name' => 'Professional Pitch'], ['type' => 'Football', 'base_price_per_hour' => 200000, 'image_path' => 'https://images.unsplash.com/photo-1560272564-c83b66b1ad12?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $prilep->id, 'name' => 'Pro Tennis Court'], ['type' => 'Tennis', 'base_price_per_hour' => 100000, 'image_path' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=1600&q=80']);
        Court::firstOrCreate(['facility_id' => $prilep->id, 'name' => 'Padel Arena'], ['type' => 'Padel', 'base_price_per_hour' => 95000, 'image_path' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/1/16/Padel_Court_in_Nsambya.jpg/1280px-Padel_Court_in_Nsambya.jpg']);

        foreach ([$central, $bitola, $ohrid, $tetovo, $prilep] as $facility) {
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Racket'], ['price' => 50000, 'suitable_for' => ['Tennis', 'Padel']]);
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Ball'], ['price' => 30000, 'suitable_for' => ['Football']]);
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Towel'], ['price' => 20000, 'suitable_for' => ['Swimming', 'Tennis', 'Padel', 'Football']]);
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Goggles'], ['price' => 40000, 'suitable_for' => ['Swimming']]);
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Swim Cap'], ['price' => 25000, 'suitable_for' => ['Swimming']]);
            Rental::firstOrCreate(['facility_id' => $facility->id, 'name' => 'Shin Guards'], ['price' => 35000, 'suitable_for' => ['Football']]);
        }

        $facilityManager->managedFacilities()->syncWithoutDetaching([$central->id, $tetovo->id]);

        $reviewers = collect([
            'ana.petrovska@example.com' => 'Ana Petrovska',
            'marko.stojanovski@example.com' => 'Marko Stojanovski',
            'elena.trajkovska@example.com' => 'Elena Trajkovska',
            'arben.shabani@example.com' => 'Arben Shabani',
            'nikola.dimitrov@example.com' => 'Nikola Dimitrov',
        ])->map(function (string $name, string $email) use ($password, $userRole) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
            $user->syncRoles($userRole);
            return $user;
        })->values();
        [$ana, $marko, $elena, $arben, $nikola] = $reviewers->all();

        $reviews = [
            [$customer, $central, 5, 'Played five-a-side here on a Thursday night. The floor is great and the changing rooms were spotless.'],
            [$ana, $central, 5, 'My kids swim here twice a week. Warm pool, friendly lifeguards, and the cafe is a nice place to wait.'],
            [$marko, $central, 4, 'Good tennis court and easy to book. It gets busy after 19:00, so book a few days ahead.'],
            [$customer, $bitola, 4, 'The grass pitch is in really good shape. Parking fills up on league nights.'],
            [$nikola, $bitola, 5, 'First time trying padel and the staff lent us rackets and explained the rules. We are coming back.'],
            [$customer, $ohrid, 5, 'Swimming in the outdoor pool with the lake in the background is hard to beat.'],
            [$elena, $ohrid, 5, 'The training pool is warm even in winter. Lanes are rarely crowded in the morning.'],
            [$marko, $ohrid, 4, 'Lovely setting. The tennis court could use new nets, but the surface is fine.'],
            [$arben, $tetovo, 5, 'Best padel courts in the region, full glass and good lighting. Booking online took a minute.'],
            [$nikola, $tetovo, 4, 'The new turf is great. The showers took a while to warm up.'],
            [$customer, $prilep, 5, 'Feels like a pro club. The pitch is perfect and the staff are very organised.'],
            [$elena, $prilep, 5, 'We booked a padel court for a birthday and they set up a table for us afterwards. Great evening.'],
            [$arben, $prilep, 4, 'Excellent tennis court. A bit pricey at peak hours, but worth it.'],
        ];
        foreach ($reviews as [$user, $facility, $rating, $comment]) {
            $review = Review::firstOrCreate(['user_id' => $user->id, 'facility_id' => $facility->id], ['rating' => $rating, 'comment' => $comment]);
            if (Booking::where('user_id', $user->id)
                ->whereHas('court', fn ($query) => $query->where('facility_id', $facility->id))
                ->where('status', '!=', 'cancelled')
                ->where('end_time', '<', $review->created_at)
                ->exists()) {
                continue;
            }

            // Demo reviews need a played booking, including when repairing previously seeded data.
            $court = $facility->courts()->orderBy('name')->firstOrFail();
            $start = $review->created_at->copy()->subDay()->setTime($facility->opening_hour, 0);
            while (Booking::overlapping($court->id, $start, $start->copy()->addHour())->exists()) {
                $start->subDay();
            }
            $end = $start->copy()->addHour();
            Booking::create([
                'user_id' => $user->id,
                'court_id' => $court->id,
                'start_time' => $start,
                'end_time' => $end,
                'status' => 'completed',
                'total_price' => Booking::calculatePrice($court, $start, $end),
            ]);
        }
    }
}
