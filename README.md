# Sport-Manager - Sports Hall Management System

A modern, full-featured sports facility booking platform built with the TALL stack. Browse sports halls, book courts with real-time availability, rent equipment, and manage everything from a powerful admin dashboard. Designed for sports center operators who want to digitize their booking workflow.

---

## Deploying on Render

This repository is Render-ready via `render.yaml` (web + worker + PostgreSQL).

### 1) Push to GitHub

Push your branch with all latest changes (including `render.yaml`).

### 2) Create services from Blueprint

In Render dashboard:

- New → Blueprint
- Select your GitHub repository
- Render will create:
  - `sport-manager-web` (Docker web service)
  - `sport-manager-worker` (queue worker)
  - `sport-manager-db` (PostgreSQL)

### 3) Ensure `APP_KEY` is shared by web and worker

`APP_KEY` is auto-generated for the web service in the blueprint.
Set the **same** value on the worker service manually (Environment tab), then redeploy worker.

### 4) First deploy checks

The web service runs migrations automatically at startup (`RUN_MIGRATIONS=true`).
The worker skips migrations (`RUN_MIGRATIONS=false`).

After deploy, open:

- Frontend: `https://<your-render-url>/`
- Admin panel: `https://<your-render-url>/admin`

### 5) Notes

- Docker image includes `pdo_pgsql` for Render PostgreSQL.
- App binds to Render's dynamic `$PORT`.
- Use managed Postgres (not SQLite) on Render.

---

## Features

### User Features

- Browse and search sports facilities by city, sport type, and available dates
- View facility details including amenities, ratings, reviews, and price ranges
- Real-time court availability with hourly time slot selection
- Dynamic pricing with peak hour (+20%) and weekend (+10%) surcharges
- Context-aware equipment rentals filtered by sport type (e.g., rackets for tennis, goggles for swimming)
- Transparent price breakdown before booking confirmation
- QR code generation for each confirmed booking
- Personal dashboard to view upcoming and past bookings
- Booking cancellation (allowed if more than 24 hours before start time)
- Leave and update reviews with star ratings for visited facilities

### Admin Features

- Full admin dashboard at `/admin` powered by FilamentPHP v3
- Create, edit, and manage sports facilities with name, description, location, images, and amenities
- Set facility status (active/inactive) to control visibility on the frontend
- Manage courts per facility with sport type and base hourly pricing
- Manage equipment rentals with sport-type suitability rules
- View and manage all bookings with status tracking
- Calendar view for booking overview (via FullCalendar integration)
- Role-based access control with Filament Shield (super_admin and admin roles)
- Ownership-based authorization: admins can only modify/delete their own facilities
- Manage amenities available across all facilities

---

## Tech Stack

| Layer        | Technology                                      |
|--------------|--------------------------------------------------|
| Framework    | Laravel 11                                       |
| Frontend     | Livewire v3 + Blade Templates + Tailwind CSS     |
| Admin Panel  | FilamentPHP v3                                   |
| Auth & Roles | Spatie Laravel Permission + Filament Shield       |
| Database     | SQLite                                           |
| QR Codes     | SimpleSoftwareIO Simple QRCode                   |
| Media        | Spatie Laravel MediaLibrary                      |
| Calendar     | Saade Filament FullCalendar                      |
| Language     | PHP 8.2+                                         |

---

## Prerequisites

Make sure you have the following installed on your machine:

- **PHP** >= 8.2 (with extensions: `sqlite3`, `mbstring`, `xml`, `curl`, `fileinfo`, `gd`)
- **Composer** >= 2.x
- **SQLite** (usually bundled with PHP)
- **Git**

> Node.js/NPM is **not required** -- Tailwind CSS is loaded via CDN.

---

## Installation Guide

### 1. Clone the Repository

```bash
git clone https://github.com/durazzo2/Sport-Manager.git
cd Sport-Manager
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Create the Database

```bash
touch database/database.sqlite
```

### 5. Run Migrations and Seed Data

```bash
php artisan migrate --seed
```

This will create all tables and populate the database with sample facilities, courts, amenities, equipment rentals, and test users.

### 6. Start the Development Server

```bash
php artisan serve
```

The application will be available at `http://localhost:8000`.

---

## Admin Access

### Using the Seeded Test Accounts

After running `php artisan migrate --seed`, two test accounts are available:

| Role     | Email             | Password   | Access     |
|----------|-------------------|------------|------------|
| Admin    | admin@test.com    | password   | `/admin`   |
| Customer | user@test.com     | password   | Frontend   |

### Creating a New Admin User

To create a fresh admin user with access to the `/admin` dashboard:

```bash
php artisan make:filament-user
```

Follow the prompts to enter a name, email, and password. This creates a user with admin panel access.

To grant the `super_admin` role (full permissions including managing other admins' facilities):

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'your@email.com')->first();
$user->assignRole('super_admin');
```

---

