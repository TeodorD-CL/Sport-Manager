# Sport Manager Project Documentation

Sport Manager is a sports facility booking platform for courts and sports venues in Macedonia. Users can search facilities, view court availability, book time slots, add equipment rentals, manage their bookings, and leave reviews after visiting a facility. Administrators and facility managers use a Filament admin panel to manage facilities, courts, rentals, bookings, reviews, amenities, and schedules.

The project is built with Laravel 11, Livewire 3, Blade, Tailwind CSS via CDN, Filament v3, Spatie permissions, and SQLite by default.

## Table of Contents

- [Main Features](#main-features)
- [Technology Stack](#technology-stack)
- [Requirements](#requirements)
- [Run with Docker](#run-with-docker)
- [Run Locally](#run-locally)
- [Seeded Login Accounts](#seeded-login-accounts)
- [Application Routes](#application-routes)
- [User Booking Flow](#user-booking-flow)
- [Admin Panel](#admin-panel)
- [Roles and Permissions](#roles-and-permissions)
- [Data Model](#data-model)
- [Pricing Rules](#pricing-rules)
- [Booking Rules](#booking-rules)
- [Review Rules](#review-rules)
- [Project Structure](#project-structure)
- [Testing](#testing)
- [Deployment Notes](#deployment-notes)
- [Troubleshooting](#troubleshooting)
- [Contributor Notes](#contributor-notes)

## Main Features

### Public and user-facing features

- Search sports facilities by city, sport type, date, amenities, rating, review count, and price.
- View each facility with its description, location, amenities, courts, prices, reviews, and available hourly slots.
- Book courts in one-hour increments based on each facility's opening and closing hours.
- Prevent past bookings and duplicate bookings for the same court and time slot.
- Add suitable equipment rentals during booking.
- Calculate total price before booking confirmation.
- Generate a QR code identifier for each booking.
- View upcoming and past bookings from the user dashboard.
- Cancel bookings more than 24 hours before the start time.
- Leave one review per facility after making a non-cancelled booking there.

### Admin features

- Filament admin dashboard at `/admin`.
- Manage facilities, courts, bookings, reviews, rentals, and amenities.
- Set facility opening and closing hours.
- Assign facility managers to specific facilities.
- Restrict facility managers to only the facilities they manage.
- Review user feedback from the admin panel.
- Use calendar-style booking views through the Filament FullCalendar plugin.

## Technology Stack

| Area | Technology |
| --- | --- |
| Backend framework | Laravel 11 |
| PHP version | PHP 8.2 |
| Frontend | Livewire 3, Blade, Tailwind CSS CDN |
| Admin panel | Filament v3 |
| Authorization | Spatie Laravel Permission, Filament Shield |
| Media handling | Spatie Laravel MediaLibrary |
| Calendar | Saade Filament FullCalendar |
| QR codes | SimpleSoftwareIO Simple QRCode |
| Default database | SQLite |
| Test database | In-memory SQLite |
| Queue, cache, session defaults | Database drivers |

Tailwind CSS is loaded through CDN in the main Blade layout, so the public frontend does not require a Vite build step.

## Requirements

For local development without Docker:

- PHP 8.2 or newer
- Composer 2.x
- SQLite support for PHP
- Git

Recommended PHP extensions:

- `sqlite3`
- `mbstring`
- `xml`
- `curl`
- `fileinfo`
- `gd`

Node.js is only needed if you plan to work on compiled frontend assets. The main public UI uses Tailwind through CDN.

## Run with Docker

Docker is the recommended way to run the project because the container bootstraps most of the environment automatically.

```bash
docker-compose up --build
```

The Docker entrypoint:

- Creates `.env` from `.env.example` if needed.
- Generates `APP_KEY` if missing.
- Creates the SQLite database file.
- Runs migrations on container start.

After the app is running, seed sample data:

```bash
docker-compose exec app php artisan db:seed
```

Open the app:

- Frontend: `http://localhost:8000`
- Admin panel: `http://localhost:8000/admin`

Run tests in Docker:

```bash
docker-compose exec app php artisan test
```

## Run Locally

Install dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Create the SQLite database file:

```bash
touch database/database.sqlite
```

Run migrations and seed sample data:

```bash
php artisan migrate --seed
```

Start the app:

```bash
php artisan serve
```

Open:

```text
http://localhost:8000
```

The project also includes a Composer dev script:

```bash
composer run dev
```

That script starts the Laravel server, queue listener, logs, and Vite process together. For the public-facing Blade and Livewire UI, `php artisan serve` is usually enough.

## Seeded Login Accounts

After running the database seeder, these accounts are available:

| Role | Email | Password | Access |
| --- | --- | --- | --- |
| `super_admin` | `admin@test.com` | `password` | Full admin access |
| `facility_manager` | `manager@test.com` | `password` | Admin access for assigned facilities |
| `user` | `user@test.com` | `password` | Frontend booking access |

The seeded facility manager is assigned to Central Sports Hall and Tetovo Sports Complex.

Newly registered users receive the `user` role. They can book courts on the frontend but cannot access the admin panel.

## Application Routes

| URL | Handler | Authentication |
| --- | --- | --- |
| `/` | `SearchFacilities` Livewire component | Public |
| `/facility/{id}` | `FacilityDetail` Livewire component | Public |
| `/login` | `AuthController@showLogin` | Public |
| `POST /login` | `AuthController@login` | Public, throttled |
| `/register` | `AuthController@showRegister` | Public |
| `POST /register` | `AuthController@register` | Public |
| `POST /logout` | `AuthController@logout` | Authenticated |
| `/dashboard` | `UserDashboard` Livewire component | Authenticated |
| `/profile` | `UserProfile` Livewire component | Authenticated |
| `/admin` | Filament admin panel | Admin role required |

Login attempts are throttled at 6 attempts per minute.

## User Booking Flow

1. The user opens the homepage at `/`.
2. The user searches or filters facilities by city, court type, date, amenities, rating, reviews, or price.
3. The user opens a facility page.
4. The facility page shows courts and one-hour availability slots based on the facility schedule.
5. Past slots are shown as unavailable.
6. The user selects an available slot.
7. If the user is not logged in, booking confirmation redirects to login.
8. The user can select suitable rentals for the selected sport type.
9. The app calculates the total price.
10. The user confirms the booking.
11. The booking is created with status `confirmed` and a UUID QR code value.
12. The user is redirected to the dashboard.

The booking confirmation runs inside a database transaction and checks the selected slot with a lock to reduce the risk of double booking.

## Admin Panel

The admin panel is available at:

```text
/admin
```

It uses Filament v3 with Amber as the primary color.

Admin resources live in:

```text
app/Filament/Resources
```

Main resources include:

- Amenity
- Booking
- Court
- Facility
- Rental
- Review

Facility assignments are managed from the Facility resource through the Facility Managers relation tab. Only super admins can assign or remove facility managers.

## Roles and Permissions

### `super_admin`

Super admins have full administrative access. They can:

- Access `/admin`.
- Create and manage all facilities.
- Manage courts, bookings, rentals, amenities, and reviews.
- Assign facility managers to facilities.
- Manage global admin data.

### `facility_manager`

Facility managers can access `/admin` only if they are assigned to at least one facility.

They can:

- View and manage only their assigned facilities.
- Manage courts for their assigned facilities.
- Manage bookings connected to their assigned facilities.
- Manage rentals for their assigned facilities.
- Update facility schedule fields such as `opening_hour` and `closing_hour`.
- View reviews for their assigned facilities.

They cannot:

- Manage global amenities.
- Create new facilities.
- Assign facility managers.
- Edit or delete reviews.
- Access facilities they do not manage.

### `user`

Regular users can:

- Search facilities.
- Book courts.
- View their dashboard.
- Cancel eligible bookings.
- Leave reviews after a valid booking.

Regular users cannot access `/admin`.

## Data Model

Most application models use UUID primary keys. The `User` model uses the standard bigint primary key.

### User

Represents a registered account.

Relationships:

- Has many bookings.
- Has many reviews.
- Belongs to many managed facilities for facility managers.
- Uses Spatie roles.

### Facility

Represents a sports venue.

Important fields:

- `name`
- `description`
- `city`
- `address`
- `image_path`
- `opening_hour`
- `closing_hour`

Relationships:

- Has many courts.
- Has many rentals.
- Has many reviews.
- Belongs to many amenities.
- Belongs to many managers through `facility_user`.

Computed values:

- Average rating.
- Review count.

### Court

Represents a bookable sports court or area inside a facility.

Important fields:

- `facility_id`
- `name`
- `type`
- `base_price_per_hour`
- `image_path`

Supported court types:

- Football
- Tennis
- Swimming
- Padel

### Booking

Represents a user reservation for a court.

Important fields:

- `user_id`
- `court_id`
- `start_time`
- `end_time`
- `status`
- `total_price`
- `qr_code`

Statuses:

- `confirmed`
- `cancelled`
- `completed`
- `checked_in`

Relationships:

- Belongs to a user.
- Belongs to a court.
- Belongs to many rentals with a pivot quantity.

### Rental

Represents rentable equipment such as rackets, balls, towels, goggles, or shin guards.

Important fields:

- `facility_id`
- `name`
- `price`
- `suitable_for`

The `suitable_for` field is a JSON array of court types. If it is empty or null, the rental is treated as suitable for all court types.

### Amenity

Represents a facility feature such as parking, wifi, showers, lockers, or cafe.

Important fields:

- `name`
- `icon`

### Review

Represents a user review for a facility.

Important fields:

- `user_id`
- `facility_id`
- `rating`
- `comment`

Rules:

- Rating must be an integer from 1 to 5.
- Comment is optional and limited to 1000 characters.
- A database unique constraint prevents duplicate reviews by the same user for the same facility.

## Pricing Rules

Booking prices are calculated by `Booking::calculatePrice()`.

The total is based on:

- Court base price per hour.
- Number of booked hours.
- Peak-hour surcharge.
- Weekend surcharge.
- Selected rentals.

Rules:

- The minimum charged duration is 1 hour.
- `end_time` must be after `start_time`.
- Peak hours are 18:00 through 21:59.
- Peak bookings receive a 20 percent surcharge.
- Weekend bookings receive a 10 percent surcharge.
- Surcharges stack. Peak is applied first, then weekend.
- Rental prices are added after court pricing.

Prices are stored as integers in Macedonian denar minor units. Displayed prices are formatted by dividing by 100 and appending `MKD`.

Example:

```text
120000 = 1,200 MKD
```

## Booking Rules

- Slots are generated in one-hour increments.
- Facility schedules define the first and last bookable hours.
- Default schedule values are typically 08:00 to 22:00.
- Past slots are unavailable.
- Users cannot confirm bookings in the past.
- Cancelled bookings no longer block availability.
- Non-cancelled bookings block overlapping slots.
- A user can cancel only if the booking starts more than 24 hours in the future.
- Duplicate slot booking is blocked during confirmation.

## Review Rules

Users can review a facility only when:

- They are authenticated.
- They have at least one non-cancelled booking at that facility.
- They have not already reviewed that facility.

After a review is submitted:

- The review is saved.
- A `reviewSubmitted` Livewire event is dispatched.
- The facility detail page reloads reviews live.

## Project Structure

```text
app/
  Http/Controllers/AuthController.php
  Livewire/
    SearchFacilities.php
    FacilityDetail.php
    UserDashboard.php
    UserProfile.php
    LeaveReview.php
  Models/
    Amenity.php
    Booking.php
    Court.php
    Facility.php
    Rental.php
    Review.php
    User.php
  Filament/Resources/
  Providers/Filament/AdminPanelProvider.php

resources/
  views/components/layouts/app.blade.php
  views/livewire/
  views/auth/

database/
  migrations/
  seeders/DatabaseSeeder.php

routes/
  web.php

tests/
  Feature/
  Unit/

docker/
  entrypoint.sh

Dockerfile
docker-compose.yml
composer.json
phpunit.xml
```

The canonical app layout is:

```text
resources/views/components/layouts/app.blade.php
```

Livewire components reference it with:

```php
#[Layout('components.layouts.app')]
```

Auth pages use it as:

```blade
<x-layouts.app>
```

Do not create a duplicate layout at `resources/views/layouts/app.blade.php`.

## Testing

Run all tests locally:

```bash
php artisan test
```

Run all tests in Docker:

```bash
docker-compose exec app php artisan test
```

The test suite uses in-memory SQLite through `phpunit.xml`, so tests do not touch the local development database.

Important test coverage:

- Booking price calculations.
- Minimum one-hour pricing.
- Peak and weekend surcharges.
- Booking cancellation window.
- Rental pricing and quantities.
- Duplicate slot prevention.
- Past slot rejection.
- Unauthenticated booking redirects.
- Admin panel access rules.
- Facility manager scoping.
- Review and admin restrictions.
- Facility schedule slot generation.

## Deployment Notes

The existing README includes Render deployment notes. The project is prepared for a Docker-based Render deployment with a web service, queue worker, and PostgreSQL database.

General production reminders:

- Use a real database such as PostgreSQL or MySQL instead of SQLite.
- Set the same `APP_KEY` for the web and worker services.
- Keep `APP_DEBUG=false`.
- Configure `APP_URL` to the deployed domain.
- Run migrations during deployment.
- Seed demo data only when intentionally creating a demo environment.
- Use a persistent storage strategy for uploaded files if media uploads are enabled.

## Troubleshooting

### The app says the application key is missing

Run:

```bash
php artisan key:generate
```

### SQLite database file does not exist

Run:

```bash
touch database/database.sqlite
php artisan migrate --seed
```

### Admin login works but `/admin` is forbidden

Make sure the user has one of these valid admin roles:

- `super_admin`
- `facility_manager` with at least one assigned facility

Regular users and unassigned facility managers cannot access the admin panel.

### A facility manager cannot see a facility

Check that the manager is assigned to the facility through the Facility Managers relation in the Facility admin resource.

### Slots are unavailable

Check:

- The selected date is not in the past.
- The facility opening and closing hours are valid.
- The court does not already have a non-cancelled booking at that time.

### Public frontend styling is missing

The public frontend uses Tailwind CSS from CDN in the Blade layout. Check the canonical layout:

```text
resources/views/components/layouts/app.blade.php
```

### Tests fail because of a dirty local database

The automated tests should use in-memory SQLite. Confirm that `phpunit.xml` contains:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Contributor Notes

- Keep frontend Blade components using the canonical app layout.
- Prefer Livewire and existing Laravel patterns already used in the project.
- Keep facility manager queries scoped to assigned facilities.
- Use database transactions for booking confirmation logic.
- Keep review rules enforced in both application logic and the database.
- Do not eager-load the full reviews collection on the homepage just to show ratings. Use aggregate values such as `withAvg` and `withCount`.
- Use `array_key_exists` when checking aggregate attributes that may intentionally be null.
- Add tests for booking, pricing, authorization, or review behavior when changing those areas.

