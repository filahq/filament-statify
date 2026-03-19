# Widgets-Only Dependency Design

## Goal

Reduce `statify`'s hard Filament dependency from the full `filament/filament` package to `filament/widgets`, while keeping the `StatifyPlugin` integration available as an optional feature when the host app already has the full Filament package installed.

## Current State

- Core package behavior depends on `Filament\Widgets\StatsOverviewWidget` and `Filament\Widgets\StatsOverviewWidget\Stat`
- Optional plugin convenience depends on `Filament\Contracts\Plugin` and `Filament\Panel`
- Those panel/plugin types are provided by the full `filament/filament` package

## Design Decision

- Make `filament/widgets` the only hard Filament dependency
- Add a Composer `suggest` entry for `filament/filament`
- Keep `StatifyPlugin` in the package unchanged as an optional integration surface
- Document clearly that `StatifyPlugin` requires the full Filament package, while `Statify::widgets([...])` works with the narrower dependency set

## Why This Is Safe

- `StatifyServiceProvider` and the HTTP/API code paths do not reference `StatifyPlugin`
- The optional plugin class only matters when consumers import and use it
- Users who only need widget registration and API exposure should not need to install the full Filament meta-package

## Trade-off

Consumers who try to use `StatifyPlugin` without `filament/filament` installed will hit a missing Filament panel/plugin type at the moment they use that optional integration. This is acceptable if the README and Composer suggestions make the requirement explicit.
