<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function show(Form $form)
    {
        if (!$form->is_active) {
            abort(404);
        }

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

        if ($form->captcha_enabled && $form->captcha_type === 'honeypot') {
            if ($request->filled('_honeypot')) {
                return redirect()->route('forms.success', $form)
                    ->with('success', $form->success_message ?: 'Thank you for your submission!');
            }
        }

        if ($form->captcha_enabled && $form->captcha_type === 'math') {
            $answer = (int) $request->input('captcha_answer');
            $expected = session('captcha_answer');
            $captchaForm = session('captcha_form');

            if ($captchaForm !== $form->id || $answer !== $expected) {
                return back()->withErrors(['captcha_answer' => 'Incorrect CAPTCHA answer. Please try again.'])->withInput();
            }
        }

        $rules = [];
        $messages = [];
        $attributes = [];
        $fieldsById = $form->fields->keyBy('id');
        $visibleFieldIds = [];

        foreach ($form->fields as $field) {
            $visibleFieldIds[$field->id] = $this->isFieldVisible($field, $request, $fieldsById);
        }

        foreach ($form->fields as $field) {
            if (in_array($field->type, ['section', 'label'], true)) {
                continue;
            }

            if ($field->type === 'table') {
                $rules = array_merge($rules, $this->buildTableFieldRules($field));
                continue;
            }

            if (!($visibleFieldIds[$field->id] ?? true)) {
                continue;
            }

            $attributes[$field->name] = $field->label;

            $fieldRules = [$field->required ? 'required' : 'nullable'];

            switch ($field->type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'phone':
                    $fieldRules[] = 'regex:/' . FormField::PHONE_PATTERN . '/';
                    $messages["{$field->name}.regex"] = 'Please enter a valid phone number.';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'dropdown':
                case 'radio':
                    if (!empty($field->options_array)) {
                        $fieldRules[] = Rule::in($field->options_array);
                    }

                    if ($field->type === 'radio' && $field->hasOtherOption()) {
                        $otherFieldName = $field->other_input_name;
                        $otherSelected = (string) $request->input($field->name, '') === FormField::OTHER_OPTION_VALUE;

                        $rules[$otherFieldName] = [
                            $otherSelected ? 'required' : 'nullable',
                            'string',
                            'max:255',
                        ];
                        $attributes[$otherFieldName] = $field->other_label;
                        $messages["{$otherFieldName}.required"] = "Please enter a value for {$field->other_label}.";
                    }
                    break;
                case 'checkbox':
                case 'checkbox_dropdown':
                    $fieldRules[] = 'array';
                    $isCheckboxWithOtherOption = $field->type === 'checkbox' && $field->hasOtherOption();

                    if (!empty($field->options_array)) {
                        $rules["{$field->name}.*"] = ['string', Rule::in($field->options_array)];
                    }

                    if ($isCheckboxWithOtherOption) {
                        $otherFieldName = $field->other_input_name;
                        $otherChecked = in_array(FormField::OTHER_OPTION_VALUE, (array) $request->input($field->name, []), true);

                        $rules[$otherFieldName] = [
                            $otherChecked ? 'required' : 'nullable',
                            'string',
                            'max:255',
                        ];
                        $attributes[$otherFieldName] = $field->other_label;
                        $messages["{$otherFieldName}.required"] = "Please enter a value for {$field->other_label}.";
                    }
                    break;
            }

            $rules[$field->name] = $fieldRules;
        }

        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        $validator->after(function ($validation) use ($form, $request) {
            foreach ($form->fields as $field) {
                if ($field->type !== 'table') {
                    continue;
                }

                $rows = $request->input("table_fields.{$field->id}", []);
                if (!is_array($rows)) {
                    continue;
                }

                $this->validateTableRows($validation, $field, $rows);
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $data = [];
        foreach ($form->fields as $field) {
            if (in_array($field->type, ['section', 'label'], true)) {
                continue;
            }

            if ($field->type === 'table') {
                $tableRows = $validated['table_fields'][$field->id] ?? [];
                $data[$field->name] = $this->normalizeTableRows($field, is_array($tableRows) ? $tableRows : []);
                continue;
            }

            if (!($visibleFieldIds[$field->id] ?? true)) {
                continue;
            }

            $value = $validated[$field->name] ?? null;

            if (in_array($field->type, ['checkbox', 'checkbox_dropdown'], true)) {
                $value = array_values(array_filter(
                    (array) $value,
                    fn ($item) => $item !== null && $item !== ''
                ));
                $shouldProcessOtherOption = $field->type === 'checkbox' && $field->hasOtherOption();

                if ($shouldProcessOtherOption && in_array(FormField::OTHER_OPTION_VALUE, $value, true)) {
                    $otherValue = trim((string) ($validated[$field->other_input_name] ?? ''));
                    $value = array_map(
                        fn ($item) => $item === FormField::OTHER_OPTION_VALUE ? FormField::formatOtherResponse($otherValue) : $item,
                        $value
                    );
                }
            }

            if ($field->type === 'radio' && $field->hasOtherOption() && $value === FormField::OTHER_OPTION_VALUE) {
                $otherValue = trim((string) ($validated[$field->other_input_name] ?? ''));
                $value = FormField::formatOtherResponse($otherValue);
            }

            $data[$field->name] = $value;
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        session()->forget(['captcha_answer', 'captcha_form']);

        return redirect()->route('forms.success', $form)
            ->with('success', $form->success_message ?: 'Thank you for your submission!');
    }

    public function success(Form $form)
    {
        return view('public.forms.success', compact('form'));
    }

    protected function buildTableFieldRules(FormField $field): array
    {
        return [
            "table_fields.{$field->id}" => $field->required ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
        ];
    }

    protected function normalizeTableRows(FormField $field, array $rows): array
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalizedRow = [];
            $hasValue = false;

            foreach ($field->table_columns as $column) {
                if (($column['type'] ?? null) === 'label') {
                    continue;
                }

                if (!FormField::isTableColumnVisible($column, $row)) {
                    continue;
                }

                $key = $column['key'];
                $value = $row[$key] ?? null;

                if (in_array($column['type'], ['checkbox', 'checkbox_dropdown'], true)) {
                    $value = array_values(array_filter((array) $value, fn ($item) => $item !== null && $item !== ''));
                    if ($column['type'] === 'checkbox' && !empty($column['allow_custom_answer']) && in_array(FormField::OTHER_OPTION_VALUE, $value, true)) {
                        $otherValue = trim((string) ($row[$this->tableOtherInputKey($key)] ?? ''));
                        $value = array_map(
                            fn ($item) => $item === FormField::OTHER_OPTION_VALUE ? FormField::formatOtherResponse($otherValue) : $item,
                            $value
                        );
                    }
                    $hasValue = $hasValue || $value !== [];
                } elseif (in_array($column['type'], ['radio', 'dropdown'], true) && !empty($column['allow_custom_answer'])) {
                    if (is_array($value)) {
                        continue;
                    }
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                    if ($value === FormField::OTHER_OPTION_VALUE) {
                        $otherValue = trim((string) ($row[$this->tableOtherInputKey($key)] ?? ''));
                        $value = FormField::formatOtherResponse($otherValue);
                    }
                    $hasValue = $hasValue || !in_array($value, [null, ''], true);
                } else {
                    if (is_array($value)) {
                        // Skip malformed array payloads for non-checkbox columns.
                        continue;
                    }

                    if (is_string($value)) {
                        $value = trim($value);
                    }
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

    protected function tableOtherInputKey(string $columnKey): string
    {
        return "{$columnKey}_other";
    }

    protected function isFieldVisible(FormField $field, Request $request, $fieldsById): bool
    {
        $visibilityRule = $field->visibility_rule;

        if ($visibilityRule === null) {
            return true;
        }

        $controllerField = $fieldsById->get($visibilityRule['field_id']);

        if (!$controllerField || in_array($controllerField->type, ['section', 'table', 'label'], true)) {
            return true;
        }

        return $field->passesVisibilityCondition($request->input($controllerField->name));
    }

    protected function validateTableRows($validation, FormField $field, array $rows): void
    {
        $maxRows = $field->table_max_rows;
        if ($maxRows > 0 && count($rows) > $maxRows) {
            $validation->errors()->add(
                "table_fields.{$field->id}",
                "You may not submit more than {$maxRows} row(s) for this field."
            );
            return;
        }

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($field->table_columns as $column) {
                if (($column['type'] ?? null) === 'label') {
                    continue;
                }

                if (!FormField::isTableColumnVisible($column, $row)) {
                    continue;
                }

                $columnKey = $column['key'];
                $value = $row[$columnKey] ?? null;
                $path = "table_fields.{$field->id}.{$rowIndex}.{$columnKey}";

                if (($column['required'] ?? false) && $this->tableColumnValueIsEmpty($column, $value)) {
                    $validation->errors()->add($path, 'This field is required.');
                    continue;
                }

                if ($this->tableColumnValueIsEmpty($column, $value)) {
                    continue;
                }

                if (!$this->tableColumnValueIsValid($column, $value)) {
                    $validation->errors()->add($path, $this->tableColumnValidationMessage($column));
                    continue;
                }

                if ($column['type'] === 'checkbox' && !empty($column['allow_custom_answer'])) {
                    $selectedValues = array_values(array_filter(
                        (array) $value,
                        fn ($item) => $item !== null && $item !== ''
                    ));

                    if (in_array(FormField::OTHER_OPTION_VALUE, $selectedValues, true)) {
                        $otherInputKey = $this->tableOtherInputKey($columnKey);
                        $otherValue = trim((string) ($row[$otherInputKey] ?? ''));

                        if ($otherValue === '') {
                            $validation->errors()->add(
                                "table_fields.{$field->id}.{$rowIndex}.{$otherInputKey}",
                                'Please enter a value for ' . ($column['other_label'] ?? FormField::DEFAULT_OTHER_LABEL) . '.'
                            );
                        }
                    }
                }

                if (in_array($column['type'], ['radio', 'dropdown'], true) && !empty($column['allow_custom_answer'])) {
                    if ((string) $value === FormField::OTHER_OPTION_VALUE || FormField::isOtherResponse((string) $value)) {
                        $otherInputKey = $this->tableOtherInputKey($columnKey);
                        $otherValue = trim((string) ($row[$otherInputKey] ?? ''));

                        if ($otherValue === '') {
                            $validation->errors()->add(
                                "table_fields.{$field->id}.{$rowIndex}.{$otherInputKey}",
                                'Please enter a value for ' . ($column['other_label'] ?? FormField::DEFAULT_OTHER_LABEL) . '.'
                            );
                        }
                    }
                }
            }
        }
    }

    protected function tableColumnValueIsEmpty(array $column, mixed $value): bool
    {
        if (in_array($column['type'], ['checkbox', 'checkbox_dropdown'], true)) {
            return array_values(array_filter((array) $value, fn ($item) => $item !== null && $item !== '')) === [];
        }

        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) ($value ?? '')) === '';
    }

    protected function tableColumnValueIsValid(array $column, mixed $value): bool
    {
        switch ($column['type']) {
            case 'email':
                if (is_array($value)) {
                    return false;
                }
                return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
            case 'phone':
                if (is_array($value)) {
                    return false;
                }
                return preg_match('/' . FormField::PHONE_PATTERN . '/', (string) $value) === 1;
            case 'number':
                if (is_array($value)) {
                    return false;
                }
                return is_numeric($value);
            case 'dropdown':
            case 'radio':
                if (is_array($value)) {
                    return false;
                }
                if (!empty($column['allow_custom_answer']) && in_array((string) $value, [FormField::OTHER_OPTION_VALUE], true)) {
                    return true;
                }
                return empty($column['options']) || in_array((string) $value, $column['options'], true);
            case 'checkbox':
            case 'checkbox_dropdown':
                $options = $column['options'] ?? [];
                if ($column['type'] === 'checkbox' && !empty($column['allow_custom_answer'])) {
                    $options[] = FormField::OTHER_OPTION_VALUE;
                }

                if ($options === []) {
                    return true;
                }

                foreach ((array) $value as $item) {
                    if (!in_array((string) $item, $options, true)) {
                        return false;
                    }
                }

                return true;
            default:
                return true;
        }
    }

    protected function tableColumnValidationMessage(array $column): string
    {
        return match ($column['type']) {
            'email' => 'Please enter a valid email address.',
            'phone' => 'Please enter a valid phone number.',
            'number' => 'This field must be a number.',
            'dropdown', 'radio', 'checkbox', 'checkbox_dropdown' => 'Please select a valid option.',
            default => 'Invalid value.',
        };
    }
}
