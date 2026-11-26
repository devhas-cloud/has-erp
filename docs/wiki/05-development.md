# 05 — Development, running and testing

## 1. Requirements

| Requirement | Version / notes |
|---|---|
| PHP | `^8.2`; typical Laravel extensions plus `dom`, `mbstring`, `xml`, `zip`, `gd` for DOMPDF/PhpSpreadsheet |
| Composer | 2.x |
| Database | MySQL/MariaDB for normal use; SQLite `:memory:` for tests |
| Node.js + npm | Needed for the Vite/Tailwind pipeline and `composer dev` |
| WhatsApp gateway | Optional waBaileys instance for WhatsApp features |

## 2. Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate --seed
npm install && npm run build
php artisan storage:link      # profile photos / public-disk files
php artisan serve
```

Or one shortcut:

```bash
composer setup
```

`composer setup` performs install, `.env` copy, key generation, migrations, `npm install`,
and `npm run build`.

### Seeded accounts

`UsersTableSeeder` creates demo users, all with password `password`. The administrator is:

| Username | Role | Password |
|---|---|---|
| `superadmin` | `Admin` | `password` |

Change or remove these credentials outside local/demo environments.

## 3. Environment variables

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Standard Laravel application settings |
| `FORCE_HTTPS` | Only forces HTTPS URLs when the request is already secure |
| `SESSION_SECURE_COOKIE` | Should be true when running exclusively over HTTPS |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database; production `.env` uses MySQL `has_erp` |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | Defaults are `database` |
| `FILESYSTEM_DISK` | Default `local`; documents may use private storage |
| `WABAILEYS_URL`, `WABAILEYS_API_KEY`, `WABAILEYS_SENDER_PHONE` | waBaileys gateway |
| `ER_OVERTIME_DEFAULT_MULTIPLIER`, `ER_OVERTIME_HOUR_DIVISOR`, `ER_OVERTIME_MAX_HOURS`, `ER_OVERTIME_DEFAULT_SHIFT_END` | HR overtime defaults |
| `ER_PAYROLL_THR_MONTH` | Payroll THR month (default 3) |
| `PROJECT_TRACKING_ALERT_DAYS` | "Stalled" threshold for Project Tracking (default 14) |

Config files worth knowing:

| File | Responsibility |
|---|---|
| `config/document_numbers.php` | Transaction document prefixes |
| `config/er.php` | BPJS rates, overtime, payroll cutoff/THR/PPH defaults |
| `config/project_tracking.php` | Project Tracking alert days |
| `config/services.php` | waBaileys credentials |
| `config/database.php`, `config/session.php`, `config/queue.php`, `config/cache.php` | Infrastructure drivers |

## 4. Running

### Development

```bash
composer dev
```

Starts concurrently:

- `php artisan serve`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

Or run pieces manually:

```bash
php artisan serve
php artisan queue:listen
npm run dev
```

The ERP Blade layout loads Bootstrap/jQuery/DataTables/Select2/toastr/SweetAlert2 from CDNs;
only the default welcome page references the Vite bundle. `npm run build` is still recommended
for a complete asset manifest.

### Scheduler

```bash
php artisan schedule:work
```

Production cron:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs:

| Schedule | Service |
|---|---|
| Daily 08:00 | Task H-1 / due-day WhatsApp alerts |
| Every 5 minutes | Payable WhatsApp digest (sends once per day at/after configured time) |
| Every 5 minutes | Receivable WhatsApp digest (same rule) |

### Queue

```bash
php artisan queue:work
```

Default connection is `database`; jobs table exists in the base migrations.

## 5. Testing

```bash
composer test          # clears config cache then runs php artisan test
php artisan test
php artisan test --filter=QuotationTest
./vendor/bin/phpunit tests/Feature/StockLedgerTest.php
```

Test configuration (`phpunit.xml`):

- `APP_ENV=testing`
- SQLite `:memory:`
- `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`
- `BCRYPT_ROUNDS=4`
- Mail/broadcast disabled

Coverage areas (73 feature files) include ACL, sidebar, CRM data/activity, task planner/workflow,
technical configuration approvals for all five divisions, quotation, profit estimate, POS, project
and billing timeline, project tracking, PO supplier approval, purchasing, inventory/stock ledger,
opname, branch transfer, production flow, packing list, delivery order, document numbers, invoices,
receipts, receivables, suppliers/bills/payments/refunds/tax/FX, payables, finance notifications,
products/pricing, profile, dashboards, WhatsApp service, and login landing.

Tests use `RefreshDatabase`; seed only what the scenario needs.

## 6. Code style

```bash
./vendor/bin/pint app/Http/Controllers/QuotationController.php
./vendor/bin/pint
```

Laravel Pint is configured through the default preset.

## 7. Adding or extending a module

Follow `PANDUAN_MODUL.md`. Condensed order:

1. **Migration** — `database/migrations/YYYY_MM_DD_HHMMSS_name.php`; never edit released migrations.
2. **Model** — relations, casts, status constants, label/badge helpers.
3. **Routes** — inside `Route::middleware(['auth', 'access.control'])` in `routes/web.php`.
4. **Seeder** — add the module row to `ModulesTableSeeder` (`module_code`, `module_name`,
   single-segment `route_name`, `icon`, `group`).
5. **Controller** — no permission queries; business validation, `DB::transaction`,
   `Log::record`, notifications.
6. **Views** — use `$canCreate/$canRead/$canUpdate/$canDelete/$canApprove` from the composer.
7. **Tests** — permission gate, workflow, page render, JSON 403, enterprise rules.

Critical conventions:

- Module key = text before the **first dot** of the route name.
- Approval endpoints must end in `approve`, `reject`, `unlock`, `settle`, `pay`, `finalize`,
  `void`; `revise` maps to `can_update`.
- Never calculate per-row permission flags in DataTables JSON; lock the flag at page render.
- Registering a module row is mandatory. An unregistered module route is **allowed** by the
  middleware, so a missing seed silently removes ACL protection.
- New sidebar order comes from `module_groups.sort_order` and `modules.sort_order`.
- Status is a string column with model constants, not a database ENUM.
- All stock writes go through `StockService` inside a transaction with locked rows.

## 8. Deployment checklist

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env   # then configure production values
php artisan key:generate
php artisan migrate --force
npm ci && npm run build
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Also:

- Point the web server document root at `public/`.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- Enable `FORCE_HTTPS=true` and `SESSION_SECURE_COOKIE=true` when TLS terminates at a proxy.
- Ensure `storage/` and `bootstrap/cache/` are writable.
- Run a queue worker under a supervisor and the Laravel scheduler via cron.
- Keep `storage/fonts` available for DOMPDF fonts.
- Configure waBaileys URL/key/sender phone; otherwise WhatsApp sends log a warning and are skipped.

## 9. Troubleshooting

| Symptom | Cause / action |
|---|---|
| 419 on logout from an old tab | Handled in `bootstrap/app.php`: session is invalidated and the user is redirected to login |
| 403 JSON from a data endpoint | User lacks the permission implied by the route verb; verify `user_access_controls` |
| A module route is accessible to everyone | Module row missing from `modules`; the middleware allows unknown modules by design |
| Approval possible without `can_approve` | Approval route name does not end with an approval verb (e.g. hyphenated verb) |
| Stock changes rejected | `StockException` messages explain SN/quantity/reservation violations |
| Changes not visible after config edits | `php artisan optimize:clear` (or `config:clear`, `route:clear`, `view:clear`) |
| WhatsApp not sent | `WABAILEYS_*` incomplete or gateway unreachable; check `storage/logs/laravel.log` |
| Finance reminders not sent daily | Check `Master Data → Notifikasi Finance` settings (`enabled`, `send_time`, recipients, `last_sent_date`) |
| Project Tracking marks stages as "late" unexpectedly | Adjust `PROJECT_TRACKING_ALERT_DAYS` |

Useful inspection commands:

```bash
php artisan route:list --name=quotation
php artisan route:list --path=finance
php artisan about
php artisan migrate:status
```

## 10. Where to look first

| Goal | Start at |
|---|---|
| Understand ACL | `CheckAccessControl`, `AppServiceProvider`, `ModuleAccess` |
| Change stock behaviour | `StockService`, `StockLog`, `StockPicker` |
| Change quotation totals | `Quotation::calculateTotals` and `QuotationController` |
| Change project stages/warnings | `ProjectTracker`, `ProjectTrackingController` |
| Change numbering | `config/document_numbers.php`, `NumberSequence` |
| Change payroll | `PayrollService`, `config/er.php` |
| Add a document type/upload | `Document`, `HasPurchaseDocuments`, `ManagesPurchaseDocuments` |
| Change notifications | `Notifiable`, observers, `NotificationController` |
| Change menu | `modules` / `module_groups` data, `SidebarMenu` |
