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
        $validated = $this->validateField($request);

        $validated['form_id'] = $form->id;
        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['order'] = $form->fields()->count();
        $validated = $this->normalizeFieldData($request, $validated);

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
        $validated = $this->validateField($request);

        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated = $this->normalizeFieldData($request, $validated);

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

    protected function validateField(Request $request): array
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,section,table',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'order' => 'nullable|integer',
            'config.auto_number' => 'nullable|boolean',
            'config.columns' => 'nullable|array',
            'config.columns.*.key' => 'nullable|string|max:255',
            'config.columns.*.label' => 'nullable|string|max:255',
            'config.columns.*.type' => 'nullable|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            'config.columns.*.required' => 'nullable|boolean',
            'config.columns.*.options' => 'nullable|string',
        ]);

        if (($validated['type'] ?? null) === 'table') {
            $request->validate([
                'config.columns' => 'required|array|min:1',
                'config.columns.*.label' => 'required|string|max:255',
                'config.columns.*.type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            ]);
        }

        return $validated;
    }

    protected function normalizeFieldData(Request $request, array $validated): array
    {
        if ($validated['type'] === 'table') {
            $validated['required'] = false;
            $validated['placeholder'] = null;
            $validated['default_value'] = null;
            $validated['options'] = null;
            $validated['config'] = $this->buildTableConfig($request);

            return $validated;
        }

        $validated['required'] = $validated['type'] === 'section' ? false : $request->boolean('required', false);
        $validated['config'] = null;

        if (!empty($validated['options'])) {
            $optionsArray = array_filter(array_map('trim', explode("\n", $validated['options'])));
            $validated['options'] = json_encode(array_values($optionsArray));
        } else {
            $validated['options'] = null;
        }

        return $validated;
    }

    protected function buildTableConfig(Request $request): array
    {
        $columns = [];
        $usedKeys = [];

        foreach ($request->input('config.columns', []) as $column) {
            $label = trim((string) ($column['label'] ?? ''));
            $type = $column['type'] ?? 'text';

            if ($label === '') {
                continue;
            }

            $baseKey = Str::snake(Str::lower($column['key'] ?? $label));
            $baseKey = $baseKey !== '' ? $baseKey : 'column';
            $key = $baseKey;
            $suffix = 2;

            while (in_array($key, $usedKeys, true)) {
                $key = "{$baseKey}_{$suffix}";
                $suffix++;
            }

            $options = [];
            if (in_array($type, ['dropdown', 'radio', 'checkbox'], true)) {
                $options = array_values(array_filter(array_map('trim', explode("\n", (string) ($column['options'] ?? '')))));
            }

            $columns[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => filter_var($column['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'options' => $options,
            ];

            $usedKeys[] = $key;
        }

        if ($columns === []) {
            throw ValidationException::withMessages([
                'config.columns' => 'Add at least one table column.',
            ]);
        }

        return [
            'auto_number' => $request->boolean('config.auto_number'),
            'columns' => $columns,
        ];
    }
}
