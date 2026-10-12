<?php

use FilaHQ\Statify\Statify;
use FilaHQ\Statify\Tests\Fixtures\MetricsWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

beforeEach(function () {
    MetricsWidget::$calls = 0;
    MetricsWidget::$resolve = null;
    Statify::widgets(['metrics' => MetricsWidget::class]);
});

it('rejects duplicate metric IDs on both endpoints', function (string $url) {
    MetricsWidget::$resolve = fn () => [Stat::make('Revenue', 1), Stat::make('Revenue', 2)];
    $this->withoutExceptionHandling();
    expect(fn () => $this->getJson($url))->toThrow(InvalidArgumentException::class, 'Duplicate Statify metric ID');
})->with(['/api/statify/stats', '/api/statify/widgets/metrics']);

it('supports duplicate display labels with explicit unique IDs', function () {
    MetricsWidget::$resolve = fn () => [Stat::make('Revenue', 1)->id('today'), Stat::make('Revenue', 2)->id('yesterday')];
    $this->getJson('/api/statify/stats')->assertOk()
        ->assertJsonPath('stats.0.id', 'today')->assertJsonPath('stats.1.id', 'yesterday');
});

it('exports empty widgets in discovery metadata', function () {
    MetricsWidget::$resolve = fn () => [];
    $this->getJson('/api/statify/stats')->assertOk()
        ->assertJsonCount(0, 'stats')->assertJsonPath('widgets.0.id', 'metrics');
});
