# Production deployment (MySQL/MariaDB)

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath`, `curl`, and `intl`.
- MySQL 8.0+ or a currently supported MariaDB release.
- Composer 2 and Node.js 20+.
- A web server pointing its document root to Laravel's `public/` directory.
- A queue worker when `QUEUE_CONNECTION=database` is used.

## First deployment

1. Create the database and a non-root application account. Use [`deployment/mysql-setup.sql`](../deployment/mysql-setup.sql) as a reviewed template; change its password and host before executing it.
2. Copy `.env.production.example` to `.env`, supply real secrets, and set the final HTTPS `APP_URL`.
3. Install and build:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci
   npm run build
   php artisan key:generate --force
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\MasterDataSeeder --force
   php artisan kolabo:create-super-admin admin@example.com --name="Kolabo Admin"
   php artisan storage:link
   php artisan optimize
   ```

   The Super Admin command prompts twice for a password of at least 12 characters. The password is neither committed nor passed through shell history.

4. Configure the web server, writable permissions for `storage/` and `bootstrap/cache/`, the queue worker, scheduler, TLS, and log rotation.
5. Verify `GET /up`, login, authorization boundaries, queue processing, and email delivery.

Generate `APP_KEY` only on the first deployment. Rotating an existing production key invalidates encrypted data and sessions.

## Routine release order

1. Verify a recent backup and create a pre-deploy backup.
2. `php artisan down --refresh=15`
3. Deploy the release files.
4. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
5. `npm ci && npm run build`
6. `php artisan migrate --force`
7. `php artisan db:seed --class=Database\\Seeders\\MasterDataSeeder --force`
8. `php artisan optimize`
9. `php artisan queue:restart`
10. `php artisan up`
11. Smoke-test `/up`, login, Dashboard, Ideas, Content Plan, Calendar, and Production.

Never run `php artisan migrate:fresh` in production: it drops every application table and all data. Use `php artisan migrate --force` only after reviewing pending migrations and confirming a backup.

## Seeder policy

- `MasterDataSeeder` is idempotent and contains departments, taxonomy, formats, platforms/accounts, permissions, and baseline roles. Existing admin-managed active states and baseline-role customization are preserved; only Super Admin is always synchronized to all permissions.
- `DemoSeeder` contains the known demo users, password, ideas, and content. It runs only in `local` or `testing`, and can be disabled locally with `SEED_DEMO_DATA=false`.
- `DatabaseSeeder` is production-safe because production calls only master data. For explicitness, deployment commands target `MasterDataSeeder` directly.
- Production has no fixed admin account or password. Use `kolabo:create-super-admin` once after master seeding.

## SQLite development data

SQLite remains the local and PHPUnit default. The existing `database/database.sqlite` contains demo/E2E records and is not suitable as a production source. A fresh MySQL production deployment should start with migrations plus `MasterDataSeeder`.

If SQLite later contains legitimate business data, do not import its SQL dump into MySQL. Freeze writes, back it up, write a reviewed model-level ETL that preserves natural keys and relationships, rehearse it against a staging copy, compare row counts and foreign-key integrity, and only then schedule a production cutover.

## MySQL compatibility verification

Fast local tests remain on SQLite in-memory. The `MySQL compatibility` CI workflow provisions MySQL 8.4, executes a clean migration/seed, rolls the latest migration back and forward, runs the complete PHPUnit suite with `phpunit.mysql.xml`, and builds Vite assets. The XML file intentionally omits database overrides so CI credentials come only from environment variables.
