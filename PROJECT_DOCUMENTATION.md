# Sport Manager Technical Documentation

## 1. Project Overview

Sport Manager is a Laravel-based web application for booking sports facilities in North Macedonia. The application lets visitors browse sports venues, filter by city and sport type, check hourly court availability, create bookings, add optional equipment rentals, manage their reservations, and leave reviews after they have used a facility.

The project also includes an administration panel for platform administrators and facility managers. Administrators can manage the whole system, while facility managers are restricted to the facilities assigned to them.

The application is built as a traditional Laravel application with server-rendered Blade views and interactive Livewire components. There is no separate JavaScript SPA frontend. The public interface is mostly Livewire 3, Blade, Tailwind CSS through CDN, and a small amount of Alpine.js-style behavior inside Blade templates.

## 2. Main User Roles

The application has three practical user types:

| Role | Purpose | Access |
| --- | --- | --- |
| `super_admin` | Platform administrator | Full access to the Filament admin panel and all resources |
| `facility_manager` | Manager of one or more assigned facilities | Admin panel access only after at least one facility is assigned; scoped to assigned facilities |
| `user` | Normal customer | Public browsing, booking, profile, dashboard, cancellation, and reviews |

There is also a `panel_user` role created by the seeder for compatibility with Filament Shield conventions, but new self-registered customers are assigned only the `user` role.

## 3. Technology Stack

| Layer | Technology |
| --- | --- |
| Backend framework | Laravel 11 |
| PHP runtime | PHP 8.2 |
| Frontend rendering | Blade templates |
| Frontend interactivity | Livewire 3 |
| Styling | Tailwind CSS CDN in the main layout |
| Admin panel | Filament v3 |
| Admin permissions | Spatie Laravel Permission and Filament Shield |
| Calendar plugin | Saade Filament FullCalendar |
| Media package | Spatie Laravel MediaLibrary |
| QR codes | SimpleSoftwareIO Simple QRCode |
| AI assistant integration | Gemini API, optional through `GEMINI_API_KEY` |
| Local/Docker database in current config | PostgreSQL |
| Test database | In-memory SQLite |
| Queue/cache/session defaults | Database drivers in `.env.example`, overridden to array/sync during tests |

Important note: older project notes mention SQLite as the default database. In the current checked-in configuration, `.env.example` and `docker-compose.yml` use PostgreSQL. SQLite is still supported by the code and is used for automated tests through `phpunit.xml`.

## 4. Repository Structure

The important folders and files are:

```text
app/
  Filament/Resources/                 Admin CRUD resources
  Http/Controllers/AuthController.php  Custom login, register, logout controller
  Http/Middleware/EnsureUserIsAdmin.php
  Livewire/                           Public interactive components
  Models/                             Eloquent models
  Policies/RolePolicy.php             Spatie Role policy used by admin permissions
  Providers/Filament/AdminPanelProvider.php

bootstrap/
  app.php                             Laravel 11 app bootstrap

config/
  app.php
  auth.php                            Web and admin session guards
  database.php
  services.php                        Includes Gemini API key configuration

database/
  migrations/                         Database schema
  seeders/DatabaseSeeder.php          Demo users, roles, facilities, courts, rentals, reviews

docker/
  entrypoint.sh                       Container bootstrap script

public/
  images/                             Static facility/court images
  css/ and js/filament/               Published Filament assets

resources/
  views/components/layouts/app.blade.php
  views/livewire/                     Livewire component views
  views/auth/                         Login and registration pages

routes/
  web.php                             Public and authenticated web routes

tests/
  Feature/                            Booking, review, auth, admin, chatbot tests
  Unit/BookingPriceTest.php           Pricing unit tests
```

## 5. Application Routing

Routes are defined in `routes/web.php`.

| Method | URL | Handler | Access |
| --- | --- | --- | --- |
| GET | `/` | `App\Livewire\SearchFacilities` | Public |
| GET | `/facility/{id}` | `App\Livewire\FacilityDetail` | Public |
| GET | `/login` | `AuthController@showLogin` | Public |
| POST | `/login` | `AuthController@login` | Public, throttled to 6 attempts per minute |
| GET | `/register` | `AuthController@showRegister` | Public |
| POST | `/register` | `AuthController@register` | Public |
| POST | `/logout` | `AuthController@logout` | Authenticated form action |
| GET | `/dashboard` | `App\Livewire\UserDashboard` | Authenticated |
| GET | `/profile` | `App\Livewire\UserProfile` | Authenticated |
| GET | `/admin` | Filament admin panel | Admin guard and role checks |

The Filament admin panel route is configured in `app/Providers/Filament/AdminPanelProvider.php` with `path('admin')`.

## 6. Authentication and Authorization

### Public Authentication

Authentication is handled by `app/Http/Controllers/AuthController.php`.

Login flow:

1. User submits email, password, and optional remember flag to `POST /login`.
2. The controller validates email and password.
3. `Auth::attempt()` checks credentials.
4. On success the session is regenerated and the user is redirected to the intended page or `/`.
5. On failure the form returns with a validation error.

Registration flow:

1. User submits name, email, optional phone, password, and password confirmation.
2. The controller validates uniqueness of email and minimum password length.
3. The user is created.
4. The `user` role is created if missing and assigned to the user.
5. The user is logged in immediately and redirected to `/`.

Logout flow:

1. The user submits a POST form to `/logout`.
2. Laravel logs the user out.
3. The session is invalidated and CSRF token is regenerated.
4. The user is redirected to `/`.

### Admin Guard

`config/auth.php` defines two session guards:

- `web`
- `admin`

Both use the same `users` provider and `App\Models\User` model. Filament is configured to use the `admin` guard.

### Admin Access Rules

Admin panel access is decided by `User::canAccessPanel()` and `User::hasAdminPanelAccess()`.

A user can access `/admin` when:

- they have the `super_admin` role, or
- they have the `facility_manager` role and are assigned to at least one facility.

Regular users and unassigned facility managers cannot use the admin panel.

The middleware `EnsureUserIsAdmin` adds a second check for authenticated admin-guard users. If an authenticated admin user lacks admin panel access, the middleware aborts with HTTP 403.

## 7. Data Model

Most business models use UUID primary keys through Laravel's `HasUuids` trait. The exception is `User`, which uses Laravel's default auto-incrementing integer ID.

### Users

Model: `App\Models\User`

Main fields:

- `id`
- `name`
- `email`
- `phone`
- `password`
- `email_verified_at`
- timestamps

Relationships:

- `bookings()` has many bookings
- `reviews()` has many reviews
- `managedFacilities()` belongs to many facilities through `facility_user`
- `facilities()` aliases `managedFacilities()` for Filament relation manager compatibility

Important methods:

- `isSuperAdmin()`
- `isFacilityManager()`
- `hasAdminPanelAccess()`
- `managedFacilityIds()`
- `managesFacility(string $facilityId)`
- `canAccessPanel(Panel $panel)`

### Facilities

Model: `App\Models\Facility`

Main fields:

- `id` UUID
- `name`
- `description`
- `city`
- `address`
- `image_path`
- `opening_hour`
- `closing_hour`
- timestamps

Relationships:

- `courts()` has many courts
- `amenities()` belongs to many amenities
- `reviews()` has many reviews
- `rentals()` has many rentals
- `managers()` belongs to many users through `facility_user`

Computed attributes:

- `averageRating`
- `reviewCount`
- `lowestPriceFormatted`

`averageRating` and `reviewCount` first use aggregate values loaded by `withAvg()` and `withCount()` when available. This avoids loading every review on the homepage. If aggregate attributes are missing, the model falls back to the loaded reviews collection.

### Courts

Model: `App\Models\Court`

Main fields:

- `id` UUID
- `facility_id`
- `name`
- `type`
- `base_price_per_hour`
- `image_path`
- timestamps

Supported court types in forms and UI:

- `Football`
- `Tennis`
- `Padel`
- `Swimming`

Relationships:

- `facility()` belongs to facility
- `bookings()` has many bookings

Prices are stored as integer cents/minor units. For example, `120000` is displayed as `1,200 MKD`.

### Bookings

Model: `App\Models\Booking`

Main fields:

- `id` UUID
- `user_id`
- `court_id`
- `start_time`
- `end_time`
- `status`
- `total_price`
- `qr_code`
- timestamps

Statuses used by the system:

- `confirmed`
- `cancelled`
- `completed`
- `checked_in`

Relationships:

- `user()` belongs to user
- `court()` belongs to court
- `rentals()` belongs to many rentals with pivot field `quantity`

Database constraints:

- `qr_code` is unique when present.
- `court_id`, `start_time`, and `end_time` are unique together.

The unique booking constraint protects identical slots. The application also checks for overlapping bookings in code so that overlapping time windows are blocked, not only identical start/end combinations.

### Rentals

Model: `App\Models\Rental`

Main fields:

- `id` UUID
- `facility_id`, nullable for legacy/global rental records
- `name`
- `price`
- `suitable_for`, JSON array
- timestamps

Relationships:

- `facility()` belongs to facility
- `bookings()` belongs to many bookings with pivot `quantity`

`Rental::isSuitableFor(string $courtType)` returns true when:

- `suitable_for` is empty or null, meaning the rental is universal, or
- the given court type exists in `suitable_for`.

### Amenities

Model: `App\Models\Amenity`

Main fields:

- `id` UUID
- `name`
- `icon`
- timestamps

Relationships:

- `facilities()` belongs to many facilities through `amenity_facility`

The `icon` field stores a Heroicon name such as `heroicon-o-wifi`.

### Reviews

Model: `App\Models\Review`

Main fields:

- `id` UUID
- `user_id`
- `facility_id`
- `rating`
- `comment`
- timestamps

Relationships:

- `user()` belongs to user
- `facility()` belongs to facility

Database rule:

- A user can have only one review per facility because `reviews` has a unique constraint on `user_id` and `facility_id`.

## 8. Database Tables and Relationships

The main business tables are:

| Table | Purpose |
| --- | --- |
| `users` | Application users |
| `facilities` | Sports venues |
| `courts` | Bookable courts/pools/pitches inside facilities |
| `bookings` | User reservations |
| `rentals` | Equipment or extras |
| `booking_rental` | Rental selections for a booking, including quantity |
| `amenities` | Facility features like parking, Wi-Fi, lockers |
| `amenity_facility` | Many-to-many connection between amenities and facilities |
| `reviews` | User reviews for facilities |
| `facility_user` | Facility manager assignments |
| `roles`, `permissions`, etc. | Spatie permission tables |
| `media` | Spatie MediaLibrary table |
| `jobs`, `cache`, `sessions` | Laravel infrastructure tables |

High-level relationship diagram:

```text
User
  has many Booking
  has many Review
  belongs to many Facility as managedFacilities

Facility
  has many Court
  has many Rental
  has many Review
  belongs to many Amenity
  belongs to many User as managers

Court
  belongs to Facility
  has many Booking

Booking
  belongs to User
  belongs to Court
  belongs to many Rental with quantity

Rental
  belongs to Facility, nullable
  belongs to many Booking with quantity

Review
  belongs to User
  belongs to Facility
```

## 9. Pricing Logic

Pricing is implemented in `Booking::calculatePrice(Court $court, $startTime, $endTime, array $rentals = [])`.

The algorithm:

1. Parse start and end times with Carbon.
2. Reject invalid ranges where `end_time <= start_time`.
3. Calculate the number of hours using `diffInHours`.
4. Enforce a minimum of one charged hour.
5. Multiply court base price by the number of hours.
6. Apply a 20% peak-hour surcharge if the start hour is from 18:00 through 21:59.
7. Apply a 10% weekend surcharge if the start date is Saturday or Sunday.
8. Load selected rentals in one `whereIn` query.
9. Add `rental.price * quantity` for each valid rental.
10. Return the rounded integer total.

Surcharges stack in this order:

```text
base price -> peak surcharge -> weekend surcharge -> rentals
```

Example:

```text
Court price: 100 MKD/hour
Time: Saturday 18:00-19:00
Base: 100
Peak +20%: 120
Weekend +10%: 132
Final: 132 MKD, before rentals
```

Internally this would be stored as `13200`.

## 10. Booking Rules

The main booking flow lives in `App\Livewire\FacilityDetail`.

Rules enforced by the UI and backend:

- Slots are one hour long.
- Slots are generated between each facility's `opening_hour` and `closing_hour`.
- Default schedule is 08:00-22:00.
- Past slots are displayed as unavailable.
- A user cannot confirm a booking in the past.
- A booking must be inside the facility schedule.
- Cancelled bookings no longer block availability.
- Non-cancelled bookings block overlapping slots.
- Booking confirmation requires authentication.
- Confirmed bookings get a generated UUID string in `qr_code`.
- Optional rentals can be attached during booking.

Double-booking protection:

1. `confirmBooking()` wraps the creation in `DB::transaction()`.
2. Inside the transaction, it queries existing non-cancelled bookings for the selected court.
3. It checks overlap using:

```text
existing.start_time < requested.end_time
existing.end_time > requested.start_time
```

4. The query uses `lockForUpdate()`.
5. If an overlap exists, the booking is rejected and the UI reloads availability.

On PostgreSQL/MySQL this gives row-level locking behavior. In SQLite tests, the transaction serializes writes enough for the tested behavior.

## 11. Cancellation Rules

Cancellation is implemented in `App\Livewire\UserDashboard::cancelBooking()`.

A booking can be cancelled only when:

- the booking belongs to the authenticated user,
- the booking status is `confirmed`, and
- the start time is more than 24 hours in the future.

If the booking is inside the 24-hour window, it remains confirmed and the user sees an error message.

Cancelled bookings appear under the dashboard's past/history tab because they are no longer active upcoming reservations.

## 12. Review Rules

Reviews are handled by `App\Livewire\LeaveReview`.

Rules:

- The visitor must be logged in.
- Rating is required and must be an integer from 1 to 5.
- Comment is optional and limited to 1000 characters.
- The user must have at least one non-cancelled booking at the facility.
- The user can submit only one review per facility.

Duplicate prevention happens in two places:

- The Livewire component checks for an existing review before creating a new one.
- The database enforces uniqueness on `user_id` and `facility_id`.

After a review is saved, the component dispatches `reviewSubmitted`. `FacilityDetail` listens for that event and reloads the facility's `reviews.user` relationship so the new review appears without a full page refresh.

## 13. Public Livewire Components

### SearchFacilities

File: `app/Livewire/SearchFacilities.php`

URL: `/`

Purpose:

- Display the homepage facility search.
- Filter by city, sport type, date, and amenities.
- Sort by name, rating, review count, and price.
- Paginate results.

Important implementation details:

- Uses Livewire URL-bound properties for filters:
  - `city`
  - `type`
  - `date`
  - `amenities`
  - `sort`
- Uses `withAvg('reviews', 'rating')` and `withCount('reviews')` instead of eager loading all reviews.
- Date filtering checks whether each facility has courts with fewer than a full day of non-cancelled bookings.
- Amenity filters are cumulative; selecting multiple amenities requires facilities to have all selected amenities.
- Price sorting uses subqueries over `courts.base_price_per_hour`.

### FacilityDetail

File: `app/Livewire/FacilityDetail.php`

URL: `/facility/{id}`

Purpose:

- Show one facility's details.
- Show amenities, gallery images, reviews, and schedule.
- Generate availability slots for each court.
- Let authenticated users create bookings.
- Let eligible authenticated users leave reviews.

Important implementation details:

- Loads `courts`, `amenities`, and `reviews.user` on mount.
- Defaults selected date to today.
- Loads all bookings for the day in one query and groups them by court.
- Generates slots based on facility opening and closing hours.
- Uses `firstWhere()` on the already-loaded courts collection instead of querying the selected court again.
- Loads rentals for the facility, plus legacy/global rentals where `facility_id` is null.
- Filters rentals by court type using `Rental::isSuitableFor()`.
- Calculates the total whenever rentals are toggled.
- Uses a transaction and row lock when confirming the booking.

### UserDashboard

File: `app/Livewire/UserDashboard.php`

URL: `/dashboard`

Purpose:

- Show the authenticated user's bookings.
- Separate upcoming bookings from past/cancelled bookings.
- Render QR codes for booking check-in/reference.
- Allow cancellation when the booking is more than 24 hours away.

Important implementation details:

- Loads bookings with `court.facility`.
- Upcoming bookings are non-cancelled and start in the future.
- Past/history bookings include bookings whose start time has passed or whose status is cancelled.
- QR codes are rendered in the Blade view with Simple QRCode using the booking's `qr_code` field.

### UserProfile

File: `app/Livewire/UserProfile.php`

URL: `/profile`

Purpose:

- Let the user edit name, email, and phone.
- Let the user change password.
- Show simple account statistics.

Statistics shown:

- total bookings
- upcoming bookings
- review count
- favourite sport based on the user's non-cancelled bookings grouped by court type

Password changes require the current password and confirmation of the new password.

### LeaveReview

File: `app/Livewire/LeaveReview.php`

Purpose:

- Embedded inside a facility detail page.
- Validates and saves a review.
- Prevents duplicate reviews.
- Enforces that the user has a non-cancelled booking at the facility.

### Chatbot

Files:

- `app/Livewire/Chatbot.php`
- `resources/views/livewire/chatbot.blade.php`

Purpose:

- Provide a floating assistant named Sporty on public pages.
- Answer basic questions in demo mode without an API key.
- When a Gemini API key is configured, use AI responses with function-style calls to check availability and prepare bookings.

Behavior:

- The component is included globally in `resources/views/components/layouts/app.blade.php`.
- It starts closed and can be toggled from a floating button.
- It keeps a message list in Livewire state.
- It supports preset suggestions for common questions.
- It can create a pending booking proposal.
- A logged-in user can confirm a pending booking directly from the chat.

Gemini integration:

- API key is read from `config('services.gemini.key')`, backed by `GEMINI_API_KEY`.
- The request is sent to Google's Gemini generate content endpoint.
- The system prompt includes current facilities, courts, rentals, user context, pricing rules, cancellation rules, and current local time.
- The component exposes two internal tool-like functions to the AI flow:
  - `checkAvailability`
  - `prepareBooking`

Demo mode:

- If no API key exists, the component returns local canned/database-backed responses for keywords such as Padel, Tennis, Football, Swimming, and cancellation rules.

## 14. Blade Layout and Frontend

The canonical application layout is:

```text
resources/views/components/layouts/app.blade.php
```

Livewire components reference it with:

```php
#[Layout('components.layouts.app')]
```

Auth views use it as:

```blade
<x-layouts.app>
```

The layout provides:

- HTML document shell
- responsive viewport metadata
- CSRF metadata
- Inter font from Google Fonts
- Tailwind CSS from CDN
- Flatpickr CSS and JavaScript from CDN
- global navigation
- Livewire styles/scripts
- global chatbot component

The public frontend does not require a Vite build because Tailwind is loaded through CDN. Vite and Node packages are still present for asset workflows and Laravel defaults, but the primary public UI can run through PHP/Laravel alone.

## 15. Admin Panel

The admin panel is configured in `app/Providers/Filament/AdminPanelProvider.php`.

Configuration highlights:

- route path: `/admin`
- panel ID: `admin`
- auth guard: `admin`
- login enabled
- primary color: Amber
- resources discovered from `app/Filament/Resources`
- dashboard pages/widgets enabled
- custom "back to site" render hook
- middleware includes `EnsureUserIsAdmin`
- plugins:
  - Filament Shield
  - Filament FullCalendar

### Admin Resources

| Resource | Model | Super Admin | Facility Manager |
| --- | --- | --- | --- |
| AmenityResource | Amenity | Full CRUD | No access |
| FacilityResource | Facility | Full CRUD | View/edit/delete assigned facilities only; cannot create new facilities |
| CourtResource | Court | Full CRUD | Manage courts in assigned facilities |
| BookingResource | Booking | Full CRUD | Manage bookings for courts in assigned facilities |
| RentalResource | Rental | Full CRUD | Manage rentals for assigned facilities |
| ReviewResource | Review | Full CRUD | Can view assigned facility reviews, but cannot create/edit/delete |

Each scoped resource uses two layers of protection:

1. `getEloquentQuery()` scopes what records appear in lists.
2. `canCreate()`, `canEdit()`, and `canDelete()` enforce permissions on actions.

Some create/edit page classes also validate submitted `facility_id` or `court_id` before saving. This protects against a manager manually submitting an ID outside their assigned facilities.

### Facility Manager Assignment

Facility manager assignment is handled by:

```text
app/Filament/Resources/FacilityResource/RelationManagers/ManagersRelationManager.php
```

Only super admins can see the "Facility Managers" relation tab. The attach action only offers users who have the `facility_manager` role.

## 16. Seed Data

The main seeder is `database/seeders/DatabaseSeeder.php`.

Seeded users:

| Role | Email | Password | Notes |
| --- | --- | --- | --- |
| `super_admin` | `admin@test.com` | `password` | Full admin access |
| `facility_manager` | `manager@test.com` | `password` | Assigned to Central Sports Hall and Tetovo Sports Complex |
| `user` | `user@test.com` | `password` | Normal customer account |

Seeded roles:

- `super_admin`
- `facility_manager`
- `panel_user`
- `user`

Seeded amenities:

- Parking
- Wifi
- Showers
- Lockers
- Cafe

Seeded facilities:

- Central Sports Hall, Skopje
- Bitola Sports Arena, Bitola
- Ohrid Aquatic Center, Ohrid
- Tetovo Sports Complex, Tetovo
- Prilep Professional Arena, Prilep

Each seeded facility has multiple courts and six rentals:

- Racket
- Ball
- Towel
- Goggles
- Swim Cap
- Shin Guards

The seeder also creates example reviews from the normal test user.

## 17. Environment Configuration

Important `.env` values:

| Variable | Purpose |
| --- | --- |
| `APP_NAME` | Application display/config name |
| `APP_ENV` | Environment name |
| `APP_KEY` | Laravel encryption key |
| `APP_DEBUG` | Enables debug output in development |
| `APP_TIMEZONE` | Should be `Europe/Skopje` for correct local slot handling |
| `APP_URL` | Base URL |
| `DB_CONNECTION` | Current sample config uses `pgsql` |
| `DB_HOST` | Database host |
| `DB_PORT` | Database port |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `SESSION_DRIVER` | Current sample config uses `database` |
| `QUEUE_CONNECTION` | Current sample config uses `database` |
| `CACHE_STORE` | Current sample config uses `database` |
| `RUN_MIGRATIONS` | Docker entrypoint flag; defaults to true |
| `RUN_SEED` | Docker entrypoint flag; defaults to false |
| `GEMINI_API_KEY` | Optional API key for the AI chatbot |

Timezone is especially important. If the app runs in UTC while users expect Europe/Skopje time, the system can misclassify local slots as past or future.

## 18. Running the Project with Docker

Docker is the easiest way to run the current project because it provides PHP and PostgreSQL.

Start the application:

```bash
docker-compose up --build
```

Open:

```text
http://localhost:8000
```

Admin panel:

```text
http://localhost:8000/admin
```

Seed data after the app is running:

```bash
docker-compose exec app php artisan db:seed
```

Run tests:

```bash
docker-compose exec app php artisan test
```

### Docker Services

`docker-compose.yml` defines:

| Service | Purpose |
| --- | --- |
| `app` | Laravel web app on port 8000 |
| `queue` | Laravel queue worker |
| `db` | PostgreSQL 15 Alpine |

Volumes:

- `vendor` keeps Composer dependencies from being overwritten by the host bind mount.
- `pgdata` persists PostgreSQL data.

### Docker Entrypoint

`docker/entrypoint.sh` does the following:

1. Copies `.env.example` to `.env` if `.env` does not exist.
2. Writes selected container environment variables into `.env`.
3. Generates `APP_KEY` if missing.
4. Creates SQLite database file only if `DB_CONNECTION=sqlite`.
5. Sets storage/cache permissions.
6. Runs migrations when `RUN_MIGRATIONS` is truthy.
7. Runs seeders when `RUN_SEED` is truthy.
8. Publishes Filament assets.
9. Starts the requested command.

## 19. Running Locally Without Docker

Local setup requires PHP 8.2, Composer, and a database.

Install dependencies:

```bash
composer install
```

Create environment:

```bash
cp .env.example .env
php artisan key:generate
```

Configure database settings in `.env`.

For PostgreSQL, make sure these match your local database:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sport_manager
DB_USERNAME=postgres
DB_PASSWORD=secret
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the Laravel server:

```bash
php artisan serve
```

Open:

```text
http://localhost:8000
```

The project also has a Composer development script:

```bash
composer run dev
```

That script starts the Laravel server, queue listener, log tailing, and Vite together through `concurrently`.

## 20. Testing

Tests are configured in `phpunit.xml`.

Test environment settings:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_STORE=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`
- `BCRYPT_ROUNDS=4`

This means automated tests do not touch the real development database.

Run all tests:

```bash
php artisan test
```

Or in Docker:

```bash
docker-compose exec app php artisan test
```

### Test Coverage Summary

| Test file | Coverage |
| --- | --- |
| `tests/Unit/BookingPriceTest.php` | Base price, minimum charge, multi-hour pricing, peak surcharge boundaries, weekend surcharge, stacked surcharges |
| `tests/Feature/BookingTest.php` | Cancellation window, wrong-user cancellation protection, rental pricing, invalid times, authenticated booking, rental attachment, duplicate slot rejection, unauthenticated redirect, past slot rejection |
| `tests/Feature/ReviewTest.php` | Review eligibility, cancelled booking rejection, duplicate review prevention, rating validation, comment length validation |
| `tests/Feature/AuthRegistrationTest.php` | New users receive `user` role and not `panel_user` |
| `tests/Feature/AdminAccessTest.php` | Admin access rules, manager scoping, amenity restriction, rental scoping, review read-only behavior, schedule-based slot generation |
| `tests/Feature/ChatbotTest.php` | Chatbot rendering, toggling, suggestions, demo responses, markdown parsing, pending booking UI, pending booking confirmation |

## 21. Deployment Notes

The repository includes:

- `Dockerfile`
- `docker-compose.yml`
- `render.yaml`

The Dockerfile builds a PHP 8.2 CLI image, installs PHP extensions needed by Laravel and this app, installs Composer dependencies, optimizes autoloading, and starts Laravel through `php artisan serve`.

For production-like deployment:

1. Set a real `APP_KEY`.
2. Set `APP_ENV=production`.
3. Set `APP_DEBUG=false`.
4. Configure a persistent database, preferably PostgreSQL based on the current Docker setup.
5. Set `APP_TIMEZONE=Europe/Skopje`.
6. Configure `APP_URL` to the deployed domain.
7. Run migrations.
8. Seed only if demo data is desired.
9. Ensure a queue worker runs if queued jobs are used.
10. Set `GEMINI_API_KEY` only if the AI chatbot should use the Gemini API.

Because this project uses CDN-hosted Tailwind and Flatpickr in the public layout, there is no required public frontend build step for the main customer-facing pages.

## 22. Security and Data Integrity Notes

Important protections already present:

- Passwords are hashed by Laravel.
- Login is throttled at 6 attempts per minute.
- Logout uses POST and CSRF protection.
- Admin panel access is role-gated.
- Facility managers are scoped at query and action levels.
- Booking confirmation is transactional.
- Booking overlap is checked before creation.
- Review uniqueness is enforced by the database.
- Users cannot cancel other users' bookings.
- Users cannot review facilities without a non-cancelled booking.
- Chatbot markdown rendering escapes user/bot text before applying limited formatting.

Areas to be careful with in future changes:

- Keep booking overlap checks whenever adding admin-side booking creation logic.
- If allowing multi-hour bookings through the UI, update slot generation and pricing display together.
- If switching database engines, re-test locking behavior in `confirmBooking()`.
- If changing timezone settings, verify past/future slot behavior manually.
- If expanding chatbot booking functionality, keep the same booking validation used by the normal booking flow.

## 23. Known Limitations and Assumptions

- Public bookings are currently one-hour slots only.
- Rental selection in the facility booking modal uses quantity 1 for each selected rental.
- The chatbot can create bookings without rentals.
- The admin booking form lets admins edit fields directly; it does not fully reproduce the public booking validation UI.
- Current Docker and `.env.example` use PostgreSQL, while some older project notes describe SQLite as the default.
- The public frontend depends on external CDN access for Tailwind, Flatpickr, and Google Fonts.

## 24. Suggested Demonstration Flow

For a professor handoff or project demo:

1. Start Docker with `docker-compose up --build`.
2. Seed data with `docker-compose exec app php artisan db:seed`.
3. Open `http://localhost:8000`.
4. Search facilities by city and sport.
5. Open a facility detail page.
6. Pick a future date and available slot.
7. Log in as `user@test.com` / `password`.
8. Confirm a booking with an optional rental.
9. Open `/dashboard` to show upcoming booking and QR code.
10. Try cancellation with a booking more than 24 hours away.
11. Visit the same facility and leave a review.
12. Log in as `admin@test.com` / `password` and show full admin access.
13. Log in as `manager@test.com` / `password` and show that admin records are scoped to assigned facilities.
14. Open the chatbot and test demo-mode questions such as "Do you have Tennis courts?" or "What are the cancellation rules?"

## 25. Maintenance Checklist

Before handing in or deploying a fresh copy:

- Run `php artisan test`.
- Confirm `.env` has the correct database credentials.
- Confirm `APP_TIMEZONE=Europe/Skopje`.
- Confirm seeded credentials work if demo data is expected.
- Confirm `/admin` blocks regular users.
- Confirm future slots are bookable and past slots are disabled.
- Confirm a duplicate booking attempt is rejected.
- Confirm review submission works only after a non-cancelled booking.
- Confirm static images under `public/images` load correctly.
- Confirm `GEMINI_API_KEY` is blank for demo mode or valid for AI mode.
