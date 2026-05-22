<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    public const DEFAULT_OTHER_LABEL = 'Other';
    public const OTHER_OPTION_VALUE = '__other__';
    public const OTHER_PREFIX = 'other:';
    public const PHONE_PATTERN = '^[0-9+\s\-]+$';
    public const OPTION_BASED_TYPES = ['dropdown', 'radio', 'checkbox', 'checkbox_dropdown'];
    public const TABLE_COLUMN_TYPES = ['text', 'email', 'phone', 'number', 'textarea', 'dropdown', 'radio', 'checkbox', 'checkbox_dropdown', 'label'];
    public const VISIBILITY_OPERATORS = ['equals', 'not_equals', 'is_empty', 'is_not_empty'];

    protected $fillable = [
        'form_id',
        'label',
        'name',
        'type',
        'options',
        'config',
        'required',
        'placeholder',
        'default_value',
        'order',
    ];

    protected $casts = [
        'config' => 'array',
        'required' => 'boolean',
        'order' => 'integer',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function getOptionsArrayAttribute(): array
    {
        if (!$this->options) {
            return [];
        }

        $decoded = json_decode($this->options, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function hasOtherOption(): bool
    {
        return in_array(self::OTHER_OPTION_VALUE, $this->options_array, true);
    }

    public function getOtherLabelAttribute(): string
    {
        return self::normalizeOtherLabel(is_array($this->config) ? ($this->config['other_label'] ?? null) : null);
    }

    public function getSelectableOptionsAttribute(): array
    {
        return array_values(array_filter(
            $this->options_array,
            fn ($option) => $option !== self::OTHER_OPTION_VALUE
        ));
    }

    public function getTableColumnsAttribute(): array
    {
        $columns = is_array($this->config) ? ($this->config['columns'] ?? []) : [];

        if (!is_array($columns)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($column) {
            if (!is_array($column)) {
                return null;
            }

            $type = in_array($column['type'] ?? 'text', self::TABLE_COLUMN_TYPES, true)
                ? $column['type']
                : 'text';

            $options = self::normalizeOptions($type, $column['options'] ?? []);
            $allowCustomAnswer = (in_array($type, ['radio', 'dropdown'], true) && !empty($column['allow_custom_answer']))
                || ($type === 'checkbox'
                    && (!empty($column['allow_custom_answer']) || in_array(self::OTHER_OPTION_VALUE, $options, true)));

            return [
                'key' => is_string($column['key'] ?? null) ? $column['key'] : '',
                'label' => is_string($column['label'] ?? null) ? $column['label'] : '',
                'type' => $type,
                'required' => $type === 'label' ? false : !empty($column['required']),
                'options' => array_values(array_filter(
                    $options,
                    fn ($option) => $option !== self::OTHER_OPTION_VALUE
                )),
                'allow_custom_answer' => $allowCustomAnswer,
                'other_label' => self::normalizeOtherLabel($column['other_label'] ?? null),
                'visibility' => self::normalizeColumnVisibilityRule($column['visibility'] ?? null),
            ];
        }, $columns)));
    }

    public function getColWidthAttribute(): int
    {
        $value = is_array($this->config) ? ($this->config['col_width'] ?? 12) : 12;
        return in_array((int) $value, [3, 4, 6, 12], true) ? (int) $value : 12;
    }

    public function getTableAutoNumberAttribute(): bool
    {
        return (bool) (is_array($this->config) ? ($this->config['auto_number'] ?? false) : false);
    }

    public function getTableMaxRowsAttribute(): int
    {
        $value = is_array($this->config) ? ($this->config['max_rows'] ?? 0) : 0;
        return max(0, (int) $value);
    }

    public function getOtherInputNameAttribute(): string
    {
        return "{$this->name}_other";
    }

    public function getVisibilityRuleAttribute(): ?array
    {
        $visibility = is_array($this->config) ? ($this->config['visibility'] ?? null) : null;

        if (!is_array($visibility) || empty($visibility['enabled'])) {
            return null;
        }

        $fieldId = (int) ($visibility['field_id'] ?? 0);
        $operator = (string) ($visibility['operator'] ?? '');

        if ($fieldId <= 0 || !in_array($operator, self::VISIBILITY_OPERATORS, true)) {
            return null;
        }

        return [
            'enabled' => true,
            'field_id' => $fieldId,
            'operator' => $operator,
            'value' => (string) ($visibility['value'] ?? ''),
        ];
    }

    public function passesVisibilityCondition(mixed $controllerValue): bool
    {
        $rule = $this->visibility_rule;

        if ($rule === null) {
            return true;
        }

        return self::evaluateVisibilityCondition($controllerValue, $rule['operator'], $rule['value']);
    }

    public static function evaluateVisibilityCondition(mixed $actualValue, string $operator, mixed $expectedValue = null): bool
    {
        $normalizedActual = self::normalizeVisibilityValue($actualValue);
        $normalizedExpected = trim((string) ($expectedValue ?? ''));

        return match ($operator) {
            'not_equals' => !self::visibilityEquals($normalizedActual, $normalizedExpected),
            'is_empty' => self::visibilityIsEmpty($normalizedActual),
            'is_not_empty' => !self::visibilityIsEmpty($normalizedActual),
            default => self::visibilityEquals($normalizedActual, $normalizedExpected),
        };
    }

    public static function normalizeColumnVisibilityRule(mixed $visibility): ?array
    {
        if (!is_array($visibility) || empty($visibility['enabled'])) {
            return null;
        }

        $field = trim((string) ($visibility['field'] ?? ''));
        $operator = trim((string) ($visibility['operator'] ?? ''));

        if ($field === '' || !in_array($operator, self::VISIBILITY_OPERATORS, true)) {
            return null;
        }

        return [
            'enabled' => true,
            'field' => $field,
            'operator' => $operator,
            'value' => trim((string) ($visibility['value'] ?? '')),
        ];
    }

    public static function isTableColumnVisible(array $column, array $rowValues): bool
    {
        $visibilityRule = self::normalizeColumnVisibilityRule($column['visibility'] ?? null);

        if ($visibilityRule === null) {
            return true;
        }

        return self::evaluateVisibilityCondition(
            $rowValues[$visibilityRule['field']] ?? null,
            $visibilityRule['operator'],
            $visibilityRule['value'] ?? null
        );
    }

    protected static function normalizeVisibilityValue(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $value
            ), fn ($item) => $item !== ''));
        }

        return trim((string) ($value ?? ''));
    }

    protected static function visibilityEquals(string|array $actualValue, string $expectedValue): bool
    {
        if (is_array($actualValue)) {
            return in_array($expectedValue, $actualValue, true);
        }

        return $actualValue === $expectedValue;
    }

    protected static function visibilityIsEmpty(string|array $actualValue): bool
    {
        if (is_array($actualValue)) {
            return $actualValue === [];
        }

        return $actualValue === '';
    }

    public static function isOtherResponse(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::OTHER_PREFIX);
    }

    public static function formatOtherResponse(string $value): string
    {
        return self::OTHER_PREFIX . $value;
    }

    public static function extractOtherResponse(mixed $value): string
    {
        return self::isOtherResponse($value)
            ? substr($value, strlen(self::OTHER_PREFIX))
            : '';
    }

    public static function displaySubmissionValue(mixed $value, ?string $otherLabel = null): string
    {
        return self::isOtherResponse($value)
            ? self::normalizeOtherLabel($otherLabel) . ': ' . self::extractOtherResponse($value)
            : (string) $value;
    }

    public function formatSubmissionValue(mixed $value): string
    {
        if ($this->type === 'table') {
            return $this->formatTableSubmissionValue($value);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => self::displaySubmissionValue($item, $this->other_label))
                ->implode(', ');
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return self::displaySubmissionValue($value, $this->other_label);
    }

    protected function formatTableSubmissionValue(mixed $value): string
    {
        if (!is_array($value) || $value === []) {
            return '—';
        }

        $rows = collect($value)
            ->values()
            ->map(function ($row, $index) {
                if (!is_array($row)) {
                    return null;
                }

                $segments = collect($this->table_columns)
                    ->map(function ($column) use ($row) {
                        if (($column['type'] ?? null) === 'label') {
                            return null;
                        }

                        $columnValue = $row[$column['key']] ?? null;

                        if (is_array($columnValue)) {
                            if ($columnValue === []) {
                                return null;
                            }

                            $formattedValue = collect($columnValue)
                                ->map(fn ($item) => self::displaySubmissionValue($item, $column['other_label'] ?? null))
                                ->implode(', ');
                        } elseif ($columnValue === null || $columnValue === '') {
                            return null;
                        } else {
                            $formattedValue = self::displaySubmissionValue($columnValue, $column['other_label'] ?? null);
                        }

                        return "{$column['label']}: {$formattedValue}";
                    })
                    ->filter()
                    ->implode('; ');

                return $segments === '' ? null : 'Row '.($index + 1).': '.$segments;
            })
            ->filter()
            ->implode(' | ');

        return $rows !== '' ? $rows : '—';
    }

    public static function normalizeOptions(string $type, mixed $options): array
    {
        if (!in_array($type, self::OPTION_BASED_TYPES, true)) {
            return [];
        }

        $values = is_array($options)
            ? $options
            : preg_split('/\r\n|\r|\n/', (string) $options);

        return collect(is_array($values) ? $values : [])
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn ($option) => $option !== '')
            ->values()
            ->all();
    }

    public static function normalizeOtherLabel(mixed $label): string
    {
        $normalized = trim((string) $label);

        return $normalized !== '' ? $normalized : self::DEFAULT_OTHER_LABEL;
    }
}
