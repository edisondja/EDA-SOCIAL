# AGENTS.md

## Cursor Cloud specific instructions

### Product

Single **Laravel 10** app at the repo root (**EDA_SOCIAL**): Blade web UI + JSON API under `/api`. There is no `backend/` subdirectory in this checkout (README may still mention it).

### System prerequisites (not in update script)

- **PHP 8.4+** — `composer.lock` pulls `symfony/css-selector` v8, which requires PHP ≥ 8.4. Ubuntu 24.04 default PHP 8.3 is insufficient; use [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) (`php8.4` + extensions: `cli`, `mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `tokenizer`, `gd`).
- **MySQL 8** — app default `DB_CONNECTION=mysql`. Start with `sudo service mysql start` if the daemon is not running.
- **Composer** — install PHP deps from repo root.
- **Node/npm** — optional unless editing CSS; compiled assets are already under `public/`.

### First-time setup (once per VM)

From `/workspace`:

```bash
cp .env.example .env
php artisan key:generate
# Set DB_* in .env, create DB/user, then:
php artisan migrate
php artisan storage:link
php artisan db:seed   # optional: admin user + 3 demo videos
php artisan serve --host=0.0.0.0 --port=8000
```

Seeded admin (from `AdminUserSeeder`): `graned@eda.social` / `Meteoro2412`.

### Commands (repo root)

| Task | Command |
|------|---------|
| Dev server | `php artisan serve --host=0.0.0.0 --port=8000` |
| Lint | `./vendor/bin/pint` (CI-style check: `./vendor/bin/pint --test`) |
| Tests | `./vendor/bin/phpunit` |
| Assets (optional) | `npm install` then `npm run dev` or `npm run production` |

### Gotchas

- **PHPUnit** `tests/Feature/ExampleTest` hits `/` and expects HTTP 200; the app redirects `/` → `/explorar` (302). Failure is a test/app mismatch, not a broken environment.
- **Pint `--test`** may report many style diffs on the current tree; that reflects existing formatting debt, not a failed install.
- **Queues** default to `sync` in `.env.example`; no worker required for basic web/API flows.
- **FFmpeg** is optional for full upload/transcode pipelines; demo seed uses remote sample URLs.
- Do not commit `.env` (gitignored).

### API smoke check

`curl -s http://127.0.0.1:8000/api/videos` should return JSON with seeded demo posts after `db:seed`.
