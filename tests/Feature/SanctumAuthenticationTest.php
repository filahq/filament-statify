<?php

use FilaHQ\Statify\Statify;
use FilaHQ\Statify\Tests\Fixtures\MetricsWidget;
use FilaHQ\Statify\Tests\Fixtures\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    if (! class_exists(Sanctum::class)) {
        $this->markTestSkipped('Optional Sanctum dependency is not installed.');
    }

    config(['statify.guard' => 'sanctum']);
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    $this->loadMigrationsFrom(__DIR__.'/../../vendor/laravel/sanctum/database/migrations');
    MetricsWidget::$calls = 0;
    MetricsWidget::$resolve = fn () => [Stat::make('User', auth()->id())];
    Statify::widgets(['metrics' => MetricsWidget::class]);
});

function requestWithStatifyToken($test, string $token, string $url = '/api/statify/stats')
{
    // Laravel tests reuse the app between requests; clear guard instances to model
    // independent HTTP requests without Sanctum::actingAs() changing the default guard.
    auth()->forgetGuards();
    auth()->shouldUse('web');

    return $test->getJson($url, ['Authorization' => 'Bearer '.$token]);
}

it('blocks missing and invalid bearer tokens', function () {
    $this->getJson('/api/statify/stats')->assertUnauthorized();
    requestWithStatifyToken($this, 'invalid')->assertUnauthorized();
});

it('sets the default user for widgets and isolates real bearer token requests', function (string $url) {
    $first = User::create(['name' => 'First']);
    $second = User::create(['name' => 'Second']);
    $firstToken = $first->createToken('statify')->plainTextToken;
    $secondToken = $second->createToken('statify')->plainTextToken;

    requestWithStatifyToken($this, $firstToken, $url)->assertOk()->assertJsonPath('stats.0.raw_value', $first->id);
    requestWithStatifyToken($this, $secondToken, $url)->assertOk()->assertJsonPath('stats.0.raw_value', $second->id);
    requestWithStatifyToken($this, $firstToken, $url)->assertOk()->assertJsonPath('stats.0.raw_value', $first->id);
    expect(MetricsWidget::$calls)->toBe(2);
})->with(['/api/statify/stats', '/api/statify/widgets/metrics']);

it('enforces an optional token ability', function () {
    config(['statify.ability' => 'statify:read']);
    $user = User::create(['name' => 'Reader']);
    $denied = $user->createToken('other', ['orders:read'])->plainTextToken;
    $allowed = $user->createToken('statify', ['statify:read'])->plainTextToken;

    requestWithStatifyToken($this, $denied)->assertForbidden();
    requestWithStatifyToken($this, $allowed)->assertOk();
});

it('does not share cached results across different tokens of the same user', function () {
    $user = User::create(['name' => 'Reader']);
    $first = $user->createToken('first', ['statify:read'])->plainTextToken;
    $second = $user->createToken('second', ['statify:read', 'revenue:read'])->plainTextToken;
    MetricsWidget::$resolve = fn () => [Stat::make('Revenue', auth()->user()->tokenCan('revenue:read') ? 100 : 0)];

    requestWithStatifyToken($this, $first)->assertJsonPath('stats.0.raw_value', 0);
    requestWithStatifyToken($this, $second)->assertJsonPath('stats.0.raw_value', 100);
});

it('rejects revoked tokens even when the stats are cached', function () {
    $user = User::create(['name' => 'Reader']);
    $token = $user->createToken('statify');
    requestWithStatifyToken($this, $token->plainTextToken)->assertOk();
    $token->accessToken->delete();
    requestWithStatifyToken($this, $token->plainTextToken)->assertUnauthorized();
});
