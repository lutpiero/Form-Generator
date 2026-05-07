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
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,section',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'allow_custom_answer' => 'boolean',
        ]);

        $validated['form_id'] = $form->id;
        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['order'] = $form->fields()->count();
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
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
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox,section',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'order' => 'nullable|integer',
            'allow_custom_answer' => 'boolean',
        ]);

        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['required'] = $validated['type'] === 'section' ? false : $request->boolean('required', false);
        $validated['options'] = $this->prepareOptions($request, $validated['type'], $validated['options'] ?? null);
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
        if (!in_array($type, ['dropdown', 'radio', 'checkbox'], true)) {
            return null;
        }

        $optionsArray = array_values(array_filter(array_map('trim', explode("\n", (string) $options))));
        $optionsArray = array_values(array_filter(
            $optionsArray,
            fn ($option) => $option !== FormField::OTHER_OPTION_VALUE
        ));

        if ($type === 'checkbox' && $request->boolean('allow_custom_answer', false)) {
            $optionsArray[] = FormField::OTHER_OPTION_VALUE;
        }

        return empty($optionsArray) ? null : json_encode(array_values(array_unique($optionsArray)));
    }
}
