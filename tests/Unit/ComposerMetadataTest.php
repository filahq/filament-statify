<?php

it('depends on filament widgets and only suggests full filament for plugin integration', function () {
    $composer = json_decode(
        file_get_contents(__DIR__.'/../../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require'])
        ->toHaveKey('filament/widgets')
        ->not->toHaveKey('filament/filament');

    expect($composer['suggest'])
        ->toHaveKey('filament/filament')
        ->and($composer['suggest']['filament/filament'])
        ->toContain('StatifyPlugin');
});
