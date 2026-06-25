# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview
Laravel 9 REST API + admin panel powering a Zalo Mini App e-commerce platform (hydroponics/vegetable market). Key business domains: customer orders, affiliate referrals, farm partner inventory, ViettelPost shipping, Zalo payments.

## Tech Stack
- **Backend:** PHP 8.0+, Laravel 9.19, Eloquent ORM, MySQL
- **Frontend (admin):** Blade + Bootstrap 5, Vite, Highcharts (no separate SPA — admin is server-rendered)
- **Auth:** JWT (`tymon/jwt-auth`) for Mini App customers; Laravel Sanctum for admin; custom `ADMIN_API_SECRET` header for internal APIs
- **Integrations:** Zalo Checkout SDK, Zalo OA, ZNS (push notifications), ViettelPost (VTP) shipping, PayPal

## Key Commands
```bash
# Development
php artisan serve                    # Start dev server
npm run dev                          # Vite HMR for admin panel assets
php artisan queue:work               # Process jobs

# Testing
composer test                        # All suites
composer test:unit                   # Unit only
composer test:zalo                   # Zalo payment flow
composer test:affiliate              # Affiliate system
composer test:farm                   # Farm partner
composer test:shipping               # VTP shipping
composer test:notify                 # Zalo OA/ZNS notifications

# Run a single test file
php artisan test --env=testing tests/Feature/ZaloNotifyTest.php

# Code quality
./vendor/bin/pint                    # Auto-format PHP (Laravel PSR-12)
./vendor/bin/pint --test             # Check style without modifying

# Database
php artisan migrate                  # Run pending migrations
php artisan migrate:fresh --seed     # Reset + seed
php artisan tinker                   # Interactive REPL

# Scheduled commands (run manually via artisan)
php artisan farms:snapshot-daily     # Expire batches + update farm payouts
php artisan orders:auto-cancel-stale # Cancel stale unpaid orders, release stock
php artisan vtp:retry-cancel         # Retry pending VTP cancellations
php artisan vtp:sync-locations       # Sync VTP province/district/ward data
php artisan vtp:refresh-token        # Refresh ViettelPost API token
```

## Directory Structure
```
app/
  Console/Commands/   # Artisan commands (AutoCancelStaleOrders, FarmsSnapshotDaily, VtpRetryCancel, etc.)
  Events/             # OrderPaymentSucceeded, OrderDelivered
  Http/
    Concerns/         # InteractsWithAccountStatus (trait used by ZaloApiController)
    Controllers/      # ZaloApiController (main customer API), Admin/, Farm/ subdirectories
    Middleware/       # zalo.jwt, zalo.farm, zalo.admin — see Kernel.php for aliases
    Requests/         # Form request validation classes
  Jobs/               # CancelUnpaidOrder, CheckPaymentStatus, CheckRefundStatus, SendZaloNotification
  Listeners/          # DeductStockOnPayment, CreateVtpOrderOnPayment, RecordAffiliateCommission,
                      # SendOrderNotification, ReleaseStockOnCancellation
  Models/             # 43 Eloquent models (ZaloOrder, ZaloProduct, Customer, Farm, AffiliateCommission, etc.)
  Services/           # Business logic layer (see Services section below)
  Support/            # AffiliateCodeGenerator, ContactMasker, DispatchesOrderNotifications (trait)
  Helpers/            # custom_helper.php, verify-permission_helper.php (autoloaded globally)
routes/
  api.php             # REST API: Mini App customers + Farm partners + Admin webhooks
  web.php             # Admin panel (Blade) + landing page
database/migrations/  # 50+ migrations
tests/
  Unit/               # 5 unit tests (commission calc, MAC verification, code generation)
  Feature/            # 40+ integration tests (SQLite in-memory, RefreshDatabase)
```

## Authentication
| Context | Middleware alias | Mechanism |
|---|---|---|
| Zalo Mini App customers | `zalo.jwt` | JWT Bearer token (tymon/jwt-auth) |
| Farm partners | `zalo.farm` | JWT + `farm_partner` role + approved status + active Farm record attached to request |
| Admin API (internal) | `zalo.admin` | `X-Admin-Secret` header = `ADMIN_API_SECRET` env |
| Admin panel (web) | Laravel Sanctum | Session cookie |

## Event-Driven Architecture
`OrderPaymentSucceeded` is fired after payment confirmed; it triggers three listeners in sequence:
- `DeductStockOnPayment` — reduces inventory stock batches
- `CreateVtpOrderOnPayment` — dispatches `CreateVtpOrder` job to create VTP shipment
- `SendOrderNotification` — sends ZNS push to customer

`OrderDelivered` is fired when VTP webhook marks order delivered; it triggers:
- `RecordAffiliateCommission` — affiliate commission is credited on delivery, not payment (COD-safe)

## Services Layer (`app/Services/`)
| Service | Responsibility |
|---|---|
| `StockService` | Inventory deduction, reservation, release on cancellation |
| `VoucherService` | Discount code validation and application |
| `RefundService` | Zalo Pay refund initiation and status checking |
| `ViettelPostService` | VTP API calls (rate estimate, order creation, cancellation) |
| `VtpOrderService` | Orchestrates VTP order lifecycle |
| `VtpWebhookService` | Processes VTP tracking event webhooks |
| `FarmDashboardService` | Farm partner analytics and revenue aggregation |
| `PackingService` | Order packing workflow (assign, start, confirm) |
| `StationPickerService` | Pickup station selection logic |
| `ZaloOaClient` | Zalo OA API calls (ZNS push, OA token management) |
| `ZaloPayRefundClient` | Zalo Pay refund HTTP client |

## Zalo Integration
- **Zalo Checkout SDK:** Payment uses `ZALO_CHECK_OUT_SECRET` for MAC signature (HMAC-SHA256). See `ZaloMacCalculationTest` for reference implementation.
- **Webhook `POST /api/notify`:** Verifies `ZALO_APP_SECRET` before processing payment callbacks.
- **ZNS push templates:** 4 env vars — `ZALO_ZNS_TEMPLATE_PAID`, `_STATUS`, `_CANCELLED`, `_SHIPPING`.
- **Zalo OA webhook `POST /api/oa/webhook`:** Handles follow/unfollow events; verifies MAC using `ZALO_OA_SECRET_KEY`.

## ViettelPost (VTP) Shipping
- Shipping rate estimation via VTP API (province/district/ward lookup from local `vtp_*` tables).
- `POST /api/viettelpost/webhook` receives real-time tracking updates (processed by `VtpWebhookService`).
- VTP order creation triggered by `CreateVtpOrderOnPayment` listener → dispatches `CreateVtpOrder` job.
- Stale VTP cancellations retried every 30 min via `vtp:retry-cancel` scheduled command.

## Scheduled Commands
| Command | Schedule | Purpose |
|---|---|---|
| `farms:snapshot-daily` | Daily 23:30 | Expire overdue batches + accrue farm payouts |
| `orders:auto-cancel-stale` | Every 5 min | Auto-cancel online orders stuck > 30 min unpaid |
| `vtp:retry-cancel` | Every 30 min | Retry VTP cancellations not yet confirmed (max 5 attempts) |
| `vtp:sync-locations` | Weekly Mon 2am | Refresh VTP province/district data |
| `vtp:refresh-token` | Weekly | Refresh ViettelPost API auth token |

## Test Configuration
- Database: SQLite `:memory:` (auto-migrated per test via `RefreshDatabase` trait)
- Queue: `sync` (jobs and listeners execute inline, no worker needed in tests)
- All secrets defined as test values in `phpunit.xml` — never use real credentials in tests
- Run individual test file: `php artisan test --env=testing tests/Feature/SomeTest.php`

## Code Conventions
- PHP formatted with **Laravel Pint** (PSR-12 + Laravel preset) — run `./vendor/bin/pint` before committing
- Global helper functions live in `app/Helpers/` and are autoloaded via `composer.json`
- New Eloquent models: prefer explicit `$fillable` over `$guarded = []`
- API responses: JSON with consistent `{'error': bool, 'data': ...}` structure
- Affiliate commission is recorded on `OrderDelivered`, not `OrderPaymentSucceeded` — this ensures COD orders are handled correctly
