# Statify development

Statify exports Filament `StatsOverviewWidget` classes as JSON endpoints. Work from this package directory; no external Laravel application is required.

## Commands

```bash
composer install
composer test
composer lint
vendor/bin/pint
```

Tests use Orchestra Testbench with in-memory SQLite and the array cache store. Sanctum is an optional production dependency and a development dependency for real bearer-token tests. Keep token/public mode usable without Sanctum or Filament panels installed.

## Architecture

- `Statify` registers widgets, application authorization, and cache context callbacks.
- `WidgetRegistry` validates classes, slugs, and unique widget identities.
- `AuthenticateStatify` handles explicit public, static token, and optional Sanctum authentication before authorization.
- `WidgetStatExtractor` constructs a widget and invokes `getStats()`; it does not run Livewire lifecycle hooks.
- `StatData` maps native Filament stats to API data. `Stat` extends Filament's stat with an explicit optional raw numeric value.
- `StatsController` isolates cached data by identity and application context, validates metric IDs, and returns schema/freshness/widget metadata.

Preserve the documented JSON response fields for existing native clients. Document security-related migration steps in README and CHANGELOG. Never infer numeric values from arbitrary substrings of formatted text.
