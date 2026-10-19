# VEMS deployment checklist

For setting up VEMS on a new server from scratch.

## 1. Requirements

- PHP 8.3+ (Laravel 13), with the `pdo_mysql`, `mbstring`, `gd`, `zip` and `fileinfo` extensions
- MySQL 8 (and `mysqldump` on the PATH, for backups)
- Composer, plus Node.js 20+ to build the frontend
- A web server (Nginx/Apache) whose document root is `public/`

## 2. Environment (`.env`)

Copy `.env.example` to `.env`, then set at least:

| Setting | Value |
|---|---|
| `APP_NAME` | `VEMS` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (never `true` on a live server: it shows error details to users) |
| `APP_URL` | The real address, e.g. `https://vems.example.org` |
| `LOG_LEVEL` | `warning` |
| `DB_*` | The production database |
| `SESSION_SECURE_COOKIE` | `true` when the site runs on HTTPS |
| `QUEUE_CONNECTION` | `database` (needs the queue worker in step 5) |
| `MAIL_*` | A real mail server (needed for password resets and backup alerts) |
| `BACKUP_NOTIFY_EMAIL` | Who gets an email when a backup fails |

## 3. Install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # production: roles, permissions, zones and departments; no demo data
php artisan vems:create-admin      # the first super-admin (asks for name, email, password)
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`storage/` and `bootstrap/cache/` must be writable by the web server user.

Then log in as the admin and set up departments, zones, users and their roles.

## 4. Scheduler (cron)

Daily jobs (all times Dhaka):

| Time | Job |
|---|---|
| 01:00–03:00 | Backups: clean old copies, back up, check health |
| 07:00 | Expiry reminders (vehicle documents, driving licences) |
| 07:30 | Trips needing attention (overdue or long-running) |

They only run if the server calls Laravel's scheduler every minute.

**Linux** (`crontab -e` for the web server user):

```
* * * * * cd /path/to/vems && php artisan schedule:run >> /dev/null 2>&1
```

**Windows:** a Task Scheduler task that runs `php artisan schedule:run` in the project folder every minute.

## 5. Queue worker

Attendance location lookups run on the queue. Keep a worker running:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

On Linux, run it under Supervisor (or systemd) so it restarts automatically. After each deploy, run `php artisan queue:restart` so the worker picks up the new code.

## 6. Backups

- Each night the database plus `storage/app/private` (NID, licence, vehicle and vendor documents) and `storage/app/public` (photos) are zipped into `storage/app/backups`.
- The last 7 days are kept in full, then fewer as they age.
- **Copy backups off the server too** (another machine or cloud storage). A backup on the same disk is lost if that disk fails.
- Check the backups are working: `php artisan backup:list`
- Make a backup right away: `php artisan backup:run`

## 7. Updating to a new version

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan permissions:update     # adds new permissions/roles without touching your custom ones
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Use `php artisan permissions:update --dry-run` first to see what it would change. Don't run `setup:permissions` on a live system: it resets roles to the defaults and deletes custom roles.
