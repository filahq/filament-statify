<?php

it('supports filament 4 and 5 while only suggesting full filament for plugin integration', function () {
    $composer = json_decode(
        file_get_contents(__DIR__.'/../../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require'])
        ->toHaveKey('filament/widgets')
        ->and($composer['require']['php'])->toBe('^8.2')
        ->and($composer['require']['filament/widgets'])->toBe('^4.0|^5.0')
        ->and($composer['require']['illuminate/support'])->toBe('^11.28|^12.0')
        ->not->toHaveKey('filament/filament');

    expect($composer['suggest'])
        ->toHaveKey('filament/filament')
        ->and($composer['suggest']['filament/filament'])
        ->toContain('StatifyPlugin');
});
