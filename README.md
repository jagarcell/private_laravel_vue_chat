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

## Local Development

### Local frontend setup

Use the project Node version and start the Vite dev server:

```bash
nvm use
npm run dev
```

### Full local start (Sail)

Start:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

Reverb is included in Docker Compose and starts with `sail up -d`.

Stop:

```bash
./vendor/bin/sail down
```

### Clean rebuild (Sail)

Use this when Docker images/services get out of sync and you need a fresh rebuild:

```bash
./vendor/bin/sail down --volumes
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

### Realtime websocket server (Reverb)

Start Reverb in a separate terminal for Echo websocket events (like user status updates on logout):

```bash
php artisan reverb:start
```

If you are using Sail, you can also (re)start only the Reverb service:

```bash
./vendor/bin/sail up -d reverb
```

For remote clients, do not use `localhost` for `REVERB_HOST` / `VITE_REVERB_HOST`.
Use your server domain (for example `jagarcell.ddns.net`) and match `REVERB_SCHEME` / `VITE_REVERB_SCHEME` with your site protocol (`http` or `https`).

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