<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function show(Form $form)
    {
        if (!$form->is_active) {
            abort(404);
        }

        // Generate math captcha if enabled
        $captcha = null;
        if ($form->captcha_enabled && $form->captcha_type === 'math') {
            $a = rand(1, 10);
            $b = rand(1, 10);
            session(['captcha_answer' => $a + $b, 'captcha_form' => $form->id]);
            $captcha = ['question' => "$a + $b = ?", 'answer' => $a + $b];
        }

        return view('public.forms.show', compact('form', 'captcha'));
    }

    public function submit(Request $request, Form $form)
    {
        if (!$form->is_active) {
            abort(404);
        }

        // Honeypot check
        if ($form->captcha_enabled && $form->captcha_type === 'honeypot') {
            if ($request->filled('_honeypot')) {
                return redirect()->route('forms.success', $form)
                    ->with('success', $form->success_message ?: 'Thank you for your submission!');
            }
        }

        // Math captcha check
        if ($form->captcha_enabled && $form->captcha_type === 'math') {
            $answer = (int) $request->input('captcha_answer');
            $expected = session('captcha_answer');
            $captchaForm = session('captcha_form');

            if ($captchaForm !== $form->id || $answer !== $expected) {
                return back()->withErrors(['captcha_answer' => 'Incorrect CAPTCHA answer. Please try again.'])->withInput();
            }
        }

        // Build validation rules from form fields
        $rules = [];
        foreach ($form->fields as $field) {
            if ($field->type === 'section') {
                continue;
            }

            if ($field->type === 'table') {
                $rules = array_merge($rules, $this->buildTableFieldRules($field));
                continue;
            }

            $fieldRules = $field->required ? ['required'] : ['nullable'];

            if ($field->type === 'email') {
                $fieldRules[] = 'email';
            }

            if ($field->type === 'number') {
                $fieldRules[] = 'numeric';
            }

            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        // Filter only form field data (exclude section fields)
        $data = [];
        foreach ($form->fields as $field) {
            if ($field->type === 'section') {
                continue;
            }

            if ($field->type === 'table') {
                $tableRows = data_get($validated, "table_fields.{$field->id}", []);
                $data[$field->name] = $this->normalizeTableRows($field, $tableRows);
                continue;
            }

            $data[$field->name] = $validated[$field->name] ?? null;
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Clear captcha session
        session()->forget(['captcha_answer', 'captcha_form']);

        return redirect()->route('forms.success', $form)
            ->with('success', $form->success_message ?: 'Thank you for your submission!');
    }

    public function success(Form $form)
    {
        return view('public.forms.success', compact('form'));
    }

    protected function buildTableFieldRules($field): array
    {
        $rules = [
            "table_fields.{$field->id}" => ['required', 'array', 'min:1'],
        ];

        foreach ($field->table_columns as $column) {
            $key = $column['key'];
            $columnRules = ($column['required'] ?? false) ? ['required'] : ['nullable'];

            switch ($column['type']) {
                case 'email':
                    $columnRules[] = 'string';
                    $columnRules[] = 'email';
                    break;
                case 'number':
                    $columnRules[] = 'numeric';
                    break;
                case 'dropdown':
                case 'radio':
                    $columnRules[] = 'string';
                    if (!empty($column['options'])) {
                        $columnRules[] = Rule::in($column['options']);
                    }
                    break;
                case 'checkbox':
                    $columnRules[] = 'array';
                    if ($column['required'] ?? false) {
                        $columnRules[] = 'min:1';
                    }
                    if (!empty($column['options'])) {
                        $rules["table_fields.{$field->id}.*.{$key}.*"] = [Rule::in($column['options'])];
                    }
                    break;
                default:
                    $columnRules[] = 'string';
                    break;
            }

            $rules["table_fields.{$field->id}.*.{$key}"] = $columnRules;
        }

        return $rules;
    }

    protected function normalizeTableRows($field, array $rows): array
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalizedRow = [];
            $hasValue = false;

            foreach ($field->table_columns as $column) {
                $key = $column['key'];
                $value = $row[$key] ?? null;

                if ($column['type'] === 'checkbox') {
                    $value = array_values(array_filter((array) $value, fn ($item) => $item !== null && $item !== ''));
                    $hasValue = $hasValue || $value !== [];
                } else {
                    $value = is_string($value) ? trim($value) : $value;
                    $hasValue = $hasValue || !in_array($value, [null, ''], true);
                }

                $normalizedRow[$key] = $value;
            }

            if ($hasValue) {
                $normalizedRows[] = $normalizedRow;
            }
        }

        return $normalizedRows;
    }
}
