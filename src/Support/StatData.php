<?php

namespace FilaHQ\Statify\Support;

use BackedEnum;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * @implements Arrayable<string, mixed>
 */
class StatData implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly mixed $value,
        public readonly int|float|null $rawValue,
        public readonly ?string $description,
        public readonly ?string $icon,
        public readonly string|array|null $color,
        public readonly ?array $chart,
    ) {}

    public static function fromStat(Stat $stat): self
    {
        $value = $stat->getValue();
        $stringValue = (string) $value;
        $label = (string) $stat->getLabel();

        $icon = $stat->getIcon();
        if ($icon instanceof BackedEnum) {
            $icon = $icon->value;
        }

        return new self(
            id: Str::slug($label),
            label: $label,
            value: $stringValue,
            rawValue: self::extractRawValue($stringValue),
            description: $stat->getDescription() ? (string) $stat->getDescription() : null,
            icon: $icon,
            color: $stat->getColor(),
            chart: $stat->getChart(),
        );
    }

    protected static function extractRawValue(string $value): int|float|null
    {
        // Strip thousands separators and whitespace
        $cleaned = preg_replace('/[,_\s]/', '', $value);

        // Detect a leading negative sign before any currency symbols
        $negative = str_starts_with($cleaned, '-');

        // Extract the first numeric sequence (integer or decimal)
        if (! preg_match('/\d+\.?\d*/', $cleaned, $matches)) {
            return null;
        }

        $number = $negative ? '-'.$matches[0] : $matches[0];

        if (str_contains($number, '.')) {
            return (float) $number;
        }

        return (int) $number;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'value' => $this->value,
            'raw_value' => $this->rawValue,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'chart' => $this->chart,
        ];
    }
}
