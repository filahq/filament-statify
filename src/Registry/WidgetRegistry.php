<?php

namespace FilaHQ\Statify\Registry;

use Illuminate\Support\Str;

class WidgetRegistry
{
    /** @var array<class-string, class-string> */
    protected array $widgets = [];

    /**
     * @param  array<int, class-string>  $widgets
     */
    public function register(array $widgets): void
    {
        foreach ($widgets as $widget) {
            $this->widgets[$widget] = $widget;
        }
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return array_values($this->widgets);
    }

    public function getSlug(string $widgetClass): string
    {
        return Str::kebab(class_basename($widgetClass));
    }

    public function flush(): void
    {
        $this->widgets = [];
    }
}
