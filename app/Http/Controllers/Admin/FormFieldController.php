<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormFieldController extends Controller
{
    public function create(Form $form)
    {
        return view('admin.fields.create', compact('form'));
    }

    public function store(Request $request, Form $form)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,table,section',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'allow_custom_answer' => 'boolean',
            'other_label' => 'nullable|string|max:255',
            'config.auto_number' => 'sometimes|boolean',
            'config.columns' => 'nullable|array',
            'config.columns.*.label' => 'nullable|string|max:255',
            'config.columns.*.key' => 'nullable|string|max:255',
            'config.columns.*.type' => 'nullable|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            'config.columns.*.required' => 'sometimes|boolean',
            'config.columns.*.allow_custom_answer' => 'sometimes|boolean',
            'config.columns.*.other_label' => 'nullable|string|max:255',
            'config.columns.*.options' => 'nullable',
        ]);

        $validated['form_id'] = $form->id;
        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['order'] = $form->fields()->count();
        $validated['required'] = !in_array($validated['type'], ['section', 'table'], true)
            && $request->boolean('required', false);
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
        $validated['config'] = $this->prepareConfig($request, $validated['type']);
        unset($validated['allow_custom_answer']);

        $form->fields()->create($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Field added successfully.');
    }

    public function edit(Form $form, FormField $field)
    {
        return view('admin.fields.edit', compact('form', 'field'));
    }

    public function update(Request $request, Form $form, FormField $field)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,table,section',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'order' => 'nullable|integer',
            'allow_custom_answer' => 'boolean',
            'other_label' => 'nullable|string|max:255',
            'config.auto_number' => 'sometimes|boolean',
            'config.columns' => 'nullable|array',
            'config.columns.*.label' => 'nullable|string|max:255',
            'config.columns.*.key' => 'nullable|string|max:255',
            'config.columns.*.type' => 'nullable|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            'config.columns.*.required' => 'sometimes|boolean',
            'config.columns.*.allow_custom_answer' => 'sometimes|boolean',
            'config.columns.*.other_label' => 'nullable|string|max:255',
            'config.columns.*.options' => 'nullable',
        ]);

        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['required'] = !in_array($validated['type'], ['section', 'table'], true)
            && $request->boolean('required', false);
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
        $validated['config'] = $this->prepareConfig($request, $validated['type']);
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

        if ($type === 'checkbox' && $request->boolean('allow_custom_answer', false)) {
            $optionsArray[] = FormField::OTHER_OPTION_VALUE;
        }

        return empty($optionsArray) ? null : json_encode(array_values(array_unique($optionsArray)));
    }

    private function prepareConfig(Request $request, string $type): ?array
    {
        if ($type === 'checkbox' && $request->boolean('allow_custom_answer', false)) {
            return [
                'other_label' => FormField::normalizeOtherLabel($request->input('other_label')),
            ];
        }

        if ($type !== 'table') {
            return null;
        }

        $columns = [];
        foreach ((array) $request->input('config.columns', []) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $label = trim((string) ($column['label'] ?? ''));
            $columnType = $column['type'] ?? 'text';

            if ($label === '' || !in_array($columnType, FormField::TABLE_COLUMN_TYPES, true)) {
                continue;
            }

            $baseKey = Str::snake(trim((string) ($column['key'] ?? $label)));
            $key = $baseKey !== '' ? $baseKey : 'column_'.($index + 1);
            $key = $this->ensureUniqueColumnKey($key, $columns);

            $allowCustomAnswer = $columnType === 'checkbox' && !empty($column['allow_custom_answer']);

            $columns[] = [
                'key' => $key,
                'label' => $label,
                'type' => $columnType,
                'required' => !empty($column['required']),
                'options' => array_values(array_filter(
                    FormField::normalizeOptions($columnType, $column['options'] ?? null),
                    fn ($option) => $option !== FormField::OTHER_OPTION_VALUE
                )),
                'allow_custom_answer' => $allowCustomAnswer,
                'other_label' => FormField::normalizeOtherLabel($column['other_label'] ?? null),
            ];
        }

        if ($columns === []) {
            throw ValidationException::withMessages([
                'config.columns' => 'Please add at least one table column.',
            ]);
        }

        return [
            'auto_number' => $request->boolean('config.auto_number', false),
            'columns' => $columns,
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
}
