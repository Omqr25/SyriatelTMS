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

### Certificate issuance worker

From `backend`, run `php artisan migrate` once and start the worker in a dedicated terminal with `php artisan certificates:work`. Stop it with Ctrl+C. HR can confirm issuance while the worker is stopped. The queued certificates resume when the worker starts. Keep the existing Core and Export worker commands as-is.

The certificate worker uses the dedicated database queue and private local storage. The web process and worker must share that storage and have permission to read the immutable backgrounds and write issued PDFs. Keep the same database and certificate configuration in both processes. On Windows/Laragon, run the command with the Laragon PHP CLI and retain the Phase 2 `CERTIFICATE_PHP_CLI` setting if PHP executable discovery requires it.

Rendering has a 12-second subprocess timeout and a 128 MB child memory limit. On PHP builds with PCNTL, Laravel additionally enforces the full job timeout (120 seconds by default). The queue makes interrupted work visible after the job timeout plus 60 seconds. On Windows without PCNTL, the rendering timeout still applies. A stalled local database or filesystem call may require stopping and restarting the worker process.

### Certificate archive bulk downloads

Apply the Phase 4 migration with `php artisan migrate`, then restart `php artisan certificates:work`. ZIP uses PHP's existing `zip` extension in the worker CLI. Separate services and rendering tools are unnecessary.

HR can prepare one package at a time. Each package accepts up to 250 certificates, at most 10 MiB per original, 64 MiB total, and 500 pages (at most 25 per original). Preparation uses private local storage and a PHP subprocess capped at 256 MiB and up to 90 seconds. Keep space for staged originals and the output. Combined PDF imports the visible page geometry and vector content. ZIP stores the exact original bytes. An unreadable, damaged, encrypted, active or unsupported PDF fails the entire package with its certificate record ID when available. Historical files remain available through their individual actions.

Downloads are scoped to the HR requester and expire 24 hours after processing. The existing scheduler runs `certificates:clean-packages` hourly. For local maintenance, run `php artisan certificates:clean-packages` directly. Cleanup allows a ten-minute grace period for downloads already in progress and only removes files under `certificates/packages`.

### Certificate release and rollback

Before a first rollout, back up the database and the private certificate tree, including historical PDFs. Apply `php artisan migrate --force` with the previous certificate worker stopped. The archive preflight reports duplicate trainee/program pairs or orphan certificate records before adding safeguards. Resolve any reported records explicitly, retaining their files. Deploy the web code and restart the certificate worker against the same database and writable, persistent private filesystem. The scheduler must be running for hourly cleanup. Phase 5 adds no schema migration.

For a Phase 5 code rollback, stop the certificate worker, revert the application to the approved Phase 4 release, and restart the worker. Keep the schema, queue, and private files. Certificate migrations intentionally stop database rollback to protect the archive. A release older than the certificate worker requires an explicit compatibility check and a drained queue before use.

The hourly cleanup also removes expired upload drafts, abandoned render scratch directories, and unpublished artifacts from failed attempts. Issuance is limited to 500 trainees per confirmation to bound one database transaction. ZIP entry names use the frozen recipient name and fall back to the current account name for legacy records. Only filename collisions add a record identifier.
