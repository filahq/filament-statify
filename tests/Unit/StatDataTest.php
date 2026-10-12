<?php

use FilaHQ\Statify\Stat as ExportStat;
use FilaHQ\Statify\Support\StatData;
use Filament\Widgets\StatsOverviewWidget\Stat;

it('extracts raw value from a plain integer string', function () {
    $stat = Stat::make('Users', '42');
    expect(StatData::fromStat($stat)->rawValue)->toBe(42);
});

it('extracts raw value from a formatted currency string', function () {
    $stat = Stat::make('Revenue', '$2,430');
    expect(StatData::fromStat($stat)->rawValue)->toBe(2430);
});

it('extracts raw value from a negative formatted string', function () {
    $stat = Stat::make('Loss', '-$1,234.56');
    expect(StatData::fromStat($stat)->rawValue)->toBe(-1234.56);
});

it('returns null for a non-numeric string', function () {
    $stat = Stat::make('Status', 'N/A');
    expect(StatData::fromStat($stat)->rawValue)->toBeNull();
});

it('extracts raw value from a float string', function () {
    $stat = Stat::make('Rate', '3.14');
    expect(StatData::fromStat($stat)->rawValue)->toBe(3.14);
});

it('parses complete supported numeric strings', function (string $value, int|float $expected) {
    expect(StatData::fromStat(Stat::make('Value', $value))->rawValue)->toBe($expected);
})->with([
    ['$-1,234.56', -1234.56],
    ['.5', 0.5],
    ['-.5', -0.5],
    ['0', 0],
    ['+42', 42],
    ['12%', 12],
    ['1,234.56 €', 1234.56],
]);

it('returns null for ambiguous or nonnumeric formatted values', function (string $value) {
    expect(StatData::fromStat(Stat::make('Value', $value))->rawValue)->toBeNull();
})->with(['1.2K', '€1.234,56', '10 / 20', '<span class="text-2xl">42</span>', '12,34', 'N/A', '--12', '1e5', '(123)', 'ERR 42', 'USD 12 EUR', '$12%', 'RM -1,234.56']);

it('preserves numeric inputs without parsing their display strings', function (int|float $value) {
    expect(StatData::fromStat(Stat::make('Value', $value))->rawValue)->toBe($value);
})->with([0, -12, 0.0000001, 1.25, PHP_INT_MAX]);

it('supports explicit raw values and explicit null', function () {
    expect(StatData::fromStat(ExportStat::make('Revenue', '€1.234,56')->rawValue(1234.56))->rawValue)->toBe(1234.56)
        ->and(StatData::fromStat(ExportStat::make('Loss', 'RM -1,234.56')->rawValue(-1234.56))->rawValue)->toBe(-1234.56)
        ->and(StatData::fromStat(ExportStat::make('Display only', '42')->rawValue(null))->rawValue)->toBeNull()
        ->and(StatData::fromStat(ExportStat::make('Zero', 'Unavailable')->rawValue(0))->rawValue)->toBe(0);
});

it('keeps a metric ID stable when its label changes', function () {
    $first = StatData::fromStat(Stat::make('Revenue', 1)->id('revenue'));
    $translated = StatData::fromStat(Stat::make('Hasil', 1)->id('revenue'));
    expect($first->id)->toBe('revenue')->and($translated->id)->toBe($first->id);
});

it('rejects empty metric IDs and preserves zero descriptions', function () {
    expect(fn () => StatData::fromStat(Stat::make('💰', 1)))->toThrow(InvalidArgumentException::class)
        ->and(StatData::fromStat(Stat::make('Value', 1)->description('0'))->description)->toBe('0');
});

it('does not emit nonfinite JSON numbers', function () {
    expect(StatData::fromStat(Stat::make('Value', INF))->rawValue)->toBeNull()
        ->and(fn () => ExportStat::make('Value', '∞')->rawValue(INF))->toThrow(InvalidArgumentException::class);
});
