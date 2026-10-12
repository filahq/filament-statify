# Changelog

## Unreleased

- Require explicit `public` authentication; reject invalid guards and missing static tokens.
- Keep Sanctum optional, activate its authenticated user for widget extraction, and support an optional token ability and application authorization callback.
- Isolate caches by user, token, locale, registry, and optional tenant/application context; allow the context callback to bypass caching.
- Support explicit widget slugs and Filament metric IDs, with class and collision validation.
- Add `FilaHQ\Statify\Stat::rawValue()` and conservative numeric parsing that preserves numeric inputs.
- Add `schema_version`, cached `generated_at`, and widget discovery/display-name metadata without changing existing response field shapes.
- Add standalone Testbench/Pest tests and compatibility CI for Filament 4/5 and Laravel 11/12, including installation without Sanctum.
- See the README upgrade guide for authentication, identifier, and numeric-parsing behavior changes.

## v0.2.0 - 2026-04-06

- Add support for both Filament 4 and Filament 5
- Widen the Laravel support range to `^11.28|^12.0`
- Clarify Filament 4 and 5 support in the installation docs
- Add Composer metadata coverage for the published compatibility contract

## v0.1.2 - 2026-03-20

- Narrowed the hard Filament dependency to `filament/widgets`
- Kept `StatifyPlugin` as an optional integration that requires `filament/filament`
- Clarified installation and plugin usage in the README

## v0.1.1 - 2026-03-19

- Added Filament 4 support

## v0.1.0 - 2026-03-19

- Initial release
