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
    expect(config('statify.cache_prefix'))->toBe('statify');
});

it('has a default cache_ttl of 60', function () {
    expect(config('statify.cache_ttl'))->toBe(60);
});
