# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Package Does

**Statify** is a Laravel package that exposes Filament `StatsOverviewWidget` widgets as a RESTful JSON API. It registers widgets via a registry, extracts their stats using PHP Reflection, and serves them through two authenticated API endpoints with optional caching.

## Commands

```bash
# Run all tests
php artisan test --compact

# Run a specific test file or filter
php artisan test --compact --filter=TestName

# Fix code style
vendor/bin/pint --dirty --format agent
```

## API Endpoints

Both endpoints are protected by token-based auth (query param `?token=` or `Authorization` header).

- `GET /api/statify/stats` — All registered widget stats
- `GET /api/statify/widgets/{widget}` — Stats for a specific widget by slug

Configured via `config/statify.php`:
- `statify.token` — API token (`null` = open access)
- `statify.cache_ttl` — Cache lifetime in seconds (default: 60)
- `statify.prefix` — Route prefix (default: `api/statify`)

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
| `src/Registry/WidgetRegistry.php` | In-memory widget class registry |
| `src/Extractors/WidgetStatExtractor.php` | Reflection-based `getStats()` invoker |
| `src/Support/StatData.php` | DTO: converts Filament `Stat` → JSON-serializable array |
| `src/Http/Controllers/StatsController.php` | API endpoints with caching |
| `src/Http/Middleware/AuthenticateStatify.php` | Token auth (`hash_equals` for timing-safe comparison) |
| `config/statify.php` | Package configuration |

### StatData Transformation

`StatData::fromStat()` converts a Filament `Stat` object and extracts:
- `id` — slug from label
- `label`, `value`, `description`, `color`, `chart`
- `icon` — converts `Heroicon` enum to string
- `raw_value` — strips formatting from value string via regex to get a numeric value
