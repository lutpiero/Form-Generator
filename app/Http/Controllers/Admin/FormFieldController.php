<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
        ]);

        $validated['form_id'] = $form->id;
        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['required'] = $request->boolean('required', false);
        $validated['order'] = $form->fields()->count();

        // Parse options for dropdown/radio/checkbox
        if (!empty($validated['options'])) {
            $optionsArray = array_filter(array_map('trim', explode("\n", $validated['options'])));
            $validated['options'] = json_encode(array_values($optionsArray));
        } else {
            $validated['options'] = null;
        }

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
            'type' => 'required|in:text,email,phone,number,textarea,dropdown,radio,checkbox',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $validated['name'] = Str::snake(Str::lower($validated['label']));
        $validated['required'] = $request->boolean('required', false);

        // Parse options
        if (!empty($validated['options'])) {
            $optionsArray = array_filter(array_map('trim', explode("\n", $validated['options'])));
            $validated['options'] = json_encode(array_values($optionsArray));
        } else {
            $validated['options'] = null;
        }

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
}
