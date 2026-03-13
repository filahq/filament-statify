<?php

namespace FilaHQ\Statify;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use FilaHQ\Statify\Http\Controllers\StatsController;
use FilaHQ\Statify\Http\Middleware\AuthenticateStatify;
use FilaHQ\Statify\Registry\WidgetRegistry;

class StatifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/statify.php', 'statify');

        $this->app->singleton(WidgetRegistry::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/statify.php' => config_path('statify.php'),
        ], 'statify-config');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('statify.prefix', 'api/statify'))
            ->middleware(AuthenticateStatify::class)
            ->group(function () {
                Route::get('/stats', [StatsController::class, 'index'])->name('statify.stats.index');
                Route::get('/widgets/{widget}', [StatsController::class, 'show'])->name('statify.stats.show');
            });
    }
}
