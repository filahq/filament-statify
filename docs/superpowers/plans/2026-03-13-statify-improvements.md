# Statify Improvements Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply 10 targeted improvements to the Statify package covering correctness, API clarity, configurability, and robustness.

**Architecture:** Changes are isolated across five files: `WidgetRegistry` (slug + dedup), `StatsController` (response shape + cache), `StatData` (regex), `config/statify.php` (new keys), and `StatifyServiceProvider` (route opt-out). All existing tests must be updated to reflect the new response shape (`responded_at`, `stats` key, `widget` field per stat).

**Tech Stack:** PHP 8.3, Laravel 12, Filament 5, Pest 4

**Spec:** `docs/superpowers/specs/2026-03-13-statify-improvements-design.md`

**Tests live in:** `/Volumes/DevDisk/code/filament5/tests/` (parent app)
**Run tests from:** `/Volumes/DevDisk/code/filament5/`
**Run command:** `php artisan test --compact --filter=Statify`
**Pint:** `vendor/bin/pint --dirty --format agent` (run from `/Volumes/DevDisk/code/filament5/`)

---

## Chunk 1: WidgetRegistry — slug + deduplication

### Task 1: Add `getSlug()` and keyed deduplication to `WidgetRegistry`

**Files:**
- Modify: `src/Registry/WidgetRegistry.php`
- Create: `/Volumes/DevDisk/code/filament5/tests/Unit/Statify/WidgetRegistryTest.php`

- [ ] **Step 1: Create the failing unit test**

Create `/Volumes/DevDisk/code/filament5/tests/Unit/Statify/WidgetRegistryTest.php`:

```php
<?php

use FilaHQ\Statify\Registry\WidgetRegistry;

it('deduplicates widgets registered multiple times', function () {
    $registry = new WidgetRegistry();
    $registry->register([\stdClass::class]);
    $registry->register([\stdClass::class]);

    expect($registry->getWidgets())->toHaveCount(1);
});

it('generates a kebab-case slug from a widget class name', function () {
    $registry = new WidgetRegistry();

    expect($registry->getSlug('App\Filament\Widgets\RevenueStatsWidget'))
        ->toBe('revenue-stats-widget');
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=WidgetRegistry
```

Expected: FAIL — `getSlug` method not found, deduplication test may pass already (via `array_unique`).

- [ ] **Step 3: Update `WidgetRegistry`**

Replace the full contents of `src/Registry/WidgetRegistry.php`:

```php
<?php

namespace FilaHQ\Statify\Registry;

use Illuminate\Support\Str;

class WidgetRegistry
{
    /** @var array<class-string, class-string> */
    protected array $widgets = [];

    /**
     * @param  array<int, class-string>  $widgets
     */
    public function register(array $widgets): void
    {
        foreach ($widgets as $widget) {
            $this->widgets[$widget] = $widget;
        }
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return array_values($this->widgets);
    }

    public function getSlug(string $widgetClass): string
    {
        return Str::kebab(class_basename($widgetClass));
    }

    public function flush(): void
    {
        $this->widgets = [];
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=WidgetRegistry
```

Expected: PASS (2 tests).

- [ ] **Step 5: Run pint**

```bash
cd /Volumes/DevDisk/code/filament5 && vendor/bin/pint packages/statify/src/Registry/WidgetRegistry.php --format agent
```

- [ ] **Step 6: Commit**

```bash
cd /Volumes/DevDisk/code/filament5 && git add packages/statify/src/Registry/WidgetRegistry.php tests/Unit/Statify/WidgetRegistryTest.php && git commit -m "feat: add getSlug() and keyed deduplication to WidgetRegistry"
```

---

## Chunk 2: Config + ServiceProvider — new keys and route opt-out

### Task 2: Add `cache_prefix`, make `cache_ttl` env-driven, add `STATIFY_PREFIX` env, skip routes if prefix falsy

**Files:**
- Modify: `config/statify.php`
- Modify: `src/StatifyServiceProvider.php`
- Create: `/Volumes/DevDisk/code/filament5/tests/Feature/Statify/StatifyConfigTest.php`

- [ ] **Step 1: Write failing tests**

Create `/Volumes/DevDisk/code/filament5/tests/Feature/Statify/StatifyConfigTest.php`:

```php
<?php

it('reads cache_ttl from config', function () {
    config(['statify.cache_ttl' => 120]);

    expect(config('statify.cache_ttl'))->toBe(120);
});

it('reads cache_prefix from config', function () {
    config(['statify.cache_prefix' => 'myapp']);

    expect(config('statify.cache_prefix'))->toBe('myapp');
});

it('has a default cache_prefix of statify', function () {
    // Ensure the default is set (config was published/merged)
    expect(config('statify.cache_prefix'))->toBe('statify');
});

it('has a default cache_ttl of 60', function () {
    expect(config('statify.cache_ttl'))->toBe(60);
});
```

> Note: The route opt-out test cannot be written as a feature test because routes are registered at boot time before any test config overrides. The guard is verified by manual inspection (`php artisan route:list`) when `STATIFY_PREFIX` is empty.

- [ ] **Step 2: Run tests to confirm they fail**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=StatifyConfig
```

Expected: `cache_prefix` tests fail (key doesn't exist yet).

- [ ] **Step 3: Update `config/statify.php`**

Replace the full contents of `config/statify.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication method for the Statify API.
    |
    | Supported: "token", "sanctum"
    |
    | - "token": Uses a static token from STATIFY_TOKEN env var.
    | - "sanctum": Uses Laravel Sanctum personal access tokens.
    |
    */
    'guard' => env('STATIFY_GUARD', 'token'),

    /*
    |--------------------------------------------------------------------------
    | API Token (for "token" guard)
    |--------------------------------------------------------------------------
    |
    | The static token used to authenticate API requests.
    | If null and guard is "token", the API is open (no auth).
    |
    */
    'token' => env('STATIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache the stats response.
    | Set to 0 to disable caching entirely.
    |
    */
    'cache_ttl' => env('STATIFY_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all cache keys used by Statify.
    | Change this if multiple apps share the same cache store.
    |
    */
    'cache_prefix' => env('STATIFY_CACHE_PREFIX', 'statify'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for the Statify API routes.
    | Set to null or false (STATIFY_PREFIX=) to disable route registration.
    |
    */
    'prefix' => env('STATIFY_PREFIX', 'api/statify'),

];
```

- [ ] **Step 4: Update `registerRoutes()` in `src/StatifyServiceProvider.php`**

Replace the existing `registerRoutes()` method (currently reads `Route::prefix(config('statify.prefix', 'api/statify'))...`) with:

```php
protected function registerRoutes(): void
{
    $prefix = config('statify.prefix', 'api/statify');

    if (! $prefix) {
        return;
    }

    Route::prefix($prefix)
        ->middleware(AuthenticateStatify::class)
        ->group(function () {
            Route::get('/stats', [StatsController::class, 'index'])->name('statify.stats.index');
            Route::get('/widgets/{widget}', [StatsController::class, 'show'])->name('statify.stats.show');
        });
}
```

> Note: Route registration happens at boot time, so testing the null-prefix opt-out via a feature test is not reliable. After this change, verify manually: set `STATIFY_PREFIX=` (empty string) in `.env`, run `php artisan route:list`, and confirm no `statify.*` routes appear.

- [ ] **Step 5: Run config tests**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=StatifyConfig
```

Expected: PASS (4 tests).

> Do NOT run `--filter=StatsEndpoint` here — those tests still expect the old response shape (`generated_at`, `widgets` key) and will fail until Chunk 4 updates the controller. That is expected at this stage.

- [ ] **Step 6: Run pint**

```bash
cd /Volumes/DevDisk/code/filament5 && vendor/bin/pint packages/statify/config/statify.php packages/statify/src/StatifyServiceProvider.php --format agent
```

- [ ] **Step 7: Commit**

```bash
cd /Volumes/DevDisk/code/filament5 && git add packages/statify/config/statify.php packages/statify/src/StatifyServiceProvider.php tests/Feature/Statify/StatifyConfigTest.php && git commit -m "feat: add cache_prefix config, env-driven cache_ttl, and route opt-out via null prefix"
```

---

## Chunk 3: StatData — fix extractRawValue regex

### Task 3: Fix `extractRawValue` to correctly handle negative formatted numbers

**Files:**
- Modify: `src/Support/StatData.php`
- Create: `/Volumes/DevDisk/code/filament5/tests/Unit/Statify/StatDataTest.php`

- [ ] **Step 1: Create the failing unit test**

Create `/Volumes/DevDisk/code/filament5/tests/Unit/Statify/StatDataTest.php`:

```php
<?php

use FilaHQ\Statify\Support\StatData;
use Filament\Widgets\StatsOverviewWidget\Stat;

it('extracts raw value from a plain integer string', function () {
    $stat = Stat::make('Users', '42');
    expect(StatData::fromStat($stat)->rawValue)->toBe(42);
});

it('extracts raw value from a formatted currency string', function () {
    $stat = Stat::make('Revenue', '$2,430');
    expect(StatData::fromStat($stat)->rawValue)->toBe(2430);
});

it('extracts raw value from a negative formatted string', function () {
    $stat = Stat::make('Loss', '-$1,234.56');
    expect(StatData::fromStat($stat)->rawValue)->toBe(-1234.56);
});

it('returns null for a non-numeric string', function () {
    $stat = Stat::make('Status', 'N/A');
    expect(StatData::fromStat($stat)->rawValue)->toBeNull();
});

it('extracts raw value from a float string', function () {
    $stat = Stat::make('Rate', '3.14');
    expect(StatData::fromStat($stat)->rawValue)->toBe(3.14);
});
```

- [ ] **Step 2: Run tests to confirm the negative case fails**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=StatData
```

Expected: "extracts raw value from a negative formatted string" FAILS.

- [ ] **Step 3: Fix `extractRawValue` in `src/Support/StatData.php`**

Replace only the `extractRawValue` method (lines ~49–62):

```php
protected static function extractRawValue(string $value): int|float|null
{
    // Strip thousands separators and whitespace
    $cleaned = preg_replace('/[,_\s]/', '', $value);

    // Extract a leading signed number (integer or decimal)
    if (! preg_match('/^-?\d*\.?\d+/', $cleaned, $matches)) {
        return null;
    }

    $number = $matches[0];

    if (str_contains($number, '.')) {
        return (float) $number;
    }

    return (int) $number;
}
```

- [ ] **Step 4: Run tests to confirm all pass**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=StatData
```

Expected: PASS (5 tests).

- [ ] **Step 5: Run pint**

```bash
cd /Volumes/DevDisk/code/filament5 && vendor/bin/pint packages/statify/src/Support/StatData.php --format agent
```

- [ ] **Step 6: Commit**

```bash
cd /Volumes/DevDisk/code/filament5 && git add packages/statify/src/Support/StatData.php tests/Unit/Statify/StatDataTest.php && git commit -m "fix: correct extractRawValue regex to handle negative formatted numbers"
```

---

## Chunk 4: StatsController — response shape, cache prefix, TTL=0, null guard

### Task 4: Update StatsController and all endpoint tests

**Files:**
- Modify: `src/Http/Controllers/StatsController.php`
- Modify: `/Volumes/DevDisk/code/filament5/tests/Feature/Statify/StatsEndpointTest.php`

> **Order:** Write the new controller first (Step 1), then replace the test file (Step 2) and run. This keeps the suite working throughout — the old tests will fail once the test file is replaced (Step 3), confirming the red phase, and pass after controller is already in place.

- [ ] **Step 1: Rewrite `src/Http/Controllers/StatsController.php`**

Replace the full file:

```php
<?php

namespace FilaHQ\Statify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use FilaHQ\Statify\Extractors\WidgetStatExtractor;
use FilaHQ\Statify\Registry\WidgetRegistry;
use FilaHQ\Statify\Support\StatData;

class StatsController
{
    public function __construct(
        protected WidgetRegistry $registry,
        protected WidgetStatExtractor $extractor,
    ) {}

    public function index(): JsonResponse
    {
        $ttl = (int) config('statify.cache_ttl', 60);
        $prefix = config('statify.cache_prefix', 'statify');

        $data = $ttl === 0
            ? $this->getAllStats()
            : Cache::remember("{$prefix}:stats:all", $ttl, fn () => $this->getAllStats());

        return response()->json([
            'responded_at' => now()->toIso8601String(),
            'stats' => $data,
        ]);
    }

    public function show(string $widget): JsonResponse
    {
        $widgetClass = $this->resolveWidgetClass($widget);

        if ($widgetClass === null) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $ttl = (int) config('statify.cache_ttl', 60);
        $prefix = config('statify.cache_prefix', 'statify');

        $data = $ttl === 0
            ? $this->extractStats($widgetClass)
            : Cache::remember("{$prefix}:stats:{$widget}", $ttl, fn () => $this->extractStats($widgetClass));

        return response()->json([
            'responded_at' => now()->toIso8601String(),
            'widget' => $widget,
            'stats' => $data,
        ]);
    }

    protected function resolveWidgetClass(string $widgetSlug): ?string
    {
        foreach ($this->registry->getWidgets() as $widgetClass) {
            if ($this->registry->getSlug($widgetClass) === $widgetSlug) {
                return $widgetClass;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAllStats(): array
    {
        $results = [];

        foreach ($this->registry->getWidgets() as $widgetClass) {
            $slug = $this->registry->getSlug($widgetClass);

            foreach ($this->extractor->extract($widgetClass) as $stat) {
                $results[] = ['widget' => $slug] + StatData::fromStat($stat)->toArray();
            }
        }

        return $results;
    }

    /**
     * @param  class-string  $widgetClass
     * @return array<int, array<string, mixed>>
     */
    protected function extractStats(string $widgetClass): array
    {
        return array_map(
            fn ($stat) => StatData::fromStat($stat)->toArray(),
            $this->extractor->extract($widgetClass),
        );
    }
}
```

- [ ] **Step 2: Run existing endpoint tests to confirm they still pass with new controller**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=StatsEndpoint
```

Expected: some tests pass, some fail (e.g. `generated_at` assertions) — this is expected. The controller is correct; the tests are still expecting the old shape.

- [ ] **Step 3: Replace the endpoint test file**

Replace the full contents of `/Volumes/DevDisk/code/filament5/tests/Feature/Statify/StatsEndpointTest.php`:

```php
<?php

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use FilaHQ\Statify\Registry\WidgetRegistry;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $registry = app(WidgetRegistry::class);
    $registry->flush();
    $registry->register([TestStatsWidget::class]);
});

it('returns all stats as json with responded_at and stats key', function () {
    $this->getJson('/api/statify/stats')
        ->assertSuccessful()
        ->assertJsonStructure([
            'responded_at',
            'stats' => [
                '*' => ['widget', 'id', 'label', 'value', 'raw_value', 'description', 'icon', 'color'],
            ],
        ])
        ->assertJsonPath('stats.0.widget', 'test-stats-widget')
        ->assertJsonPath('stats.0.label', 'Revenue Today')
        ->assertJsonPath('stats.0.raw_value', 2430)
        ->assertJsonPath('stats.1.label', 'New Users');
});

it('returns stats for a specific widget', function () {
    $this->getJson('/api/statify/widgets/test-stats-widget')
        ->assertSuccessful()
        ->assertJsonPath('widget', 'test-stats-widget')
        ->assertJsonPath('stats.0.label', 'Revenue Today');
});

it('returns 404 for unknown widget', function () {
    $this->getJson('/api/statify/widgets/nonexistent')
        ->assertNotFound();
});

it('does not cache a 404 — a newly registered widget slug is immediately accessible', function () {
    // First, confirm the slug doesn't exist
    $this->getJson('/api/statify/widgets/fresh-widget')->assertNotFound();

    // Register a widget whose slug is "fresh-widget"
    app(WidgetRegistry::class)->register([FreshWidget::class]);

    // Should now be found (not served from a cached null)
    $this->getJson('/api/statify/widgets/fresh-widget')->assertSuccessful();
});

it('bypasses cache when cache_ttl is 0', function () {
    config(['statify.cache_ttl' => 0, 'statify.cache_prefix' => 'statify']);

    $this->getJson('/api/statify/stats')
        ->assertSuccessful()
        ->assertJsonPath('stats.0.label', 'Revenue Today');

    // Cache key must NOT exist — confirms Cache::remember was not called
    expect(cache()->has('statify:stats:all'))->toBeFalse();
});

it('uses the configured cache_prefix in cache keys', function () {
    config(['statify.cache_prefix' => 'custom', 'statify.cache_ttl' => 60]);

    // First call populates cache under 'custom:stats:all'
    $this->getJson('/api/statify/stats')->assertSuccessful();

    expect(cache()->has('custom:stats:all'))->toBeTrue();
    expect(cache()->has('statify:stats:all'))->toBeFalse();
});

it('blocks requests without token when token is configured', function () {
    config(['statify.token' => 'secret-token']);

    $this->getJson('/api/statify/stats')
        ->assertUnauthorized();
});

it('allows requests with valid query token', function () {
    config(['statify.token' => 'secret-token']);

    $this->getJson('/api/statify/stats?token=secret-token')
        ->assertSuccessful();
});

it('allows requests with valid bearer token', function () {
    config(['statify.token' => 'secret-token']);

    $this->getJson('/api/statify/stats', ['Authorization' => 'Bearer secret-token'])
        ->assertSuccessful();
});

it('registers widgets via registry', function () {
    expect(app(WidgetRegistry::class)->getWidgets())->toContain(TestStatsWidget::class);
});

it('extracts raw numeric value from formatted string', function () {
    $this->getJson('/api/statify/stats')
        ->assertSuccessful()
        ->assertJsonPath('stats.0.value', '$2,430')
        ->assertJsonPath('stats.0.raw_value', 2430);
});

it('blocks requests without sanctum token when guard is sanctum', function () {
    config(['statify.guard' => 'sanctum']);

    $this->getJson('/api/statify/stats')
        ->assertUnauthorized();
});

it('allows requests with valid sanctum token', function () {
    config(['statify.guard' => 'sanctum']);

    Sanctum::actingAs(User::factory()->make());

    $this->getJson('/api/statify/stats')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Test widgets
// ---------------------------------------------------------------------------

class TestStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Revenue Today', '$2,430')
                ->description('+12%')
                ->color('success'),
            Stat::make('New Users', '38')
                ->description('+5 today')
                ->color('primary'),
        ];
    }
}

class FreshWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [Stat::make('Fresh Metric', '1')];
    }
}
```

- [ ] **Step 4: Run all Statify tests**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=Statify
```

Expected: all pass across `StatsEndpointTest`, `StatifyConfigTest`, `WidgetRegistryTest`, `StatDataTest`.

- [ ] **Step 5: Run pint**

```bash
cd /Volumes/DevDisk/code/filament5 && vendor/bin/pint packages/statify/src/Http/Controllers/StatsController.php --format agent
```

- [ ] **Step 6: Commit**

```bash
cd /Volumes/DevDisk/code/filament5 && git add packages/statify/src/Http/Controllers/StatsController.php tests/Feature/Statify/StatsEndpointTest.php && git commit -m "feat: update StatsController — responded_at, stats key, widget field, cache prefix, TTL=0 bypass, null guard"
```

---

## Final: Full test run

- [ ] **Run the complete Statify test suite**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=Statify
```

Expected: all tests pass across all four test files.
