# Halo Race Leaderboard

HRL is a rebuild of the public [Halo Race Leaderboard](https://hrl.effakt.info), preserving its production data while replacing the legacy application with Laravel 13, Livewire 4, and Tailwind CSS 4.

The frontend design is complete. Real-data integration is underway; see [docs/roadmap.md](docs/roadmap.md) for the current page-by-page status.

## Stack

- PHP 8.3+ and Laravel 13
- Livewire 4 and Alpine.js
- Tailwind CSS 4 and Vite
- MySQL in development/production; SQLite in tests
- Pest, Pint, PHPStan/Larastan, and Rector

## Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve
```

Configure the database in `.env`. For a fresh empty database, run `php artisan migrate`. **Do not run all migrations blindly against an imported HRL database**: it already contains tables that overlap with the Laravel scaffold. See [docs/database.md](docs/database.md) first.

## Checks

```bash
vendor/bin/pint --dirty --format agent
composer analyse
php artisan test --compact
npm run build
```

## Project documentation

Start with [AGENTS.md](AGENTS.md), which links the full documentation set. The most useful working documents are:

- [docs/roadmap.md](docs/roadmap.md) — completed work, next tasks, and open questions
- [docs/decisions.md](docs/decisions.md) — chronological decisions, reversals, and bug fixes
- [docs/database.md](docs/database.md) — real schema and data constraints
- [docs/coding-standards.md](docs/coding-standards.md) — project-specific conventions
- [docs/livewire-guide.md](docs/livewire-guide.md) — Livewire and Alpine patterns and gotchas

Secrets belong in `.env`, which is excluded from Git. Never commit production credentials or database exports.
