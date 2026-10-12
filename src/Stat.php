<?php

namespace FilaHQ\Statify;

use Filament\Widgets\StatsOverviewWidget\Stat as FilamentStat;
use InvalidArgumentException;

class Stat extends FilamentStat
{
    protected bool $hasRawValue = false;

    protected int|float|null $rawValue = null;

    public function rawValue(int|float|null $value): static
    {
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Statify raw values must be finite.');
        }

        $this->hasRawValue = true;
        $this->rawValue = $value;

        return $this;
    }

    public function hasRawValue(): bool
    {
        return $this->hasRawValue;
    }

    public function getRawValue(): int|float|null
    {
        return $this->rawValue;
    }
}
