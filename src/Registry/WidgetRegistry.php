<?php

namespace FilaHQ\Statify\Registry;

class WidgetRegistry
{
    /** @var array<int, class-string> */
    protected array $widgets = [];

    /**
     * @param  array<int, class-string>  $widgets
     */
    public function register(array $widgets): void
    {
        $this->widgets = array_merge($this->widgets, $widgets);
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return array_unique($this->widgets);
    }
}
