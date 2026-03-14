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

it('returns a sequential array after multiple register calls', function () {
    $registry = new WidgetRegistry();
    $registry->register([\stdClass::class]);
    $registry->register([\Exception::class]);

    $widgets = $registry->getWidgets();

    expect(array_keys($widgets))->toBe([0, 1]);
});

it('clears all widgets when flushed', function () {
    $registry = new WidgetRegistry();
    $registry->register([\stdClass::class]);
    $registry->flush();

    expect($registry->getWidgets())->toBeEmpty();
});

it('treats an empty register call as a no-op', function () {
    $registry = new WidgetRegistry();
    $registry->register([]);

    expect($registry->getWidgets())->toBeEmpty();
});
