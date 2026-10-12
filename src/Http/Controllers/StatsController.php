<?php

namespace FilaHQ\Statify\Http\Controllers;

use Closure;
use FilaHQ\Statify\Extractors\WidgetStatExtractor;
use FilaHQ\Statify\Registry\WidgetRegistry;
use FilaHQ\Statify\Statify;
use FilaHQ\Statify\Support\StatData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class StatsController
{
    public function __construct(
        protected WidgetRegistry $registry,
        protected WidgetStatExtractor $extractor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $slugs = array_map($this->registry->getSlug(...), $this->registry->getWidgets());
        $data = $this->remember($request, 'all', function (): array {
            $results = [];

            foreach ($this->registry->getWidgets() as $widgetClass) {
                $slug = $this->registry->getSlug($widgetClass);

                foreach ($this->extractStats($widgetClass) as $stat) {
                    $results[] = ['widget' => $slug] + $stat;
                }
            }

            return $results;
        });

        return $this->respond($data, $slugs);
    }

    public function show(Request $request, string $widget): JsonResponse
    {
        $widgetClass = $this->registry->resolve($widget);

        if ($widgetClass === null) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $data = $this->remember($request, 'widget:'.$widget, fn (): array => $this->extractStats($widgetClass));

        return $this->respond($data, [$widget], ['widget' => $widget]);
    }

    /** @return array{generated_at: string, stats: array} */
    protected function remember(Request $request, string $resource, Closure $extract): array
    {
        $generate = fn (): array => [
            'generated_at' => now()->toIso8601String(),
            'stats' => $extract(),
        ];
        $ttl = (int) config('statify.cache_ttl', 60);

        if ($ttl <= 0) {
            return $generate();
        }

        $scope = app(Statify::class)->resolveCacheScope($request);

        if ($scope === null) {
            return $generate();
        }

        $user = $request->user();
        $identity = $user === null ? null : [$user::class, $user->getAuthIdentifier()];
        $widgets = array_map(
            fn (string $class): array => [$class, $this->registry->getSlug($class)],
            $this->registry->getWidgets(),
        );
        $credential = $request->bearerToken() ?? $request->query('token');
        $key = hash('sha256', json_encode([
            config('statify.guard'),
            $identity,
            // Different tokens can have different abilities, even for the same user.
            is_string($credential) ? hash('sha256', $credential) : null,
            $scope,
            app()->getLocale(),
            $widgets,
        ], JSON_THROW_ON_ERROR));
        $prefix = config('statify.cache_prefix', 'statify');

        return Cache::remember("{$prefix}:v1:{$resource}:{$key}", $ttl, $generate);
    }

    protected function respond(array $data, array $slugs, array $extra = []): JsonResponse
    {
        return response()->json($extra + [
            'schema_version' => 1,
            'responded_at' => now()->toIso8601String(),
            'generated_at' => $data['generated_at'],
            'widgets' => array_map(fn (string $slug): array => [
                'id' => $slug,
                'name' => $this->registry->getName($slug),
            ], $slugs),
            'stats' => $data['stats'],
        ]);
    }

    /**
     * @param  class-string  $widgetClass
     * @return array<int, array<string, mixed>>
     */
    protected function extractStats(string $widgetClass): array
    {
        $stats = [];
        $ids = [];

        foreach ($this->extractor->extract($widgetClass) as $stat) {
            $data = StatData::fromStat($stat)->toArray();

            if (isset($ids[$data['id']])) {
                throw new InvalidArgumentException("Duplicate Statify metric ID [{$data['id']}] in [{$widgetClass}]. Set unique IDs with ->id().");
            }

            $ids[$data['id']] = true;
            $stats[] = $data;
        }

        return $stats;
    }
}
