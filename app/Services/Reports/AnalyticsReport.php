<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Carbon;

/**
 * Base for the table-style reports served by AnalyticsReportController
 * (one page, Excel and PDF for all of them). build() returns:
 *
 *  - summary:  [['label' => ..., 'value' => ..., 'hint' => ?string], ...]
 *  - sections: [['key', 'title', 'description' => ?string,
 *                'columns' => [['key', 'label', 'align' => 'left'|'right']],
 *                'rows' => [[column key => value, '_tone' => ?'danger'|'warning'], ...]], ...]
 */
abstract class AnalyticsReport
{
    /**
     * @param  array<string, mixed>  $filters  validated filters (see filterRules())
     */
    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
        protected array $filters,
        protected ?User $viewer,
    ) {}

    abstract public static function title(): string;

    abstract public static function description(): string;

    /**
     * @return array{summary: array, sections: array}
     */
    abstract public function build(): array;

    /** Whether the report covers a date range (Compliance is "as of today") */
    public static function usesDateRange(): bool
    {
        return true;
    }

    /**
     * Validation rules for report-specific filters (zone_id/from/to are shared).
     *
     * @return array<string, mixed>
     */
    public static function filterRules(): array
    {
        return [];
    }

    /**
     * Report-specific filter dropdowns: [['key', 'label', 'all' => ?string, 'options' => [['value', 'label']]]]
     */
    public static function filterOptions(?User $viewer): array
    {
        return [];
    }

    /**
     * Zones to report on: the chosen office, otherwise everything the
     * viewer can see (null = unrestricted).
     *
     * @return array<int>|null
     */
    protected function zoneIds(): ?array
    {
        if (! empty($this->filters['zone_id'])) {
            return [(int) $this->filters['zone_id']];
        }

        return Zone::accessIds($this->viewer);
    }

    protected static function column(string $key, string $label, bool $numeric = false): array
    {
        return ['key' => $key, 'label' => $label, 'align' => $numeric ? 'right' : 'left'];
    }

    protected static function percent(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /**
     * @param  iterable<int|float|null>  $values
     */
    protected static function average(iterable $values, int $precision = 1): ?float
    {
        $values = collect($values)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round($values->avg(), $precision);
    }

    protected static function headline(?string $value): string
    {
        return $value === null || $value === '' ? '—' : ucfirst(str_replace(['_', '-'], ' ', $value));
    }
}
