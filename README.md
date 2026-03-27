# Private Laravel Vue Chat
## Tech Stack

- Backend framework: Laravel `^12.0`
- PHP: `^8.2` (CI runs PHP `8.3`)
- Frontend framework: Vue `^3.4.0`
- Inertia: `inertiajs/inertia-laravel ^2.0` + `@inertiajs/vue3 ^2.0.0`
- Realtime broadcasting:
  - Laravel Reverb `^1.8` (`BROADCAST_CONNECTION=reverb`)
  - Laravel Echo `^2.3.0`
  - Pusher JS client `^8.4.0`
- Database:
  - Local non-Docker default from `.env.example`: SQLite (`DB_CONNECTION=sqlite`)
  - Docker/Sail service from `compose.yaml`: MySQL `8.4`
- Cache / sessions / queues:
  - Redis service (`redis:alpine`) in Docker mode
  - Database-backed sessions/cache/queue enabled in `.env.example`
- Build tooling:
  - Vite `^7.0.7`
  - `@vitejs/plugin-vue ^6.0.0`
  - `laravel-vite-plugin ^2.0.0`
- Styling:
  - Tailwind CSS `^3.2.1`
  - `@tailwindcss/forms ^0.5.3`
- Frontend testing:
  - Vitest `^2.1.8`
  - `@vue/test-utils ^2.4.6`
  - jsdom `^25.0.1`
- Auth/API:
  - Laravel Sanctum `^4.0`
- Local Node version target:
  - `.nvmrc`: Node `22.12.0`
  - CI currently pins Node `20.19.0` in `.github/workflows/ci-cd.yml`

## Local Setup

### 1) Download the code

```bash
git clone https://github.com/<your-org-or-user>/private_laravel_vue_chat.git
cd private_laravel_vue_chat
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`compose.yaml` defines these local Docker services: `laravel.test`, `mysql`, `redis`, `reverb`, and `scheduler`.

### Option A: Run locally with `php artisan serve` (no Docker)

This project's `.env.example` defaults to SQLite, so you can keep that for quick local setup.

1. Confirm `.env` uses local defaults:

```env
APP_ENV=local
APP_URL=http://localhost:80
DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

2. Create SQLite DB and run migrations:

```bash
mkdir -p database
touch database/database.sqlite
php artisan migrate
```

3. Start app services in separate terminals:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan reverb:start --host=0.0.0.0 --port=8080
```

App URL: `http://127.0.0.1:8000`

### Option B: Run with Laravel Sail + Docker (`compose.yaml`)

For Sail, set `.env` database values to MySQL because `compose.yaml` provides `mysql`.

1. Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=private_laravel_vue_chat
DB_USERNAME=sail
DB_PASSWORD=password

APP_PORT=80
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
FORWARD_REVERB_PORT=8080
REVERB_SERVER_PORT=8080
```

2. Start containers:

```bash
./vendor/bin/sail up -d
```

3. Install deps and run migrations in Sail:

```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

4. Start Vite in Sail:

```bash
./vendor/bin/sail npm run dev
```

App URL: `http://localhost`

Stop containers:

```bash
./vendor/bin/sail down
```

For remote clients, do not use `localhost` for `REVERB_HOST` / `VITE_REVERB_HOST`.
Use your server domain and match `REVERB_SCHEME` / `VITE_REVERB_SCHEME` with your site protocol (`http` or `https`).

## Testing

Feature tests in this app use `RefreshDatabase`, so a working database connection is required.

### Option 1: MySQL (default)

Create your local test env file from the template:

```bash
cp .env.testing.example .env.testing
```

Then adjust database settings in `.env.testing` (or exported env vars), for example:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=private_laravel_vue_chat_test
DB_USERNAME=...
DB_PASSWORD=...
```

Run all tests:

```bash
php artisan test
```

### Option 2: SQLite (fast local fallback)

If you prefer SQLite, make sure PHP has `pdo_sqlite` enabled; otherwise tests fail with `could not find driver`.

Run with in-memory SQLite:

```bash
DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test
```

### Targeted suite added in `test/initial-tests`

```bash
php artisan test tests/Feature/DashboardTest.php tests/Feature/ProfileTest.php tests/Feature/Auth/AuthenticationTest.php
```

## Git Workflow

Use a short-lived feature branch per change:

```bash
git checkout -b feature/<short-description>
```

Commit with a focused message:

```bash
git add .
git commit -m "Describe the change"
```

Push branch to GitHub:

```bash
git push -u origin feature/<short-description>
```

Then open a Pull Request from `feature/<short-description>` into `main` on GitHub.


<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

