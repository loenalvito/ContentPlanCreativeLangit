# Kolabo Creative Content Management System

Internal Laravel application for the complete Kolabo content lifecycle: **Ideas → Content Plan → Calendar → Production → Review → Scheduled → Published**. Content is the single source of truth; Dashboard, Calendar, Production, Published, My Tasks, and Team are synchronized queries over the same records.

## Stack

- Laravel 12 / PHP 8.2+
- MySQL 8+ (SQLite is configured for the local demo and automated tests)
- Blade, Tailwind CSS, Alpine.js
- FullCalendar and SortableJS
- `spatie/laravel-permission` and `spatie/laravel-activitylog`

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kolabo_creative
DB_USERNAME=root
DB_PASSWORD=
```

Then initialize and run:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

The current UI loads Tailwind, Alpine, FullCalendar, and SortableJS through pinned CDN builds, so no frontend compilation is required for the demo. For a production deployment, self-host these assets through Vite and run `npm install && npm run build`.

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
- `database/seeders/DatabaseSeeder.php` — taxonomy, roles, permissions, users, ideas, and demo content
- `resources/views` — responsive mockup-derived Blade UI
- `tests/Feature` — critical business-flow tests

## Implementation assumptions

- MVP uses external URLs for assets and published posts; no binary DAM or direct social publishing.
- Weekly calendar and notifications remain future enhancements; monthly drag-to-reschedule is implemented for authorized users.
- Status values are backed enums stored as readable strings.
- User deactivation is used instead of deletion so historical attribution remains intact.
- Role pages currently provide an auditable permission matrix; the seeded role configuration is the baseline policy.
