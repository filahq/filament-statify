<?php

namespace FilaHQ\Statify\Registry;

use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WidgetRegistry
{
    /** @var array<string, class-string<StatsOverviewWidget>> */
    protected array $widgets = [];

    /**
     * @param  array<int|string, class-string<StatsOverviewWidget>>  $widgets
     */
    public function register(array $widgets): void
    {
        $registered = $this->widgets;

        foreach ($widgets as $slug => $widget) {
            if (! is_string($widget) || ! is_subclass_of($widget, StatsOverviewWidget::class)) {
                throw new InvalidArgumentException('Statify widgets must extend '.StatsOverviewWidget::class.'.');
            }

            $slug = is_int($slug)
                ? (array_search($widget, $registered, true) ?: $this->getSlug($widget))
                : $slug;

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
                throw new InvalidArgumentException("Invalid Statify widget slug [{$slug}]. Use lowercase letters, numbers, and hyphens.");
            }

            if (isset($registered[$slug]) && $registered[$slug] !== $widget) {
                throw new InvalidArgumentException("Duplicate Statify widget slug [{$slug}]. Register an explicit unique slug.");
            }

            $existing = array_search($widget, $registered, true);

            if ($existing !== false && $existing !== $slug) {
                throw new InvalidArgumentException("Statify widget [{$widget}] is already registered as [{$existing}].");
            }

            $registered[$slug] = $widget;
        }

        $this->widgets = $registered;
    }

    /**
     * @return array<int, class-string<StatsOverviewWidget>>
     */
    public function getWidgets(): array
    {
        return array_values($this->widgets);
    }

    public function getSlug(string $widgetClass): string
    {
        return array_search($widgetClass, $this->widgets, true) ?: Str::kebab(class_basename($widgetClass));
    }

    /** @return class-string<StatsOverviewWidget>|null */
    public function resolve(string $slug): ?string
    {
        return $this->widgets[$slug] ?? null;
    }

    public function getName(string $slug): string
    {
        return config('statify.widget_names', [])[$slug] ?? Str::headline($slug);
    }

    public function flush(): void
    {
        $this->widgets = [];
    }
}
