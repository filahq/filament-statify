# Statify

Export your Filament stat widgets as API.

## Installation

```bash
composer require filahq/statify
```

Publish the config:

```bash
php artisan vendor:publish --tag=statify-config
```

## Configuration

```php
// config/statify.php

return [
    'token' => env('STATIFY_TOKEN'),  // null = open access
    'cache_ttl' => 60,                // seconds
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

Or use the Filament plugin in your panel provider:

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

Set `STATIFY_TOKEN` in your `.env` file to enable token authentication:

```env
STATIFY_TOKEN=your-secret-token
```

Then authenticate via query parameter or header:

```
GET /api/statify/stats?token=your-secret-token
```

```
Authorization: Bearer your-secret-token
```

### Response Format

```json
{
  "generated_at": "2026-03-13T12:00:00+00:00",
  "widgets": [
    {
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
