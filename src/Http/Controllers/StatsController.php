<?php

namespace FilaHQ\Statify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use FilaHQ\Statify\Extractors\WidgetStatExtractor;
use FilaHQ\Statify\Registry\WidgetRegistry;
use FilaHQ\Statify\Support\StatData;

class StatsController
{
    public function __construct(
        protected WidgetRegistry $registry,
        protected WidgetStatExtractor $extractor,
    ) {}

    public function index(): JsonResponse
    {
        $ttl = (int) config('statify.cache_ttl', 60);
        $prefix = config('statify.cache_prefix', 'statify');

        $data = $ttl === 0
            ? $this->getAllStats()
            : Cache::remember("{$prefix}:stats:all", $ttl, fn () => $this->getAllStats());

        return response()->json([
            'responded_at' => now()->toIso8601String(),
            'stats' => $data,
        ]);
    }

    public function show(string $widget): JsonResponse
    {
        $widgetClass = $this->resolveWidgetClass($widget);

        if ($widgetClass === null) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $ttl = (int) config('statify.cache_ttl', 60);
        $prefix = config('statify.cache_prefix', 'statify');

        $data = $ttl === 0
            ? $this->extractStats($widgetClass)
            : Cache::remember("{$prefix}:stats:{$widget}", $ttl, fn () => $this->extractStats($widgetClass));

        return response()->json([
            'responded_at' => now()->toIso8601String(),
            'widget' => $widget,
            'stats' => $data,
        ]);
    }

    protected function resolveWidgetClass(string $widgetSlug): ?string
    {
        foreach ($this->registry->getWidgets() as $widgetClass) {
            if ($this->registry->getSlug($widgetClass) === $widgetSlug) {
                return $widgetClass;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAllStats(): array
    {
        $results = [];

        foreach ($this->registry->getWidgets() as $widgetClass) {
            $slug = $this->registry->getSlug($widgetClass);

            foreach ($this->extractor->extract($widgetClass) as $stat) {
                $results[] = ['widget' => $slug] + StatData::fromStat($stat)->toArray();
            }
        }

        return $results;
    }

    /**
     * @param  class-string  $widgetClass
     * @return array<int, array<string, mixed>>
     */
    protected function extractStats(string $widgetClass): array
    {
        return array_map(
            fn ($stat) => StatData::fromStat($stat)->toArray(),
            $this->extractor->extract($widgetClass),
        );
    }
}
