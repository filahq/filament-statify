<?php

use FilaHQ\Statify\Registry\WidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

it('blocks requests without token when token is configured', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret-token']);

    $this->getJson('/api/statify/stats')
        ->assertUnauthorized();
});

it('allows requests with valid query token', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret-token']);

    $this->getJson('/api/statify/stats?token=secret-token')
        ->assertSuccessful();
});

it('allows requests with valid bearer token', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret-token']);

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

it('exports additive metadata and custom widget names', function () {
    config(['statify.widget_names' => ['test-stats-widget' => 'Revenue Overview']]);
    foreach (['/api/statify/stats', '/api/statify/widgets/test-stats-widget'] as $url) {
        $this->getJson($url)->assertOk()
            ->assertJsonPath('schema_version', 1)
            ->assertJsonStructure(['generated_at', 'responded_at', 'stats'])
            ->assertJsonPath('widgets.0.id', 'test-stats-widget')
            ->assertJsonPath('widgets.0.name', 'Revenue Overview');
    }
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
