<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'captcha_enabled',
        'captcha_type',
        'success_message',
        'header_image',
        'max_submissions',
        'submission_start_at',
        'submission_end_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'captcha_enabled' => 'boolean',
        'max_submissions' => 'integer',
        'submission_start_at' => 'datetime',
        'submission_end_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
        });
    }

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Check whether the form can currently accept a new submission.
     * Returns null when submissions are allowed, or a string message when blocked.
     */
    public function submissionBlockedReason(): ?string
    {
        $now = now();

        if ($this->submission_start_at && $now->lt($this->submission_start_at)) {
            return 'This form is not yet open for submissions.';
        }

        if ($this->submission_end_at && $now->gt($this->submission_end_at)) {
            return 'This form is no longer accepting submissions.';
        }

        if ($this->max_submissions !== null && $this->submissions()->count() >= $this->max_submissions) {
            return 'This form has reached its maximum number of submissions.';
        }

        return null;
    }
}
