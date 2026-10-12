<?php

namespace FilaHQ\Statify\Support;

use BackedEnum;
use FilaHQ\Statify\Stat as ExportStat;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
        $id = $stat->getCustomId() ?? Str::slug($label);

        if (trim($id) === '') {
            throw new InvalidArgumentException('Statify metrics require a non-empty ID. Set an explicit ID with ->id().');
        }

        $rawValue = $stat instanceof ExportStat && $stat->hasRawValue()
            ? $stat->getRawValue()
            : (is_int($value) || is_float($value) ? $value : self::extractRawValue($stringValue));

        if (is_float($rawValue) && ! is_finite($rawValue)) {
            $rawValue = null;
        }

        $description = $stat->getDescription();

        $icon = $stat->getIcon();
        if ($icon instanceof BackedEnum) {
            $icon = $icon->value;
        }

        return new self(
            id: $id,
            label: $label,
            value: $stringValue,
            rawValue: $rawValue,
            description: $description === null ? null : (string) $description,
            icon: $icon,
            color: $stat->getColor(),
            chart: $stat->getChart(),
        );
    }

    protected static function extractRawValue(string $value): int|float|null
    {
        // Accept only complete numbers with decimal points and optional US-style
        // thousands separators/currency. Localized or abbreviated values need rawValue().
        $pattern = '/\A(?<before>[+-]?)\s*(?<currency>\p{Sc})?\s*(?<after>[+-]?)(?<number>(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]+)?|\.[0-9]+)(?:\s*(?<suffix>\p{Sc}|%))?\z/u';

        if (! preg_match($pattern, trim($value), $matches)
            || ($matches['before'] !== '' && $matches['after'] !== '')
            || ($matches['currency'] !== '' && ($matches['suffix'] ?? '') !== '')) {
            return null;
        }

        $number = $matches['before'].$matches['after'].str_replace(',', '', $matches['number']);

        if (! str_contains($number, '.')) {
            $integer = filter_var($number, FILTER_VALIDATE_INT);

            if ($integer !== false) {
                return $integer;
            }
        }

        $float = (float) $number;

        return is_finite($float) ? $float : null;
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
