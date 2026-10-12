<?php

namespace FilaHQ\Statify;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Widgets\StatsOverviewWidget;

class StatifyPlugin implements Plugin
{
    /** @var array<int|string, class-string<StatsOverviewWidget>> */
    protected array $widgets = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'statify';
    }

    /**
     * @param  array<int|string, class-string<StatsOverviewWidget>>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function register(Panel $panel): void
    {
        if (! empty($this->widgets)) {
            Statify::widgets($this->widgets);
        }
    }

    public function boot(Panel $panel): void {}
}
