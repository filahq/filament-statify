<?php

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
