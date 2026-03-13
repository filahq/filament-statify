<?php

namespace FilaHQ\Statify\Extractors;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use ReflectionMethod;

class WidgetStatExtractor
{
    /**
     * @param  class-string<StatsOverviewWidget>  $widgetClass
     * @return array<int, Stat>
     */
    public function extract(string $widgetClass): array
    {
        $widget = app()->make($widgetClass);

        $method = new ReflectionMethod($widget, 'getStats');

        return $method->invoke($widget);
    }
}
