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
}
