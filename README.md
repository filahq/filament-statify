# Statify

Export your Filament stat widgets as API.

## Installation

```bash
composer require filahq/statify
```

Statify's core package only requires Filament widgets.

If you also want the optional `StatifyPlugin` panel integration, install the full Filament package in your app:

```bash
composer require filament/filament
```

Publish the config:

```bash
php artisan vendor:publish --tag=statify-config
```

## Configuration

```php
// config/statify.php

return [
    'guard' => env('STATIFY_GUARD', 'token'), // "token" or "sanctum"
    'token' => env('STATIFY_TOKEN'),           // for "token" guard; null = open access
    'cache_ttl' => 60,                         // seconds
    'cache_prefix' => 'statify',
    'prefix' => 'api/statify',
];
```

## Usage

### Register Widgets

Register your `StatsOverviewWidget` classes in a service provider:

```php
use FilaHQ\Statify\Statify;

Statify::widgets([
    RevenueStatsWidget::class,
    UsersStatsWidget::class,
]);
```

Or, if your app has the full `filament/filament` package installed, use the optional Filament plugin in your panel provider:

```php
use FilaHQ\Statify\StatifyPlugin;

$panel->plugin(
    StatifyPlugin::make()->widgets([
        RevenueStatsWidget::class,
        UsersStatsWidget::class,
    ])
);
```

### API Endpoints

**Get all stats:**

```
GET /api/statify/stats
```

**Get stats from a specific widget:**

```
GET /api/statify/widgets/{widget-slug}
```

The widget slug is the kebab-case version of the class name (e.g., `RevenueStatsWidget` becomes `revenue-stats-widget`).

### Authentication

Statify supports two authentication modes, configured via `STATIFY_GUARD`:

#### Static Token (default)

Set `STATIFY_TOKEN` in your `.env` file:

```env
STATIFY_TOKEN=your-secret-token
```

Authenticate via query parameter or header:

```
GET /api/statify/stats?token=your-secret-token
```

```
Authorization: Bearer your-secret-token
```

If no token is configured, the API is open (no auth required).

#### Laravel Sanctum

Set the guard to Sanctum in your `.env`:

```env
STATIFY_GUARD=sanctum
```

Install and configure Laravel Sanctum in your application before using this mode.

Generate a personal access token for your user:

```php
$token = $user->createToken('statify')->plainTextToken;
```

Authenticate with the Sanctum token:

```
Authorization: Bearer {sanctum-token}
```

### Response Format

```json
{
  "responded_at": "2026-03-13T12:00:00+00:00",
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

## License

MIT
