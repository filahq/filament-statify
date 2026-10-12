<?php

use FilaHQ\Statify\Statify;

it('requires explicit public access', function () {
    config(['statify.guard' => 'token', 'statify.token' => null]);
    $this->getJson('/api/statify/stats')->assertStatus(503);
    config(['statify.guard' => 'public']);
    $this->getJson('/api/statify/stats')->assertOk();
});

it('rejects invalid guards instead of falling back to public access', function ($guard) {
    config(['statify.guard' => $guard, 'statify.token' => null]);
    $this->getJson('/api/statify/stats')->assertStatus(503);
})->with(['sanctumm', '', null]);

it('rejects missing or empty configured tokens', function ($token) {
    config(['statify.guard' => 'token', 'statify.token' => $token]);
    $this->getJson('/api/statify/stats')->assertStatus(503);
})->with([null, '', '   ', false]);

it('rejects incorrect or malformed request tokens', function (string $query) {
    config(['statify.guard' => 'token', 'statify.token' => 'secret']);
    $this->getJson('/api/statify/stats'.$query)->assertUnauthorized();
})->with(['', '?token=wrong', '?token[]=secret']);

it('prefers the bearer token when a query token is also present', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret']);
    $this->getJson('/api/statify/stats?token=wrong', ['Authorization' => 'Bearer secret'])->assertOk();
    $this->getJson('/api/statify/stats?token=secret', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

it('supports authorization callbacks after token validation', function () {
    config(['statify.guard' => 'token', 'statify.token' => 'secret']);
    Statify::authorizeUsing(fn ($request) => $request->header('X-Test-Allow') === 'yes');
    $this->getJson('/api/statify/stats', ['Authorization' => 'Bearer secret'])->assertForbidden();
    $this->getJson('/api/statify/stats', ['Authorization' => 'Bearer secret', 'X-Test-Allow' => 'yes'])->assertOk();
});

it('fails closed when the optional Sanctum guard is unavailable', function () {
    config(['statify.guard' => 'sanctum', 'auth.guards.sanctum' => null]);
    $this->getJson('/api/statify/stats')->assertStatus(503);
});
