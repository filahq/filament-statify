<?php

namespace FilaHQ\Statify;

use FilaHQ\Statify\Registry\WidgetRegistry;

class Statify
{
    /**
     * @param  array<int, class-string>  $widgets
     */
    public static function widgets(array $widgets): void
    {
        app(WidgetRegistry::class)->register($widgets);
    }
}
