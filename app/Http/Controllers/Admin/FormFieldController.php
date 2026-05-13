<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormFieldController extends Controller
{
    public function create(Form $form)
    {
        $visibilityControllerFields = $this->visibilityControllerFields($form);

        return view('admin.fields.create', compact('form', 'visibilityControllerFields'));
    }

    public function store(Request $request, Form $form)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,checkbox_dropdown,table,section,label',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'allow_custom_answer' => 'boolean',
            'other_label' => 'nullable|string|max:255',
            'config.min_value' => 'nullable|numeric',
            'config.max_value' => 'nullable|numeric|gte:config.min_value',
            'config.min_length' => 'nullable|integer|min:0',
            'config.max_length' => 'nullable|integer|min:0|gte:config.min_length',
            'config.auto_number' => 'sometimes|boolean',
            'config.max_rows' => 'nullable|integer|min:0',
            'config.columns' => 'nullable|array',
            'config.columns.*.label' => 'nullable|string|max:255',
            'config.columns.*.key' => 'nullable|string|max:255',
            'config.columns.*.type' => 'nullable|in:text,email,phone,number,textarea,dropdown,radio,checkbox,checkbox_dropdown,label',
            'config.columns.*.required' => 'sometimes|boolean',
            'config.columns.*.allow_custom_answer' => 'sometimes|boolean',
            'config.columns.*.other_label' => 'nullable|string|max:255',
            'config.columns.*.options' => 'nullable',
            'config.columns.*.visibility.enabled' => 'sometimes|boolean',
            'config.columns.*.visibility.field' => 'nullable|string|max:255',
            'config.columns.*.visibility.operator' => 'nullable|in:equals,not_equals,is_empty,is_not_empty',
            'config.columns.*.visibility.value' => 'nullable|string|max:255',
            'visibility.enabled' => 'sometimes|boolean',
            'visibility.field_id' => 'nullable|integer',
            'visibility.operator' => 'nullable|in:equals,not_equals,is_empty,is_not_empty',
            'visibility.value' => 'nullable|string|max:255',
        ]);

        $validated['form_id'] = $form->id;
        $validated['name'] = $this->sanitizeName($validated['label']);
        $validated['order'] = $form->fields()->count();
        $validated['required'] = !in_array($validated['type'], ['section', 'table', 'label'], true)
            && $request->boolean('required', false);
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
        $validated['config'] = $this->prepareConfig($request, $form, $validated['type']);
        unset($validated['allow_custom_answer']);

        $form->fields()->create($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Field added successfully.');
    }

    public function edit(Form $form, FormField $field)
    {
        $visibilityControllerFields = $this->visibilityControllerFields($form, $field);

        return view('admin.fields.edit', compact('form', 'field', 'visibilityControllerFields'));
    }

    public function update(Request $request, Form $form, FormField $field)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,checkbox_dropdown,table,section,label',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'order' => 'nullable|integer',
            'allow_custom_answer' => 'boolean',
            'other_label' => 'nullable|string|max:255',
            'config.min_value' => 'nullable|numeric',
            'config.max_value' => 'nullable|numeric|gte:config.min_value',
            'config.min_length' => 'nullable|integer|min:0',
            'config.max_length' => 'nullable|integer|min:0|gte:config.min_length',
            'config.auto_number' => 'sometimes|boolean',
            'config.max_rows' => 'nullable|integer|min:0',
            'config.columns' => 'nullable|array',
            'config.columns.*.label' => 'nullable|string|max:255',
            'config.columns.*.key' => 'nullable|string|max:255',
            'config.columns.*.type' => 'nullable|in:text,email,phone,number,textarea,dropdown,radio,checkbox,checkbox_dropdown,label',
            'config.columns.*.required' => 'sometimes|boolean',
            'config.columns.*.allow_custom_answer' => 'sometimes|boolean',
            'config.columns.*.other_label' => 'nullable|string|max:255',
            'config.columns.*.options' => 'nullable',
            'config.columns.*.visibility.enabled' => 'sometimes|boolean',
            'config.columns.*.visibility.field' => 'nullable|string|max:255',
            'config.columns.*.visibility.operator' => 'nullable|in:equals,not_equals,is_empty,is_not_empty',
            'config.columns.*.visibility.value' => 'nullable|string|max:255',
            'visibility.enabled' => 'sometimes|boolean',
            'visibility.field_id' => 'nullable|integer',
            'visibility.operator' => 'nullable|in:equals,not_equals,is_empty,is_not_empty',
            'visibility.value' => 'nullable|string|max:255',
        ]);

        $validated['name'] = $this->sanitizeName($validated['label']);
        $validated['required'] = !in_array($validated['type'], ['section', 'table', 'label'], true)
            && $request->boolean('required', false);
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
        $validated['config'] = $this->prepareConfig($request, $form, $validated['type'], $field);
        unset($validated['allow_custom_answer']);

        $field->update($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Field updated successfully.');
    }

    public function destroy(Form $form, FormField $field)
    {
        $field->delete();

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Field deleted successfully.');
    }

    public function reorder(Request $request, Form $form)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'integer',
        ]);

        foreach ($request->fields as $order => $fieldId) {
            FormField::where('id', $fieldId)->where('form_id', $form->id)->update(['order' => $order]);
        }

        return response()->json(['success' => true]);
    }

    private function prepareOptions(Request $request, string $type, ?string $options): ?string
    {
        if (!in_array($type, FormField::OPTION_BASED_TYPES, true)) {
            return null;
        }

        $optionsArray = FormField::normalizeOptions($type, $options);
        $optionsArray = array_values(array_filter(
            $optionsArray,
            fn ($option) => $option !== FormField::OTHER_OPTION_VALUE
        ));

        if (in_array($type, ['checkbox', 'radio'], true) && $request->boolean('allow_custom_answer', false)) {
            $optionsArray[] = FormField::OTHER_OPTION_VALUE;
        }

        return empty($optionsArray) ? null : json_encode(array_values(array_unique($optionsArray)));
    }

    private function prepareConfig(Request $request, Form $form, string $type, ?FormField $currentField = null): ?array
    {
        if ($type === 'table') {
            return $this->prepareTableConfig($request);
        }

        $config = [];

        if (in_array($type, ['checkbox', 'radio'], true) && $request->boolean('allow_custom_answer', false)) {
            $config['other_label'] = FormField::normalizeOtherLabel($request->input('other_label'));
        }

        if ($type === 'number') {
            $minValue = $request->input('config.min_value');
            $maxValue = $request->input('config.max_value');

            if ($this->hasConfigInput($minValue)) {
                $config['min_value'] = (float) $minValue;
            }

            if ($this->hasConfigInput($maxValue)) {
                $config['max_value'] = (float) $maxValue;
            }
        }

        if (in_array($type, ['number', 'text', 'textarea'], true)) {
            $minLength = $request->input('config.min_length');
            $maxLength = $request->input('config.max_length');

            if ($this->hasConfigInput($minLength)) {
                $config['min_length'] = (int) $minLength;
            }

            if ($this->hasConfigInput($maxLength)) {
                $config['max_length'] = (int) $maxLength;
            }
        }

        $visibilityConfig = $this->prepareVisibilityConfig($request, $form, $type, $currentField);
        if ($visibilityConfig !== null) {
            $config['visibility'] = $visibilityConfig;
        }

        return $config === [] ? null : $config;
    }

    private function prepareTableConfig(Request $request): array
    {
        $columns = [];
        $columnIndexLookup = [];
        foreach ((array) $request->input('config.columns', []) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $label = trim((string) ($column['label'] ?? ''));
            $columnType = $column['type'] ?? 'text';

            if ($label === '' || !in_array($columnType, FormField::TABLE_COLUMN_TYPES, true)) {
                continue;
            }

            $baseKey = $this->sanitizeName((string) ($column['key'] ?? $label));
            $key = $baseKey !== '' ? $baseKey : 'column_'.($index + 1);
            $key = $this->ensureUniqueColumnKey($key, $columns);

            $allowCustomAnswer = in_array($columnType, ['checkbox', 'radio'], true)
                && !empty($column['allow_custom_answer']);

            $columns[] = [
                'key' => $key,
                'label' => $label,
                'type' => $columnType,
                'required' => $columnType === 'label' ? false : !empty($column['required']),
                'options' => array_values(array_filter(
                    FormField::normalizeOptions($columnType, $column['options'] ?? null),
                    fn ($option) => $option !== FormField::OTHER_OPTION_VALUE
                )),
                'allow_custom_answer' => $columnType === 'label' ? false : $allowCustomAnswer,
                'other_label' => FormField::normalizeOtherLabel($column['other_label'] ?? null),
            ];
            $columnIndexLookup[$key] = $index;
        }

        if ($columns === []) {
            throw ValidationException::withMessages([
                'config.columns' => 'Please add at least one table column.',
            ]);
        }

        $columnKeys = array_column($columns, 'key');
        foreach ($columns as $position => $column) {
            $sourceIndex = $columnIndexLookup[$column['key']] ?? null;
            $sourceColumn = is_int($sourceIndex) ? (array) $request->input("config.columns.{$sourceIndex}", []) : [];
            $visibility = $this->prepareColumnVisibilityConfig($sourceColumn, $column['key'], $columnKeys, $position);

            if ($visibility !== null) {
                $columns[$position]['visibility'] = $visibility;
            }
        }

        return [
            'auto_number' => $request->boolean('config.auto_number', false),
            'max_rows' => max(0, (int) $request->input('config.max_rows', 0)),
            'columns' => $columns,
        ];
    }

    private function prepareVisibilityConfig(Request $request, Form $form, string $type, ?FormField $currentField = null): ?array
    {
        if (in_array($type, ['table', 'section', 'label'], true) || !$request->boolean('visibility.enabled', false)) {
            return null;
        }

        $controllerFieldId = (int) $request->input('visibility.field_id');
        $operator = (string) $request->input('visibility.operator');
        $expectedValue = trim((string) $request->input('visibility.value'));

        if ($controllerFieldId <= 0) {
            throw ValidationException::withMessages([
                'visibility.field_id' => 'Please select a controlling field.',
            ]);
        }

        if (!in_array($operator, FormField::VISIBILITY_OPERATORS, true)) {
            throw ValidationException::withMessages([
                'visibility.operator' => 'Please select a valid visibility operator.',
            ]);
        }

        if (in_array($operator, ['equals', 'not_equals'], true) && $expectedValue === '') {
            throw ValidationException::withMessages([
                'visibility.value' => 'Please enter an expected value.',
            ]);
        }

        $controllerField = $form->fields()
            ->where('id', $controllerFieldId)
            ->whereNotIn('type', ['section', 'table', 'label'])
            ->first();

        if ($controllerField === null || ($currentField && $controllerField->id === $currentField->id)) {
            throw ValidationException::withMessages([
                'visibility.field_id' => 'Please select a valid controlling field.',
            ]);
        }

        return [
            'enabled' => true,
            'field_id' => $controllerField->id,
            'operator' => $operator,
            'value' => $expectedValue,
        ];
    }

    private function prepareColumnVisibilityConfig(array $column, string $currentKey, array $availableKeys, int $position): ?array
    {
        if (empty($column['visibility']['enabled'])) {
            return null;
        }

        $controllerField = $this->sanitizeName((string) ($column['visibility']['field'] ?? ''));
        $operator = (string) ($column['visibility']['operator'] ?? '');
        $expectedValue = trim((string) ($column['visibility']['value'] ?? ''));
        $baseErrorKey = "config.columns.{$position}.visibility";

        if ($controllerField === '') {
            throw ValidationException::withMessages([
                "{$baseErrorKey}.field" => 'Please select a controlling column.',
            ]);
        }

        if ($controllerField === $currentKey || !in_array($controllerField, $availableKeys, true)) {
            throw ValidationException::withMessages([
                "{$baseErrorKey}.field" => 'Please select a valid controlling column.',
            ]);
        }

        if (!in_array($operator, FormField::VISIBILITY_OPERATORS, true)) {
            throw ValidationException::withMessages([
                "{$baseErrorKey}.operator" => 'Please select a valid visibility operator.',
            ]);
        }

        if (in_array($operator, ['equals', 'not_equals'], true) && $expectedValue === '') {
            throw ValidationException::withMessages([
                "{$baseErrorKey}.value" => 'Please enter an expected value.',
            ]);
        }

        return [
            'enabled' => true,
            'field' => $controllerField,
            'operator' => $operator,
            'value' => $expectedValue,
        ];
    }
    private function ensureUniqueColumnKey(string $key, array $columns): string
    {
        $existingKeys = array_column($columns, 'key');
        $uniqueKey = $key;
        $suffix = 2;

        while (in_array($uniqueKey, $existingKeys, true)) {
            $uniqueKey = "{$key}_{$suffix}";
            $suffix++;
        }

        return $uniqueKey;
    }

    private function sanitizeName(string $label): string
    {
        $name = strtolower(trim($label));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');

        return $name !== '' ? $name : 'field';
    }

    private function hasConfigInput(mixed $value): bool
    {
        return $value !== null && (string) $value !== '';
    }

    private function visibilityControllerFields(Form $form, ?FormField $currentField = null)
    {
        return $form->fields()
            ->whereNotIn('type', ['section', 'table', 'label'])
            ->when($currentField !== null, fn ($query) => $query->where('id', '!=', $currentField->id))
            ->get();
    }
}
