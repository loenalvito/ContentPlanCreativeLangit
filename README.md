# Kolabo Creative Content Management System

## Revision 2 workflow

- Sales users land on the permission-protected `/sales-dashboard`, which shows database-driven current-week Creative workload.
- `Request Content` creates an Idea (never a Content directly), with authenticated requester/department, multi-platform targets, and optional urgent deadline/purpose.
- Idea platforms use the shared `platforms` master through `idea_platform`. `Selected` is no longer an Idea status; eligible Ideas are selected transiently with checkboxes and converted in one transaction.
- Bulk conversion creates one Planned Content per Idea and requires Publish Date, PIC, and an active Social Account for every Platform.
- Social Accounts belong to a Platform and are assigned on `content_platform.account_id`. The legacy `contents.account_id` remains as a backward-compatible fallback. Accounts are manual planning identities only; no OAuth or publishing API is implemented.
- Users use soft deletion so historical attribution remains. Super Admin users/roles cannot be deleted or deactivated. Admins can edit users, change passwords, toggle active status, and safely delete non-protected users.
- Added permissions: `sales_dashboard.view`, `content_request.create`, `ideas.bulk_move_to_content`, `accounts.*`, `users.delete`, `users.change_password`, and `users.change_status`.

Internal Laravel application for the complete Kolabo content lifecycle: **Ideas → Content Plan → Calendar → Production → Review → Scheduled → Published**. Content is the single source of truth; Dashboard, Calendar, Production, Published, My Tasks, and Team are synchronized queries over the same records.

## Stack

- Laravel 12 / PHP 8.2+
- MySQL 8+ (SQLite is configured for the local demo and automated tests)
- Blade, Tailwind CSS, Alpine.js
- FullCalendar and SortableJS
- `spatie/laravel-permission` and `spatie/laravel-activitylog`

Composer resolves dependencies against PHP 8.2 through `config.platform.php`. The compatible package line is `spatie/laravel-permission ^6.0`; major 8 requires PHP 8.3. Deploy with `composer install` so the tested `composer.lock` is used—do not run `composer update` on the production server.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

For the default local SQLite workflow:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Local/testing seeding includes demo records. Set `SEED_DEMO_DATA=false` to initialize only required master data.

For MySQL production, start from `.env.production.example` and configure credentials for a dedicated non-root database user:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kolabo_creative
DB_USERNAME=kolabo_app
DB_PASSWORD=use-a-long-random-secret
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
SEED_DEMO_DATA=false
```

Then initialize production with non-destructive migrations and master data only:

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\MasterDataSeeder --force
php artisan kolabo:create-super-admin admin@example.com --name="Kolabo Admin"
```

Build production assets with `npm ci && npm run build`. See [production deployment](docs/production-deployment.md) and [backup/restore](docs/database-backup.md) for the exact release order and safeguards. Never run `migrate:fresh` against production.

## Demo credentials

All demo accounts use password `password`.

| Role | Email |
|---|---|
| Super Admin | `admin@kolabo.id` |
| Creative Lead | `lead@kolabo.id` |
| Creative Member | `fadly@kolabo.id` |
| Creative Member | `nabila@kolabo.id` |
| Sales Contributor | `sales@kolabo.id` |

Sales Contributor only sees Ideas and Calendar, can submit ideas and view their own ideas, and is rejected server-side from Content, Production, Users, Roles, and calendar mutations.

## Tests

```bash
php artisan test
```

Critical feature coverage includes authorization boundaries, automatic idea attribution, own-idea visibility, calendar mutation denial, Idea-to-Content conversion with source attribution, persisted Kanban status changes, and Published Library synchronization.

## Structure

- `app/Enums` — stable workflow statuses
- `app/Models` — domain models and relationships
- `app/Http/Controllers/KolaboController.php` — module endpoints with server-side authorization
- `database/migrations` — normalized schema, indexes, foreign keys, permission and activity tables
- `database/seeders/MasterDataSeeder.php` — production-safe, idempotent taxonomy, roles, and permissions
- `database/seeders/DemoSeeder.php` — local/testing-only users, ideas, and demo content
- `resources/views` — responsive mockup-derived Blade UI
- `tests/Feature` — critical business-flow tests

## Implementation assumptions

- MVP uses external URLs for assets and published posts; no binary DAM or direct social publishing.
- Weekly calendar and notifications remain future enhancements; monthly drag-to-reschedule is implemented for authorized users.
- Status values are backed enums stored as readable strings.
- User deactivation is used instead of deletion so historical attribution remains intact.
- Role pages currently provide an auditable permission matrix; the seeded role configuration is the baseline policy.

## Production Deployment

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --force
php artisan kolabo:create-super-admin admin@example.com --name="Kolabo Admin"
php artisan storage:link
php artisan optimize
php artisan queue:restart
```

## Production Update

```bash
php artisan down --refresh=15
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\MasterDataSeeder --force
php artisan optimize
php artisan queue:restart
php artisan up
```
