# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Package Does

**Statify** is a Laravel package that exposes Filament `StatsOverviewWidget` widgets as a RESTful JSON API. It registers widgets via a registry, extracts their stats using PHP Reflection, and serves them through two authenticated API endpoints with optional caching.

## Commands

**Run from the parent app root** (`/Volumes/DevDisk/code/filament5/`), not the package root.

```bash
# Run all tests
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=Statify

# Run a specific test file or filter
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=TestName

# Fix code style
cd /Volumes/DevDisk/code/filament5 && vendor/bin/pint packages/statify/src/... --format agent
```

## API Endpoints

- `GET /api/statify/stats` — All registered widget stats
- `GET /api/statify/widgets/{widget}` — Stats for a specific widget by slug

Configured via `config/statify.php`:
- `statify.guard` — Auth mode: `token` (default) or `sanctum`
- `statify.token` — Static API token for `token` guard (`null` = open access)
- `statify.cache_ttl` — Cache lifetime in seconds (default: 60, `0` = no cache)
- `statify.cache_prefix` — Cache key prefix (default: `statify`)
- `statify.prefix` — Route prefix (default: `api/statify`, `null`/`false` = routes disabled)

## Authentication

Two modes via `STATIFY_GUARD` env var:

- **`token`** (default): Static token via `STATIFY_TOKEN`. Accepts `?token=` query param or `Authorization: Bearer` header. No token configured = open access.
- **`sanctum`**: Laravel Sanctum personal access tokens via `Authorization: Bearer` header.

## Architecture

### Registration Flow

1. Consumer registers widgets via `Statify::widgets([MyWidget::class])` or via `StatifyPlugin::make()->widgets([...])` in a Filament panel
2. `WidgetRegistry` (singleton) stores the class names
3. `StatifyServiceProvider` boots routes and registers the registry

### Request Flow

`StatsController` → `WidgetRegistry` (get classes) → `WidgetStatExtractor` (PHP Reflection calls `getStats()` on widget instance) → `StatData::fromStat()` (transforms Filament `Stat` objects to arrays) → cached JSON response

### Key Files

| File | Purpose |
|------|---------|
| `src/Statify.php` | Facade / entry point |
| `src/StatifyPlugin.php` | Filament plugin contract implementation |
| `src/StatifyServiceProvider.php` | Route registration, config merge, singleton binding |
| `src/Registry/WidgetRegistry.php` | In-memory widget class registry (with `flush()` for testing) |
| `src/Extractors/WidgetStatExtractor.php` | Reflection-based `getStats()` invoker |
| `src/Support/StatData.php` | DTO: converts Filament `Stat` → JSON-serializable array |
| `src/Http/Controllers/StatsController.php` | API endpoints with caching |
| `src/Http/Middleware/AuthenticateStatify.php` | Auth middleware (static token or Sanctum) |
| `config/statify.php` | Package configuration |

### StatData Transformation

`StatData::fromStat()` converts a Filament `Stat` object and extracts:
- `id` — slug from label via `Str::slug()`
- `label`, `value`, `description`, `color`, `chart`
- `icon` — converts `Heroicon` enum to string
- `raw_value` — strips formatting from value string via regex to get a numeric value

### Testing Notes

- **Tests live in the parent app** at `/Volumes/DevDisk/code/filament5/tests/Feature/Statify/` and `tests/Unit/Statify/` — the package has no test infrastructure of its own
- **Two git repos**: commit package source from `/Volumes/DevDisk/code/filament5/packages/statify`; test files belong to the parent app repo and must be committed separately
- Tests must call `WidgetRegistry::flush()` in `beforeEach` to clear widgets registered by the app's `AdminPanelProvider`
- Sanctum tests use `Sanctum::actingAs()` with `User::factory()->make()` (no DB needed)
