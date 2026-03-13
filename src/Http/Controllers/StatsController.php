<?php

namespace FilaHQ\Statify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
        $ttl = config('statify.cache_ttl', 60);

        $data = Cache::remember('statify:stats:all', $ttl, function () {
            return $this->getAllStats();
        });

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'widgets' => $data,
        ]);
    }

    public function show(string $widget): JsonResponse
    {
        $ttl = config('statify.cache_ttl', 60);

        $data = Cache::remember("statify:stats:{$widget}", $ttl, function () use ($widget) {
            return $this->getWidgetStats($widget);
        });

        if ($data === null) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'widget' => $widget,
            'stats' => $data,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAllStats(): array
    {
        $results = [];

        foreach ($this->registry->getWidgets() as $widgetClass) {
            $stats = $this->extractor->extract($widgetClass);

            foreach ($stats as $stat) {
                $results[] = StatData::fromStat($stat)->toArray();
            }
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getWidgetStats(string $widgetSlug): ?array
    {
        foreach ($this->registry->getWidgets() as $widgetClass) {
            $slug = Str::kebab(class_basename($widgetClass));

            if ($slug === $widgetSlug) {
                $stats = $this->extractor->extract($widgetClass);

                return array_map(
                    fn ($stat) => StatData::fromStat($stat)->toArray(),
                    $stats,
                );
            }
        }

        return null;
    }
}
