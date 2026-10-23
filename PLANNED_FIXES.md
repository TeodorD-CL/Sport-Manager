# Sport Manager: Planned Fixes

Review date: 2026-10-23. Reviewed at commit `894d7a8` on `master`.

## Scope and method

I read every application file: models, Livewire components, the Filament resources and pages, middleware, routes, migrations, the seeder, all Blade views, config, tests, Docker, Render and repo hygiene.

What I could not verify: PHP is not installed on this machine and `vendor/` is empty, so I did not run the test suite or the app. Items marked **(verify)** depend on framework behaviour that should be confirmed with a quick test before fixing.

Severity levels:

- **Critical**: broken feature, or a security or money bug users can trigger.
- **High**: real bug or risk, fix soon.
- **Medium**: correctness, performance or maintainability issue.
- **Low**: cleanup and polish.

---

## Critical

### C1. Password change on the Profile page does not work
- `resources/views/livewire/user-profile.blade.php:40-63` binds the inputs to `password` and `password_confirmation`. The component (`app/Livewire/UserProfile.php:20-22`) defines `current_password`, `new_password` and `new_password_confirmation`.
- Nothing in the view calls `updatePassword()`. The only form submits `updateProfile`, so a password typed in is ignored, and Livewire may throw "property not found" when the field syncs.
- Fix: bind the inputs to `new_password` and `new_password_confirmation`, and give the password block its own form with `wire:submit="updatePassword"`. Add a feature test.

### C2. Profile success messages never show
- The component flashes `profile_success` (`UserProfile.php:48`) and `password_success` (`:69`). The view only checks `session('success')` (`user-profile.blade.php:8`).
- Fix: use matching keys.
- Related: `render()` computes `totalBookings`, `upcomingBookings`, `reviewsCount` and `favouriteSport` (`UserProfile.php:74-97`), but the view never shows them. That is 4 wasted queries per render. Show them or remove them.

### C3. Users can tamper with chatbot bookings (bypassing the rules)
- `Chatbot::$pendingBooking` (`app/Livewire/Chatbot.php:21`) is a public Livewire property without `#[Locked]`. Any client can overwrite it, for example with `$wire.set('pendingBooking', {...})`.
- `confirmPendingBooking()` (`:77-172`) trusts it and only checks for overlaps. It does **not** re-check "not in the past", "within opening hours" or "exactly 1 hour". Those checks exist only in `prepareBookingInternal()` (`:486-495`).
- Result: a logged-in user can book past slots, slots outside opening hours, or slots of any length on any court.
- Fix: mark it `#[Locked]`. Move the slot validation into one shared place (see M1) and run it again on confirm.

### C4. Facility booking accepts any slot and rental the client sends
In `app/Livewire/FacilityDetail.php`:
- `selectedSlot` is client-controlled. `confirmBooking()` (`:176-188`) checks the hour bounds but not that `end = start + 1h`, that the minutes are `:00`, or that the values parse at all. A crafted `start:'10:00', end:'14:00'` books 4 hours. `end <= start` makes `Booking::calculatePrice` throw `InvalidArgumentException` (`app/Models/Booking.php:58`), which nothing catches, so the user gets a 500. A value like `'abc'` makes Carbon throw, also a 500.
- `selectedRentals` is client-controlled. The price calculation skips unknown IDs, but `attach()` (`:227-229`) attaches whatever was sent:
  - rentals from other facilities, or ones not suitable for the court type;
  - non-UUID strings, which make PostgreSQL throw, so a 500.
- Fix: validate the slot strictly (`H:00` format, exactly one hour, inside opening hours). Only accept rental IDs that are in the `$this->rentals` list computed for that court.

### C5. Stored XSS on the public homepage, writable by facility managers
- `resources/views/livewire/search-facilities.blade.php:32` uses `wire:click="$set('city', '{{ $c }}')"`. Blade escapes `'` as `&#039;`, the browser decodes it back inside the attribute, and Livewire/Alpine runs the result as JavaScript.
- Facility managers can edit `city` (`FacilityResource` lets them edit their facilities). A city value like `x'); alert(document.cookie); ('` runs JavaScript for every visitor of `/`.
- The same pattern is on line 106 (`sort`, static values, so safe today) and in `user-dashboard.blade.php:105` (UUID, safe).
- Fix: use `wire:click="$set('city', @js($c))"`.
- Also `search-facilities.blade.php:161-162` prints amenity names with `{!! !!}`. Only super admins can edit amenities, but escape each name anyway and keep `&bull;` as the only raw HTML.

---

## High

### H1. The Gemini API key is in the URL and can leak into logs
- `Chatbot.php:313` puts `?key={$apiKey}` in the request URL. When the HTTP client throws a connection error, the exception message usually includes the full URL. That message is logged on `:373`, so the key ends up in logs (stderr on Render).
- Fix: send the key in the `x-goog-api-key` header. Rotate the key if logs were ever collected while a key was set.
- Also move the model name `gemini-3.1-flash-lite` into `config/services.php` and check that the model ID really exists. A wrong ID silently falls back to demo mode with a "Status 404" message.

### H2. The chatbot has no rate limit (anonymous users run up the LLM bill)
- `sendMessage()` (`Chatbot.php:174`) can be called without limit by guests. Each call can make up to 3 Gemini requests (`:307`), and each request rebuilds a system prompt containing every facility, court and rental (`:532-608`).
- Fix: add `RateLimiter::attempt()` per IP or user (for example 10 messages a minute), cap the message length, and cache the facilities part of the prompt for a few minutes.

### H3. Overlap protection is weaker than it looks
- The booking transactions use `->lockForUpdate()->exists()` (`FacilityDetail.php:204-209`, `Chatbot.php:117-122`). When no rows match, nothing is locked, so two concurrent requests can both pass.
- The only backstop is `unique(court_id, start_time, end_time)` (bookings migration). It catches identical slots only, not overlapping ones such as 10–12 against 11–12. C3 and C4 make overlapping bookings possible.
- Fix (PostgreSQL): add an exclusion constraint, for example `EXCLUDE USING gist (court_id WITH =, tsrange(start_time, end_time) WITH &&) WHERE (status <> 'cancelled')` with `btree_gist`. Alternatively, lock the court row (`Court::lockForUpdate()->find()`) before checking.
- Also: the chatbot path does not catch `UniqueConstraintViolationException` on its own. It falls into the generic "Could not save" branch without a log entry.

### H4. Uploaded images are stored on a private disk, so they never display
- `.env` and `.env.example` set `FILESYSTEM_DISK=local`. In Laravel 11 that writes to `storage/app/private`.
- `FileUpload::make('image_path')` (`CourtResource.php:55`, `FacilityResource.php:35`) doesn't set a disk, but the views build `/storage/<path>` (`facility-detail.blade.php:52`, `search-facilities.blade.php:131-133`). The Dockerfile and entrypoint never run `storage:link` either.
- On Render's free plan the container disk is also wiped on every deploy, so uploads would be lost anyway.
- Fix: use `->disk('public')->image()->directory('facilities')`, run `php artisan storage:link` in the entrypoint, and use S3 or another persistent store in production.

### H5. Admin-created bookings have no QR code, which may break the user's dashboard (verify)
- `BookingResource.php:54` makes `qr_code` a `disabled()` field. Disabled fields are not saved, so bookings created in the admin panel get `qr_code = null`.
- `user-dashboard.blade.php:123` calls `QrCode::generate($booking->qr_code)` for every non-cancelled booking. With `null` this likely throws, which would make the dashboard return a 500 for that user.
- Fix: generate `qr_code` in a `Booking::creating` model event (also covers the chatbot and facility paths), and guard the view.

### H6. The admin booking form has no validation
`BookingResource.php:43-55`:
- `start_time` and `end_time` are optional, with no `end > start` check, no overlap check and no opening-hours check.
- `total_price` is a raw number in **cents**. Courts and rentals ask for MKD and convert, so an admin entering `1200` here means 12 MKD.
- `user_id` preloads every user, with no `->searchable()`.
- Fix: make the times required, add an `after:start_time` rule and an overlap check in the Create/Edit pages, use the same MKD↔cents conversion as `CourtResource:52-53` (or auto-calculate with `Booking::calculatePrice`), and make the user select searchable.

### H7. Facility managers may be able to bulk-delete reviews (verify)
- `ReviewResource` blocks single edit and delete for managers (`:98-116`), but it doesn't override `canDeleteAny()`. With no policy registered, Filament allows it by default. `DeleteBulkAction` (`:63-66`) would then let a manager delete the negative reviews of their own facility.
- Same gap for `FacilityResource`: a manager can delete their whole facility (`canDelete = canEdit`, `:127-130`), which cascades to its courts, bookings and reviews.
- Fix: override `canDeleteAny()` (super admin only for reviews and facilities). Decide whether managers should ever delete a facility; probably not.

### H8. Cascading deletes wipe booking history
- `bookings.court_id` and `bookings.user_id` use `onDelete('cascade')`. Deleting a court, facility or user silently deletes every booking, so revenue history is gone.
- `booking_rental.rental_id` cascades too. Deleting a rental removes it from past bookings while their `total_price` stays the same.
- Fix: use `restrictOnDelete()`, or soft deletes for Facility, Court, Rental and User. Store `unit_price` on the `booking_rental` pivot so past bookings keep the price that was actually charged.

### H9. Mobile users have no navigation
- `components/layouts/app.blade.php:133-136`: the hamburger button toggles `open` on its own `x-data`, but no mobile menu exists. Below `md`, visitors can't reach Sign in, Register, Bookings, Profile or Sign out.
- Fix: put `x-data="{ open: false }"` on the header and add a menu with `x-show="open"`.

### H10. Production runs on the PHP dev server, with dev dependencies
- `Dockerfile` CMD is `php artisan serve`, which is single-threaded and not meant for production.
- `composer install` runs without `--no-dev`, and there are no `config:cache`, `route:cache` or `view:cache` steps.
- Fix: use php-fpm plus nginx (or FrankenPHP/Octane), `composer install --no-dev --optimize-autoloader`, and run the cache commands in the entrypoint.
- `render.yaml` uses `healthCheckPath: /`, which renders the full homepage with database queries. Use Laravel's built-in `/up`.

---

## Medium

### M1. Booking logic is spread over three places
- The slot validation, overlap query and create-in-transaction code is duplicated in `FacilityDetail::confirmBooking`, `Chatbot::confirmPendingBooking` and `Chatbot::prepareBookingInternal`. The admin panel has none of it. The overlap query alone appears 5 times.
- Fix: one `Booking::book(Court $court, Carbon $start, Carbon $end, array $rentals, User $user)` (or a small action class) that validates, locks, checks overlaps, prices, creates and attaches. All entry points call it. This fixes C3, C4 and H6 in one place.

### M2. Pricing edge cases (`app/Models/Booking.php:53-97`)
- Carbon 3 (installed: 3.11.1) returns a float from `diffInHours`, so a 90-minute booking costs 1.5 hours. That's fine if intended; decide and test it.
- Peak is decided by the start hour only, so a 17:00–20:00 booking pays no peak surcharge. The weekend check also uses the start time only.
- The surcharges (20%, 10%, 18–22h) are hardcoded. They should be per-facility settings.
- The modal breakdown (`facility-detail.blade.php:236-262`) rounds each line separately, so the lines can differ from the total by 1 MKD.
- Slot buttons show `base_price_per_hour` (`FacilityDetail.php:81`), not the real price with surcharges.

### M3. Reviews: rules and loading
- `LeaveReview::submit` (`app/Livewire/LeaveReview.php:46-49`) accepts any non-cancelled booking, including future ones. A user can book, review and then cancel. Require a booking whose `end_time` has passed.
- `$facilityId` (`:11`) is public and not `#[Locked]`. A non-UUID value makes PostgreSQL throw.
- `FacilityDetail` loads **all** reviews with their users (`:31`) and computes the average in PHP. Paginate the reviews or load the latest 10, and use `withAvg`/`withCount` as the homepage already does.
- `CreateReview` and `EditReview` contain manager-scope checks that can never run, because only super admins can create or edit reviews. Delete them.

### M4. Non-UUID input causes 500 errors on PostgreSQL
- `UserDashboard::cancelBooking($bookingId)` (`:16`), `LeaveReview` (`facilityId`) and `Chatbot::Court::find` (LLM-provided `court_id`) all pass raw strings into UUID columns. PostgreSQL throws `invalid input syntax for type uuid` on non-UUID values.
- `SearchFacilities` already guards against this (`:94-95`). Apply the same `Str::isUuid` check, or route model binding, everywhere.

### M5. Search filter issues (`app/Livewire/SearchFacilities.php`)
- The date filter (`:77-91`) has a hardcoded `< 14` bookings threshold that ignores real opening hours. `whereDoesntHave` combined with `orWhereHas(... < 14)` is also redundant, because a count below 14 already covers zero.
- Fix: compare against `closing_hour - opening_hour`, or drop the filter until it's accurate.
- There is no `updatingDate()`, so changing the date doesn't reset the page.
- `type` isn't checked against the allowed sport list.
- The sport list is hardcoded in four places (view, CourtResource, RentalResource, chatbot). Use one PHP enum `CourtType`.

### M6. Chatbot quality
- `Chatbot.php:420`: `where('city', 'like', "%{$city}%")` is case-sensitive on PostgreSQL. Use `whereLike` or `ilike`.
- `checkAvailabilityInternal` runs one bookings query per court (`:438-442`), which is N+1. Load bookings for all courts in one query, as `FacilityDetail::loadAvailableSlots` does.
- `getSystemPrompt` triggers N+1 on `$booking->rentals` (`:569`). Add `rentals` to `with()`.
- The prompt sends the user's email to Google (`:558`) with no clear need. Drop it.
- The mock reply matches `'hi'` anywhere in the text (`:691`), so "which", "this" and "chips" all get the greeting. Match whole words.
- Markdown links like `[Sign In](/login)` (`:86`) show up as raw text, because `parseMarkdown` doesn't handle links. Support simple relative links or reword the message.
- The `$messages` history is a public property, so a client can rewrite past "bot" turns that are sent to the model. Lock it, or keep the history in the session.
- `toggleChat` makes a server round trip just to open and close the panel. Use Alpine `x-data="{ open: false }"`.
- `chatbot.blade.php:172` uses `env('GEMINI_API_KEY')` in a view. `env()` returns null once config is cached. Keep only `config(...)`.

### M7. Two login sessions for admins
- The site uses the `web` guard and Filament uses an `admin` guard (`AdminPanelProvider.php:33`). An admin who signs in on the site and clicks "Admin" has to sign in again. Logging out of one doesn't log out of the other.
- Each resource's `getCurrentAdminUser()` also falls back to the `web` guard, which muddles which guard is in charge.
- Fix: use the `web` guard for the panel too. `canAccessPanel` and `EnsureUserIsAdmin` already protect it.
- Check that the Shield-generated roles and permissions use the same guard as the panel. The seeder creates roles on `web`. (verify)

### M8. Duplicated admin code
- `getCurrentAdminUser()` is copy-pasted in 6 resources. The "manager may only use own facility" check is copy-pasted in 8 Create/Edit pages.
- Fix: one trait (for example `ScopesToManagedFacilities`) providing `getCurrentAdminUser()`, a `getEloquentQuery` helper and a shared `assertManagesFacility()`.

### M9. Missing indexes (PostgreSQL does not index foreign keys automatically)
- `bookings.user_id` (dashboard and profile queries), `courts.facility_id`, `reviews.facility_id` (the unique index starts with `user_id`), and `bookings(court_id, start_time)` for range queries.
- `whereDate('start_time', ...)` (`Chatbot.php:440`, `SearchFacilities.php:84`) can't use an index. Use a `start_time >= day AND start_time < next day` range.

### M10. Account security gaps
- Only `POST /login` is throttled (`routes/web.php:14`). `POST /register` is not, and neither is the password check in `UserProfile::updatePassword`.
- There is no password reset flow, even though the `password_reset_tokens` table exists.
- There is no email verification. A user can change their email without entering their password (`UserProfile::updateProfile`).
- After a password change, other sessions stay logged in. Call `Auth::logoutOtherDevices()`.

### M11. Tests run on SQLite, production runs on PostgreSQL
- `phpunit.xml` forces SQLite in memory. UUID strictness, `like` case sensitivity, row locking and exclusion constraints behave differently, so C3, C4, H3 and M4 would not show up in tests.
- Add a PostgreSQL test run (docker-compose already has a `db` service) at least in CI.
- Missing tests: the profile page (C1, C2), admin booking create/edit, chatbot tampering, and booking with foreign or invalid rental IDs.

### M12. The frontend build pipeline is unused
- The layout loads Tailwind from `cdn.tailwindcss.com` (`app.blade.php:10`). Tailwind says not to use that in production: it compiles in the browser, is slow and has no purge.
- Flatpickr loads from jsDelivr with **no version pinned** and no SRI (`:24-25`).
- Meanwhile `vite.config.js`, `tailwind.config.js` (with a different font, Figtree), `resources/css/app.css`, `resources/js/*` and `package.json` exist but are never loaded.
- Fix: switch the layout to `@vite([...])`, add `npm ci && npm run build` to the Dockerfile, and install flatpickr through npm (or at least pin the version).

---

## Low

- **L1.** Duplicate `<title>`: the layout always prints `<title>Sport Manager</title>` (`app.blade.php:7`), and `facility-detail.blade.php:4` pushes a second one. Use `@yield`/`@section('title')`, or Livewire's `#[Title]`.
- **L2.** The dashboard "Past & cancelled" tab shows a **Cancel** button on past confirmed bookings (`user-dashboard.blade.php:104`), and clicking it always errors. Hide it when `start_time` is in the past.
- **L3.** Booking statuses `completed` and `checked_in` are never set automatically. Schedule a command in `routes/console.php` to mark past bookings `completed`.
- **L4.** `$heroImg` in `facility-detail.blade.php:8-11` is computed but never used. Delete it.
- **L5.** The booking modal has no Escape-to-close, no focus trap and no `role="dialog"`. The rating stars are radios hidden with `sr-only` and have no labels. The global `button:focus { outline: none }` (`app.blade.php:40-42`) removes keyboard focus indicators. Use `focus-visible` styles.
- **L6.** The error pages (`errors/403|404|500.blade.php`) use indigo, rounded styling that doesn't match the black-and-white design. The 500 page renders the full layout, including auth queries and the chatbot, so a database outage will fail again while rendering the error page. Make 500 a static page.
- **L7.** Leftover published assets from a package that isn't installed: `public/js/saade/...` and `public/css/saade/...` (filament-fullcalendar is not in `composer.json`). Delete them.
- **L8.** `.config/` (composer `.htaccess`, empty psysh history) is committed. Remove it from git and add it to `.gitignore` (it's already in `.dockerignore`).
- **L9.** The seeder isn't idempotent (`User::create` fails on a second run) and creates `admin@test.com` / `password`. `render.yaml` defaults `RUN_SEED` to false, which is good. Make the seeder refuse to run in `production`, or read the admin password from env.
- **L10.** `.env.example` ships `APP_ENV=local` and `APP_DEBUG=true`, and the entrypoint copies it to `.env` when none exists. Render's environment variables override it today, but a container started without them runs with debug on. Default the example to `production` / `false` and make docker-compose set local values.
- **L11.** `Rental` can have a null `facility_id` (global rentals, `FacilityDetail.php:110-112`), but `RentalResource` makes `facility_id` required, so global rentals can't be created from the admin panel. Decide which behaviour is right.
- **L12.** `composer.json` still has the skeleton `name: laravel/laravel` and description. `RolePolicy` and `Controller` are scaffolding. The Filament dashboard shows only the default `FilamentInfoWidget`.
- **L13.** The README's "Project Evaluation" section is self-assessment marketing ("meets professional development standards") and is contradicted by C1–C5. Remove it, or replace it with a short "Known limitations" list.
- **L14.** `docker-compose.yml` and `render.yaml` define a queue worker, but the app dispatches no jobs, so it's an idle service. Keep it only once notifications (S2) exist. On Render's free plan background workers may not be available (verify).
- **L15.** Admin tables have no `->searchable()`, `->sortable()` or filters (for example bookings by date or status, or by facility). This gets painful as data grows.

---

## Suggestions: what could be built next

Ordered by value for a booking product:

- **S1. Payments or deposits.** Today every booking is `confirmed` instantly and for free, so no-shows cost nothing. Add a pending status, an online payment or deposit, and auto-cancel unpaid holds after N minutes.
- **S2. Email notifications.** Send a booking confirmation (with the QR code), a reminder 24h before, and a cancellation notice. Mail and queue config already exist; this is the first real use for the worker.
- **S3. QR check-in for managers.** The QR code holds a UUID and the `checked_in` status exists, but there's no scanner. Add a Filament page where staff scan or type the code to check the booking in.
- **S4. Manager calendar view.** A day/week grid per court in the admin panel. This is the main screen a facility manager needs.
- **S5. Admin dashboard widgets.** Revenue per facility, occupancy rate, peak hours, upcoming bookings today. Replace `FilamentInfoWidget`.
- **S6. Multi-hour and recurring bookings.** Select consecutive slots, plus "every Tuesday 19:00 for 8 weeks". This needs M1 first.
- **S7. Facility-configurable pricing and schedules.** Per-facility peak window and percentages, per-weekday hours, holidays and closures, blocked maintenance slots.
- **S8. Manager-side cancellation.** Cancel or move a booking with a reason, and notify the user.
- **S9. Review replies.** Managers reply publicly instead of deleting reviews (pairs with H7).
- **S10. Readable URLs and SEO.** `/facility/central-sports-hall` slugs instead of UUIDs, an `og:image`, and a sitemap.
- **S11. Localisation.** Macedonian (and Albanian) UI. The audience is North Macedonia, but everything is English-only.
- **S12. Map and location.** Store lat/lng, show a map on the facility page, and offer "near me" sorting.
- **S13. Waitlist.** Notify users when a slot on a fully booked day frees up after a cancellation.
- **S14. Favourites and "book again" shortcuts**, plus an account page that shows the stats already computed (C2).

---

## Suggested order of work

1. **Security and broken features:** C1, C2, C3, C4, C5, H1, H2.
2. **Booking integrity:** M1 (one booking service), then H3 (exclusion constraint), H5, H6, H8.
3. **Production readiness:** H4, H10, M12, L10, then a PostgreSQL test run (M11).
4. **Admin correctness:** H7, M7, M8, L11, L15.
5. **UX polish:** H9, M2, M3, M5, L1–L6.
6. **Cleanup:** L7, L8, L12, L13, L14.
7. **Features:** S1–S5 first. They turn the demo into a usable product.
