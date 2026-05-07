<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    public const OTHER_OPTION_VALUE = '__other__';

    protected $fillable = [
        'form_id',
        'label',
        'name',
        'type',
        'options',
        'required',
        'placeholder',
        'default_value',
        'order',
    ];

    protected $casts = [
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
            fn (string $option) => $option !== self::OTHER_OPTION_VALUE
        ));
    }

    public function getOtherInputNameAttribute(): string
    {
        return "{$this->name}_other";
    }
}
