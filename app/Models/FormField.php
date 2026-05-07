<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

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

    public function getTableColumnsAttribute(): array
    {
        $columns = $this->config['columns'] ?? [];

        return is_array($columns) ? $columns : [];
    }

    public function getTableAutoNumberAttribute(): bool
    {
        return (bool) ($this->config['auto_number'] ?? false);
    }

    public function formatSubmissionValue(mixed $value, bool $blankForEmpty = false): string
    {
        if ($this->type === 'table') {
            if (!is_array($value) || $value === []) {
                return $blankForEmpty ? '' : '-';
            }

            $formatted = collect($value)->values()->map(function ($row, $index) {
                $parts = collect($this->table_columns)->map(function ($column) use ($row) {
                    $columnValue = $row[$column['key']] ?? null;

                    if (is_array($columnValue)) {
                        $columnValue = implode(', ', $columnValue);
                    }

                    if ($columnValue === null || $columnValue === '') {
                        return null;
                    }

                    return "{$column['label']}: {$columnValue}";
                })->filter()->implode('; ');

                return $parts === '' ? null : 'Row '.($index + 1).': '.$parts;
            })->filter()->implode(' | ');

            if ($formatted !== '') {
                return $formatted;
            }

            return $blankForEmpty ? '' : '-';
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        if ($value === null || $value === '') {
            return $blankForEmpty ? '' : '-';
        }

        return (string) $value;
    }
}
