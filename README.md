# Statify

Export your Filament stat widgets as a JSON API for dashboards and native apps.

## Installation

```bash
composer require filahq/statify
php artisan vendor:publish --tag=statify-config
```

Requires PHP 8.2+, Laravel 11.28 or 12, and Filament widgets 4 or 5. Full Filament panels and Laravel Sanctum are optional.

## Register widgets

Register `StatsOverviewWidget` classes in a service provider:

```php
use FilaHQ\Statify\Statify;

Statify::widgets([
    RevenueStatsWidget::class,
    'users' => UsersStatsWidget::class,
]);
```

Without an explicit key, the slug is the kebab-case class basename: `RevenueStatsWidget` becomes `revenue-stats-widget`. Explicit slugs stay stable when classes move or are renamed. Use lowercase letters, numbers, and hyphens. Duplicate classes are deduplicated; conflicting slugs, invalid classes, and attempts to register one class under different slugs throw an exception.

For the optional panel plugin, install `filament/filament` and register:

```php
use FilaHQ\Statify\StatifyPlugin;

$panel->plugin(StatifyPlugin::make()->widgets([
    'revenue' => RevenueStatsWidget::class,
]));
```

The API extracts `getStats()` from a newly constructed widget. Livewire `mount()`, dashboard filters, panel authorization, and Filament tenant initialization do not run automatically. Registered widgets must supply the API's query context themselves. Use the authorization hook below to enforce API access.

## Authentication

The default `token` guard requires a non-empty token:

```env
STATIFY_TOKEN=your-secret-token
```

Send `Authorization: Bearer your-secret-token`. Query tokens (`?token=...`) remain supported for compatibility; prefer the header to keep credentials out of URLs and access logs. The header takes precedence if both are provided.

For an intentionally public API, explicitly set:

```env
STATIFY_GUARD=public
```

Unknown guards, an unconfigured token guard, or an unavailable Sanctum guard return JSON with status `503`. Invalid credentials return `401`; failed authorization returns `403`.

### Optional Sanctum

Install and configure `laravel/sanctum` in the host application, then set:

```env
STATIFY_GUARD=sanctum
# Optional: require a particular token ability
STATIFY_ABILITY=statify:read
```

Generate a token from a user whose model uses Sanctum's `HasApiTokens` trait:

```php
$token = $user->createToken('statify', ['statify:read'])->plainTextToken;
```

Send it as `Authorization: Bearer {token}`. The authenticated Sanctum user becomes available through `auth()->user()` and `$request->user()` during widget extraction. Sanctum is not required in token or public mode. The ability check is disabled unless configured; use a policy callback when API access must also depend on the user's permissions.

### Application authorization

Register callbacks in a service provider's `boot()` method, so they also work with Laravel's config cache:

```php
use Illuminate\Http\Request;
use FilaHQ\Statify\Statify;

Statify::authorizeUsing(
    fn (Request $request): bool => $request->user()?->can('viewStats') === true,
);
```

The callback must return `true` to allow access. It runs after authentication and before every cache lookup. In static token and public modes there may be no user; callbacks for those modes should check the application's appropriate access context.

## Stable metric IDs and raw values

Native Filament stats continue to work. Use Filament's `id()` method to keep a metric ID stable across label changes and translations:

```php
use Filament\Widgets\StatsOverviewWidget\Stat;

Stat::make('Revenue Today', 2430)->id('revenue-today');
```

Without an explicit ID, Statify generates one from the label. IDs must be non-empty and unique within a widget. The same metric ID can be used in different widgets; clients should identify a metric by its widget slug and metric ID together.

For localized or abbreviated display values, use Statify's optional `Stat` subclass:

```php
use FilaHQ\Statify\Stat;

Stat::make('Revenue Today', 'RM -1,234.56')
    ->id('revenue-today')
    ->rawValue(-1234.56);

Stat::make('Visitors', '1.2K')->id('visitors')->rawValue(1200);
Stat::make('Display Only', '42')->id('display-only')->rawValue(null);
```

The JSON `value` remains a string. Numeric PHP inputs retain their numeric value in `raw_value`. Automatic parsing supports complete decimal-point numbers, US-style comma grouping, an optional currency symbol, and percentages. For example, `.5` becomes `0.5` and `12%` becomes `12`, not `0.12`. Currency-code text such as `RM -1,234.56` or `USD 1,234`, abbreviations such as `1.2K`, localized formats such as `€1.234,56`, ratios, accounting parentheses, or HTML produce `null`; provide `rawValue()` for these values. Explicit raw floats must be finite. JSON numbers have the usual client-side precision limits; keep exact high-precision amounts in the display string.

## Caching and tenant context

Stats are cached for 60 seconds by default. Cache keys include the authenticated user, request token, guard, application locale, and registered widgets. Tokens are included only inside the cache key's SHA-256 digest. The all-stats and individual-widget endpoints have separate caches.

For tenant-specific widgets, add the same tenant context used by your widget queries:

```php
Statify::cacheScopeUsing(
    fn (Request $request): ?int => $request->user()?->current_team_id,
);
```

The callback adds context to the user/token scope; it does not replace it or initialize a Filament tenant. Return `null` to bypass caching when the required context is unavailable. Include any other context that changes results, such as a selected reporting period or permission revision. Resolve tenant selection from trusted application state and enforce tenant authorization in the host app.

Set `STATIFY_CACHE_TTL=0` to disable caching. Negative TTLs also bypass the cache. Set a different `STATIFY_CACHE_PREFIX` for apps sharing a cache store. `generated_at` is the time the returned stats were extracted; `responded_at` is the time of the current response. Both endpoints may return data up to the configured TTL old.

## Configuration

```php
// config/statify.php
return [
    'guard' => env('STATIFY_GUARD', 'token'), // token, sanctum, or public
    'token' => env('STATIFY_TOKEN'),
    'ability' => env('STATIFY_ABILITY'),     // optional Sanctum token ability
    'cache_ttl' => env('STATIFY_CACHE_TTL', 60),
    'cache_prefix' => env('STATIFY_CACHE_PREFIX', 'statify'),
    'prefix' => env('STATIFY_PREFIX', 'api/statify'),
    'widget_names' => [
        'revenue-stats-widget' => 'Revenue Overview',
    ],
];
```

Set `prefix` to `null`, `false`, or an empty string to disable route registration. Widget names fall back to a readable version of their slug. Add application-specific throttling through the host application's middleware where needed.

## API endpoints

- `GET /api/statify/stats` returns all registered widgets' metrics.
- `GET /api/statify/widgets/{widget-slug}` returns one widget, or `404` for an unknown slug.

Example all-stats response:

```json
{
  "schema_version": 1,
  "responded_at": "2026-09-22T12:00:30+00:00",
  "generated_at": "2026-09-22T12:00:00+00:00",
  "widgets": [
    {"id": "revenue-stats-widget", "name": "Revenue Overview"}
  ],
  "stats": [
    {
      "widget": "revenue-stats-widget",
      "id": "revenue-today",
      "label": "Revenue Today",
      "value": "$2,430",
      "raw_value": 2430,
      "description": "+12%",
      "icon": null,
      "color": "success",
      "chart": null
    }
  ]
}
```

The single-widget response includes a top-level `widget` slug, the same metadata fields, and `stats` without the per-row `widget` field, preserving the existing contract. The `widgets` metadata includes registered widgets even if they currently have no metrics. Schema version `1` adds metadata to the original response shape; clients should ignore unknown fields.

## Upgrading from 0.2.x

- **Authentication:** missing tokens no longer mean public access. Set a non-empty `STATIFY_TOKEN`, configure Sanctum, or deliberately set `STATIFY_GUARD=public`.
- **Published config:** merge `ability` and `widget_names` if using them. Rebuild your application's config cache after changing environment settings.
- **Identifiers:** fix duplicate widget slugs and duplicate/empty metric IDs. Explicit IDs and slugs are optional for existing unique identifiers. Existing Filament `->id()` values now take precedence over generated label IDs. Changing a slug or ID changes client identity; retain current identifiers or migrate saved selections.
- **Raw values:** formatted values outside the documented parser formats now return `null`. Use `FilaHQ\Statify\Stat::rawValue()` to supply their intended numeric value.
- **Caches:** cache keys now use a separate namespace and identity scope, so existing cached responses are not reused. Add a context callback for tenant-specific data.
- **Clients:** existing `stats`, `widget`, `value`, and `responded_at` fields retain their shape. New metadata is additive.

## Development

Tests run independently of any host application using Orchestra Testbench:

```bash
composer install
composer test
composer lint
```

The suite includes real Sanctum bearer-token tests, cache isolation/expiry, auth configuration, numeric parsing, and ID validation. CI covers PHP 8.2–8.4, Laravel 11/12, Filament 4/5, and a widgets-only installation without Sanctum. Sanctum is a development dependency for these tests, not a production requirement.

Laravel 11 compatibility jobs allow its known framework advisories during dependency resolution in the isolated test runner. This does not change the package's Composer security defaults or certify Laravel 11 for production use.

## License

MIT
