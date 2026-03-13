# Statify Improvements Design

**Date:** 2026-03-13
**Status:** Approved

## Overview

Ten targeted improvements to the Statify package covering correctness, API clarity, configurability, and robustness. No breaking changes to public API surface.

---

## 1. Rename `generated_at` → `responded_at`

**Problem:** `now()` is called after the cache is read, so the field reflects response time, not data generation time.
**Fix:** Rename the field to `responded_at` in both `index()` and `show()` responses. The call site stays the same — the name now accurately describes the value.

---

## 2. Add `widget` field to flat stats in `index()`

**Problem:** `index()` flattens all widget stats into one array with no indication of which widget each stat came from.
**Decision:** Non-breaking — keep the flat array, add a `widget` slug key to each stat object.
**Response shape:**
```json
{
  "responded_at": "...",
  "stats": [
    { "widget": "revenue-widget", "id": "total-revenue", "label": "...", ... }
  ]
}
```
Implementation: loop over widgets in `getAllStats()`, get the slug via `WidgetRegistry::getSlug()`, and inject it into each stat's array before appending to results.

---

## 3. Fix `extractRawValue` regex

**Problem:** `/[^0-9.\-]/` keeps `-` anywhere in the string, so `-1,234.56` becomes `-123456`.
**Fix:** Two-pass approach:
1. Strip thousands separators: `/[,_\s]/` → `''`
2. Extract leading number: `/^-?\d*\.?\d+/`

Return `null` if no match.

---

## 4. `cache_ttl` configurable via env

**Problem:** `cache_ttl` is hardcoded to `60`; all other config values support env vars.
**Fix:** Change config to `env('STATIFY_CACHE_TTL', 60)`.

---

## 5. Don't cache null in `show()`

**Problem:** `Cache::remember` caches a `null` return when a widget slug doesn't exist, preventing newly registered widgets from being found until cache expires.
**Fix:** Resolve the widget class from the registry before touching the cache. Return 404 immediately if not found. Only call `Cache::remember` when the widget exists.

---

## 6. Slug generation on `WidgetRegistry`

**Problem:** `Str::kebab(class_basename($widgetClass))` is duplicated in `StatsController` and potentially elsewhere.
**Fix:** Add `public function getSlug(string $widgetClass): string` to `WidgetRegistry`. `StatsController` calls `$this->registry->getSlug($widgetClass)` in both `getAllStats()` and `getWidgetStats()`.

---

## 7. Registry deduplication

**Problem:** Calling `Statify::widgets([Foo::class])` and `StatifyPlugin::widgets([Foo::class])` registers the same widget twice, producing duplicate stats.
**Fix:** Store widgets as `[$class => $class]` keyed by class name in `WidgetRegistry`. `register()` uses array merge. `getWidgets()` returns `array_values()`.

---

## 8. Configurable cache key prefix

**Problem:** Apps sharing a cache store have colliding `statify:stats:all` keys.
**Fix:** Add `'cache_prefix' => env('STATIFY_CACHE_PREFIX', 'statify')` to config. Cache keys become `"{$prefix}:stats:all"` and `"{$prefix}:stats:{$widget}"`.

---

## 9. Opt-out of route registration

**Problem:** Routes are always registered even if the consumer wants to disable them.
**Fix:** In `registerRoutes()`, skip registration if `config('statify.prefix')` is `null` or `false`. Document this as the disable mechanism.

---

## 10. `cache_ttl = 0` disables caching

**Problem:** `Cache::remember` with TTL `0` behaves inconsistently across drivers; users expect `0` to mean "no cache".
**Fix:** In `index()` and `show()`, check `if ($ttl === 0)` and call the data method directly, bypassing `Cache::remember`.

---

## Affected Files

| File | Changes |
|------|---------|
| `src/Registry/WidgetRegistry.php` | Add `getSlug()`, deduplicate with keyed array |
| `src/Http/Controllers/StatsController.php` | Use `getSlug()`, add `widget` field, `responded_at`, cache prefix, TTL=0 bypass, null guard |
| `src/Support/StatData.php` | Fix `extractRawValue` regex |
| `config/statify.php` | Add `cache_prefix`, `cache_ttl` env, document prefix=null opt-out |
| `src/StatifyServiceProvider.php` | Skip route registration if prefix is null/false |
