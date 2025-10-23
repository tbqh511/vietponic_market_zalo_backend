API scaffolding for Zalo Mini App

Files added:
- database/migrations/2025_10_21_000001_add_zalo_id_and_is_admin_to_users_table.php  (adds zalo_id, avatar, is_admin)
- app/Http/Middleware/JwtMiddleware.php
- app/Http/Middleware/AllowZaloOrigin.php
- app/Http/Controllers/Api/*.php  (AuthController, CategoryController, ProductController, BannerController, StationController, OrderController, AdminController)
- routes/api.php
- config/cors.php

Manual steps after adding files:
1. Install dependency for JWT. Recommended: tymon/jwt-auth (Laravel integration).

   Why: the middleware and AuthController use the Tymon JWT facade (JWTAuth). You must install this package locally before attempting to authenticate.

   On your machine (macOS / zsh) run:

```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\\JWTAuth\\Providers\\LaravelServiceProvider" --tag="config"
php artisan jwt:secret
```

If Composer is not available on the environment where you run the app, install Composer first (https://getcomposer.org/download/). If you prefer a lighter approach (no package publish step), you can use `firebase/php-jwt` instead — tell me and I will switch the code to use it.

2. Add environment variables to your .env:
   JWT_SECRET=<random_string>
   JWT_TTL=3600
   ZALO_VERIFY_URL=https://open.zalo.me/v2.0/me
   ALLOWED_ZALO_ORIGINS=https://h5.zdn.vn

3. Register middleware in `app/Http/Kernel.php` (if present) by adding to `$routeMiddleware`:
   'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
   'allow.zalo' => \App\Http\Middleware\AllowZaloOrigin::class,

4. Ensure `routes/api.php` is loaded (Laravel loads it by default).

5. Run migrations and seeders locally (MySQL must be configured in `.env`):

```bash
# run migrations
php artisan migrate --force

# seed database (DatabaseSeeder registers the mock import seeders)
php artisan db:seed --class=DatabaseSeeder
```

Troubleshooting tips:
- If `composer` is not found: install Composer on your machine or run the composer commands in your CI/deploy environment.
- If `php artisan jwt:secret` is not available, ensure `tymon/jwt-auth` is installed and published; otherwise set `JWT_SECRET` in `.env` manually.
- If migrations fail due to existing tables, either drop them first or edit the migration timestamps/IDs to avoid conflicts.

6. Start server and test endpoints (see README for example curl calls).
