<?php

use FilaHQ\Statify\Registry\WidgetRegistry;
use FilaHQ\Statify\Statify;
use FilaHQ\Statify\Tests\Fixtures\MetricsWidget;
use FilaHQ\Statify\Tests\Fixtures\OtherWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    MetricsWidget::$calls = 0;
    MetricsWidget::$resolve = null;
    Statify::widgets(['metrics' => MetricsWidget::class]);
});

it('reuses cached data until expiry while reporting its actual generation time', function (string $url) {
    $this->freezeTime();
    $first = $this->getJson($url)->assertOk()->json();
    $this->travel(30)->seconds();
    $cached = $this->getJson($url)->assertOk()->json();

    expect($cached['stats'])->toBe($first['stats'])
        ->and($cached['generated_at'])->toBe($first['generated_at'])
        ->and($cached['responded_at'])->not->toBe($first['responded_at']);

    $this->travel(31)->seconds();
    $this->getJson($url)->assertOk()->assertJsonPath('stats.0.raw_value', 2);
})->with(['/api/statify/stats', '/api/statify/widgets/metrics']);

it('isolates users on both endpoints', function (string $url) {
    MetricsWidget::$resolve = fn () => [Stat::make('User', auth()->id())];
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->getJson($url)->assertOk()->assertJsonPath('stats.0.raw_value', 1);
    $this->actingAs(new GenericUser(['id' => 2]));
    $this->getJson($url)->assertOk()->assertJsonPath('stats.0.raw_value', 2);
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->getJson($url)->assertOk()->assertJsonPath('stats.0.raw_value', 1);
    expect(MetricsWidget::$calls)->toBe(2);
})->with(['/api/statify/stats', '/api/statify/widgets/metrics']);

it('adds tenant context to the authenticated cache scope', function (string $url) {
    Statify::cacheScopeUsing(fn ($request) => $request->header('X-Test-Tenant'));
    MetricsWidget::$resolve = fn () => [Stat::make('Tenant', request()->header('X-Test-Tenant'))];

    $this->getJson($url, ['X-Test-Tenant' => '1'])->assertJsonPath('stats.0.raw_value', 1);
    $this->getJson($url, ['X-Test-Tenant' => '2'])->assertJsonPath('stats.0.raw_value', 2);
    $this->getJson($url, ['X-Test-Tenant' => '1'])->assertJsonPath('stats.0.raw_value', 1);
    expect(MetricsWidget::$calls)->toBe(2);
})->with(['/api/statify/stats', '/api/statify/widgets/metrics']);

it('bypasses caching when the context is unavailable', function () {
    Statify::cacheScopeUsing(fn () => null);
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 1);
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 2);
});

it('bypasses caching for zero or negative TTLs', function (int $ttl) {
    config(['statify.cache_ttl' => $ttl]);
    Statify::cacheScopeUsing(function () {
        throw new RuntimeException('Cache context should not be resolved when caching is disabled.');
    });
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 1);
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 2);
})->with([0, -1]);

it('isolates cache prefixes', function () {
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 1);
    config(['statify.cache_prefix' => 'another-app']);
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 2);
    config(['statify.cache_prefix' => 'statify']);
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 1);
});

it('does not expose request tokens in cache keys', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret-token']);
    Cache::shouldReceive('remember')
        ->once()
        ->withArgs(fn (string $key, int $ttl, Closure $generate): bool => ! str_contains($key, 'secret-token') && $ttl === 60)
        ->andReturnUsing(fn ($key, $ttl, $generate) => $generate());

    $this->getJson('/api/statify/stats', ['Authorization' => 'Bearer secret-token'])->assertOk();
});

it('isolates locales and registered widget sets', function () {
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 1);
    app()->setLocale('ms');
    $this->getJson('/api/statify/stats')->assertJsonPath('stats.0.raw_value', 2);
    Statify::widgets([OtherWidget::class]);
    $this->getJson('/api/statify/stats')->assertJsonCount(2, 'stats');
    app(WidgetRegistry::class)->flush();
    $this->getJson('/api/statify/stats')->assertJsonCount(0, 'stats');
});

it('does not collide with a widget whose slug is all', function () {
    app(WidgetRegistry::class)->flush();
    Statify::widgets(['all' => MetricsWidget::class, OtherWidget::class]);
    $this->getJson('/api/statify/stats')->assertJsonCount(2, 'stats');
    $this->getJson('/api/statify/widgets/all')->assertJsonCount(1, 'stats');
});

it('checks authorization even when data is already cached', function () {
    $this->getJson('/api/statify/stats')->assertOk();
    Statify::authorizeUsing(fn () => false);
    $this->getJson('/api/statify/stats')->assertForbidden();
    $this->getJson('/api/statify/widgets/metrics')->assertForbidden();
    expect(MetricsWidget::$calls)->toBe(1);
});
