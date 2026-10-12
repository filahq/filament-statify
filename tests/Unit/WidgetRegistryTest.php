<?php

use FilaHQ\Statify\Registry\WidgetRegistry;
use FilaHQ\Statify\Tests\Fixtures\MetricsWidget;
use FilaHQ\Statify\Tests\Fixtures\OtherWidget;

it('deduplicates widgets registered multiple times', function () {
    $registry = new WidgetRegistry;
    $registry->register([MetricsWidget::class]);
    $registry->register([MetricsWidget::class]);

    expect($registry->getWidgets())->toHaveCount(1);
});

it('generates a kebab-case slug from a widget class name', function () {
    $registry = new WidgetRegistry;

    expect($registry->getSlug('App\Filament\Widgets\RevenueStatsWidget'))
        ->toBe('revenue-stats-widget');
});

it('returns a sequential array after multiple register calls', function () {
    $registry = new WidgetRegistry;
    $registry->register([MetricsWidget::class]);
    $registry->register([OtherWidget::class]);

    $widgets = $registry->getWidgets();

    expect(array_keys($widgets))->toBe([0, 1]);
});

it('clears all widgets when flushed', function () {
    $registry = new WidgetRegistry;
    $registry->register([MetricsWidget::class]);
    $registry->flush();

    expect($registry->getWidgets())->toBeEmpty();
});

it('treats an empty register call as a no-op', function () {
    $registry = new WidgetRegistry;
    $registry->register([]);

    expect($registry->getWidgets())->toBeEmpty();
});

it('preserves explicit slugs across duplicate registration', function () {
    $registry = new WidgetRegistry;
    $registry->register(['revenue' => MetricsWidget::class]);
    $registry->register([MetricsWidget::class]);
    expect($registry->getSlug(MetricsWidget::class))->toBe('revenue')
        ->and($registry->resolve('revenue'))->toBe(MetricsWidget::class)
        ->and($registry->getWidgets())->toBe([MetricsWidget::class]);
});

it('rejects slug collisions atomically', function () {
    $registry = new WidgetRegistry;
    $registry->register(['revenue' => MetricsWidget::class]);
    expect(fn () => $registry->register(['other' => OtherWidget::class, 'revenue' => OtherWidget::class]))
        ->toThrow(InvalidArgumentException::class, 'Duplicate Statify widget slug');
    expect($registry->resolve('other'))->toBeNull();
});

it('rejects changing an already registered widget slug', function () {
    $registry = new WidgetRegistry;
    $registry->register(['revenue' => MetricsWidget::class]);
    expect(fn () => $registry->register(['renamed' => MetricsWidget::class]))
        ->toThrow(InvalidArgumentException::class, 'already registered');
});

it('detects equal class basenames and allows explicit slugs to disambiguate them', function () {
    $registry = new WidgetRegistry;
    $registry->register([MetricsWidget::class]);
    expect(fn () => $registry->register([FilaHQ\Statify\Tests\Fixtures\Admin\MetricsWidget::class]))
        ->toThrow(InvalidArgumentException::class, 'Duplicate Statify widget slug');
    $registry->register(['admin-metrics' => FilaHQ\Statify\Tests\Fixtures\Admin\MetricsWidget::class]);
    expect($registry->getWidgets())->toHaveCount(2);
});

it('deduplicates an explicit slug followed by a class in the same call', function () {
    $registry = new WidgetRegistry;
    $registry->register(['revenue' => MetricsWidget::class, MetricsWidget::class]);
    expect($registry->getWidgets())->toBe([MetricsWidget::class]);
});

it('rejects invalid widget classes', function ($class) {
    expect(fn () => (new WidgetRegistry)->register([$class]))->toThrow(InvalidArgumentException::class);
})->with([stdClass::class, 'MissingWidget', 42]);

it('rejects invalid explicit slugs', function (string $slug) {
    expect(fn () => (new WidgetRegistry)->register([$slug => MetricsWidget::class]))
        ->toThrow(InvalidArgumentException::class);
})->with(['', 'Revenue', 'my/widget', 'my widget', '-revenue']);
