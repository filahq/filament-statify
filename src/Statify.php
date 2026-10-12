<?php

namespace FilaHQ\Statify;

use Closure;
use FilaHQ\Statify\Registry\WidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Http\Request;

class Statify
{
    protected ?Closure $authorization = null;

    protected ?Closure $cacheScope = null;

    /**
     * @param  array<int|string, class-string<StatsOverviewWidget>>  $widgets
     */
    public static function widgets(array $widgets): void
    {
        app(WidgetRegistry::class)->register($widgets);
    }

    public static function authorizeUsing(Closure $callback): void
    {
        app(self::class)->authorization = $callback;
    }

    /** The callback returns a tenant/context key, or null to bypass caching. */
    public static function cacheScopeUsing(Closure $callback): void
    {
        app(self::class)->cacheScope = $callback;
    }

    public function isAuthorized(Request $request): bool
    {
        return $this->authorization === null || ($this->authorization)($request) === true;
    }

    public function resolveCacheScope(Request $request): string|int|null
    {
        return $this->cacheScope === null ? '' : ($this->cacheScope)($request);
    }
}
