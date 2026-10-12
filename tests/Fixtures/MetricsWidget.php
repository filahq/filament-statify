<?php

namespace FilaHQ\Statify\Tests\Fixtures;

use Closure;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MetricsWidget extends StatsOverviewWidget
{
    public static int $calls = 0;

    public static ?Closure $resolve = null;

    protected function getStats(): array
    {
        self::$calls++;

        return self::$resolve === null
            ? [Stat::make('Value', self::$calls)]
            : (self::$resolve)();
    }
}
