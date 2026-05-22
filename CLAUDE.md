# Sportsalit — Sports Facility Booking Platform

Laravel 11 + Livewire 3 + Blade + Tailwind CSS (CDN) app for booking sports courts in Macedonia.

## Stack

- **PHP 8.2**, Laravel 11, Livewire 3
- **Filament v3** admin panel at `/admin` with Filament Shield (RBAC) and FullCalendar plugin
- **Spatie**: laravel-medialibrary, laravel-permission
- **Tailwind CSS** loaded via CDN in the main layout (not Vite-compiled) — no build step needed for frontend styles
- **Database**: SQLite by default (`database/database.sqlite`), can be switched to MySQL in `.env`
- **Queue/Cache/Session**: all use `database` driver by default

## Running with Docker (recommended)

```bash
# First run — builds image, runs migrations automatically
docker-compose up --build

# Seed sample data (after app is up)
docker-compose exec app php artisan db:seed

# Run tests
docker-compose exec app php artisan test
```

App is at **http://localhost:8000**. Admin panel at **http://localhost:8000/admin**.

The entrypoint (`docker/entrypoint.sh`) auto-creates `.env`, generates `APP_KEY`, creates the SQLite file, and runs migrations on every container start.

## Running locally (without Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
composer run dev        # server + queue + pail + vite concurrently
```

## Seed Credentials

| Role | Email | Password | Admin access |
|------|-------|----------|-------------|
| super_admin | admin@test.com | password | Full |
| facility_manager | manager@test.com | password | Managed facilities only |
| user | user@test.com | password | No |

New self-registered users get the `user` role — they can book courts on the frontend but cannot access `/admin`.

## Routes

| URL | Handler | Auth |
|-----|---------|------|
| `/` | `SearchFacilities` Livewire | No |
| `/facility/{id}` | `FacilityDetail` Livewire | No |
| `/dashboard` | `UserDashboard` Livewire | Required |
| `/login`, `/register`, `/logout` | `AuthController` | No |
| `/admin` | Filament panel | Role required |

`POST /login` is throttled at 6 attempts per minute.

## Models & Relationships

All models except `User` use **UUID primary keys** (`HasUuids`, `$incrementing = false`).

```
User (bigint)
  └── bookings (HasMany)
  └── reviews (HasMany)

Facility (UUID)
  ├── courts (HasMany)
  ├── rentals (HasMany)
  ├── amenities (BelongsToMany)
  └── reviews (HasMany)
  settings: opening_hour, closing_hour (24h integers)
  computed: averageRating, reviewCount  ← prefer DB aggregates (reviews_avg_rating / reviews_count) when set; fall back to loaded collection

Court (UUID)
  ├── facility (BelongsTo)
  └── bookings (HasMany)
  types: Football | Tennis | Swimming | Padel

Booking (UUID)
  ├── user (BelongsTo)
  ├── court (BelongsTo)
  └── rentals (BelongsToMany, pivot: quantity)
  statuses: confirmed | cancelled | completed | checked_in

Rental (UUID)
  ├── facility (BelongsTo, nullable for legacy rows)
  └── suitable_for (JSON array of court types — null/empty = all types)

Amenity   — name + heroicon slug
Review    — rating (1-5), comment, user→facility
          — unique(user_id, facility_id) enforced at DB level
```

## Pricing Logic (`Booking::calculatePrice()`)

- **Base**: `court.base_price_per_hour × hours` (minimum 1 hour, `diffInHours` truncates)
- **Peak surcharge**: +20% if start hour is 18:00–21:59
- **Weekend surcharge**: +10% if start day is Saturday/Sunday
- Surcharges stack: peak is applied first, then weekend on top
- **Rentals**: add flat `rental.price × quantity` per item; loaded in one `whereIn` query, not a loop
- Throws `\InvalidArgumentException` if `end_time <= start_time`
- All prices stored as **integers in Macedonian denar × 100** (e.g., `120000` = 1,200 MKD). Display with `number_format($price / 100, 0) . ' MKD'`

## Booking Rules

- Slots run in 1-hour increments between each facility's `opening_hour` and `closing_hour` (default 08:00–22:00); past slots are shown as unavailable (grayed out)
- Cannot book a slot in the past (enforced in both UI and `confirmBooking()`)
- Cancellation only allowed if `start_time > now() + 24 hours`
- QR code is a UUID string generated at booking creation, rendered via `simplesoftwareio/simple-qrcode` on the dashboard

## Review Rules

- User must have at least one non-cancelled booking at the facility to leave a review
- One review per user per facility (enforced in component + DB unique constraint)
- Rating: integer 1–5; comment: optional, max 1000 chars
- After submission, `reviewSubmitted` event is dispatched — `FacilityDetail` listens and reloads reviews live

## Livewire Components

| Component | File | Notes |
|-----------|------|-------|
| `SearchFacilities` | `app/Livewire/SearchFacilities.php` | Homepage; filters by city, court type, date, amenities (all live); `cities` is a `#[Computed]` property; uses `withAvg`/`withCount` for ratings — does **not** eager-load reviews collection |
| `FacilityDetail` | `app/Livewire/FacilityDetail.php` | Loads all slots in 1 query grouped by court; listens for `reviewSubmitted`; `confirmBooking()` is wrapped in a `DB::transaction` with `lockForUpdate()` to prevent double-booking; uses `$this->facility->courts->firstWhere()` instead of `Court::find()` |
| `UserDashboard` | `app/Livewire/UserDashboard.php` | Booking history + cancel; uses `first()` not `firstOrFail()` in `cancelBooking()`; flashes an error message when the 24h window has passed |
| `LeaveReview` | `app/Livewire/LeaveReview.php` | Validates rating/comment; checks booking exists; prevents duplicates; `wire:model.live` on rating |

## Filament Admin Resources

`app/Filament/Resources/`: Amenity, Booking, Court, Facility, Rental, Review

Admin panel uses **Amber** as primary color. Roles managed via Filament Shield.
`canAccessPanel()` allows `super_admin` and `facility_manager` (only when assigned at least one facility). `user` and `panel_user` are blocked from `/admin`.
Facility assignments are managed in the `Facility` admin resource via the **Facility Managers** relation tab (super admin only).
`facility_manager` can manage only their facilities' courts, bookings, reviews (read-only), rentals (including rental prices), and facility schedule (`opening_hour` / `closing_hour`).

## Layout

The single canonical layout is `resources/views/components/layouts/app.blade.php`.

- Livewire components reference it as `#[Layout('components.layouts.app')]`
- Auth views use it as `<x-layouts.app>`

Do not create a second copy at `resources/views/layouts/app.blade.php`.

## Testing

```bash
docker-compose exec app php artisan test
# or locally: php artisan test
```

Tests use **in-memory SQLite** (configured in `phpunit.xml`) — they never touch the real DB.

| Suite | File | Coverage |
|-------|------|----------|
| Unit | `tests/Unit/BookingPriceTest.php` | `Booking::calculatePrice()` — base, minimum hour, multi-hour, peak boundary, weekend, stacking |
| Feature | `tests/Feature/BookingTest.php` | Cancellation window (allowed / blocked / wrong user / error flash), rental cost, quantity, unknown rental, `end_time < start_time` throws, full booking confirmation flow, rental attachment + price, duplicate slot blocked, unauthenticated redirect, past slot rejected |

## Key Files

```
app/
  Http/Controllers/AuthController.php       — custom login/register/logout
  Livewire/                                 — all frontend components
  Models/                                   — Eloquent models
  Filament/Resources/                       — admin CRUD
  Providers/Filament/AdminPanelProvider.php

resources/
  views/components/layouts/app.blade.php    — canonical layout (nav + footer + Livewire scripts)
  views/livewire/                           — Blade templates for Livewire components
  views/auth/login.blade.php
  views/auth/register.blade.php

database/
  migrations/                               — app migrations prefixed 2026_02_13_2000xx
                                              + 2026_05_16_000001 (unique constraint on reviews)
  seeders/DatabaseSeeder.php                — 5 facilities, sample courts/rentals/reviews

docker/entrypoint.sh                        — container bootstrap script
docker-compose.yml                          — app + queue services
Dockerfile                                  — PHP 8.2-cli + extensions + Composer
routes/web.php
tests/Unit/BookingPriceTest.php
tests/Feature/BookingTest.php
```

## Notes & Gotchas

- Tailwind loads via CDN — no Vite build needed for the public-facing frontend
- Vite/npm is only needed for Filament's compiled assets, which are pre-committed to `public/`
- `Facility::averageRating` and `reviewCount` are computed Eloquent attributes. They check `$this->attributes` for `reviews_avg_rating` / `reviews_count` (set by `withAvg`/`withCount`) first, then fall back to the loaded `$this->reviews` collection. Use `array_key_exists`, not `isset`, to distinguish a null aggregate (no reviews) from a missing key.
- `SearchFacilities` uses `withAvg('reviews', 'rating')->withCount('reviews')` — it does **not** load the reviews collection. The blade reads `$facility->reviews_avg_rating` and `$facility->reviews_count` directly.
- `FacilityDetail` still eager-loads `reviews.user` (needed to display individual reviews). `$this->facility->courts` is lazy-loaded once per request and reused via the cached collection — no `Court::find()` calls.
- `FacilityDetail::confirmBooking()` is atomic: slot availability is checked with `lockForUpdate()` inside a `DB::transaction`. On MySQL/PostgreSQL this holds a row lock; on SQLite (tests) the transaction itself serialises writes.
- `Rental::isSuitableFor()` returns `true` if `suitable_for` is empty (universal rental)
- Image paths starting with `/` are served directly; paths without `/` or `http` get `/storage/` prepended
- `RolePolicy.php`: soft-delete and restore methods return `false` (Spatie Role does not support soft-deletes); `replicate` and `reorder` check real permission strings
