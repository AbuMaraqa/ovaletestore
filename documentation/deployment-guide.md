# Deployment Guide (draft)

## Requirements
PHP 8.4 (ext: gd with WebP+AVIF, intl, exif, pdo_mysql, redis, sodium, zip), Composer 2, Node 22, MySQL 8, Redis, a process manager (Supervisor) for queues, cron.

## Environment (`.env`)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ovaletestore.com
APP_LOCALE=ar
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
DB_CONNECTION=mysql  (dedicated user: SELECT/INSERT/UPDATE/DELETE on the app schema only; migrations run with a separate admin user)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
MEDIA_DISK=public   (or s3 + CDN)
TRUSTED_PROXIES=…   (if behind a load balancer/Cloudflare)
```
Never commit `.env`. `ADMIN_EMAIL` controls the seeded super-admin.

## Release steps
```
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force            # first deploy only (roles + super-admin, random password printed once)
php artisan storage:link
php artisan config:cache && php artisan route:trans:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
```
Use `route:trans:cache` (mcamara) instead of `route:cache` so each locale gets its own cached routes.

## Workers & schedule
- Supervisor: `php artisan queue:work redis --tries=3 --timeout=300 --memory=512` (image conversions are queued).
- Cron: `* * * * * php artisan schedule:run` (backups once configured).

## Data migration on production
```
php artisan woo:inspect
php artisan woo:migrate --dry-run
php artisan woo:migrate
php artisan woo:migrate --images   # queued; watch failed_jobs
php artisan woo:verify             # writes documentation/migration-report.md
```

## Web server
Serve `public/`. Keep the default Laravel `.htaccess`/nginx trailing-slash 301. Gzip/Brotli for text, long cache headers for `/build/*` and `/storage/*` (fingerprinted / immutable media paths).
