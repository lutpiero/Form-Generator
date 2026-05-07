<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    public const OTHER_OPTION_VALUE = '__other__';
    public const OTHER_PREFIX = 'other:';
    public const PHONE_PATTERN = '^[0-9+\s\-]+$';

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

    public function getSelectableOptionsAttribute(): array
    {
        return array_values(array_filter(
            $this->options_array,
            fn ($option) => $option !== self::OTHER_OPTION_VALUE
        ));
    }

    public function getOtherInputNameAttribute(): string
    {
        return "{$this->name}_other";
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

    public static function displaySubmissionValue(mixed $value): string
    {
        return self::isOtherResponse($value)
            ? 'Other: ' . self::extractOtherResponse($value)
            : (string) $value;
    }
}
