<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function index()
    {
        $forms = Form::withCount('submissions')->latest()->paginate(10);
        return view('admin.forms.index', compact('forms'));
    }

    public function create()
    {
        return view('admin.forms.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('forms', 'slug'),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'captcha_enabled' => 'boolean',
            'captcha_type' => 'required|in:math,honeypot',
            'success_message' => 'nullable|string',
            'header_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['captcha_enabled'] = $request->boolean('captcha_enabled', false);

        if ($request->hasFile('header_image')) {
            $validated['header_image'] = $request->file('header_image')->store('form-headers', 'public');
        }

        $form = Form::create($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Form created successfully.');
    }

    public function show(Form $form)
    {
        $form->load('fields', 'submissions');
        return view('admin.forms.show', compact('form'));
    }

    public function edit(Form $form)
    {
        return view('admin.forms.edit', compact('form'));
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('forms', 'slug')->ignore($form->id),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'captcha_enabled' => 'boolean',
            'captcha_type' => 'required|in:math,honeypot',
            'success_message' => 'nullable|string',
            'header_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'remove_header_image' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);
        $validated['captcha_enabled'] = $request->boolean('captcha_enabled', false);

        if ($request->boolean('remove_header_image')) {
            if ($form->header_image) {
                Storage::disk('public')->delete($form->header_image);
            }
            $validated['header_image'] = null;
        } elseif ($request->hasFile('header_image')) {
            if ($form->header_image) {
                Storage::disk('public')->delete($form->header_image);
            }
            $validated['header_image'] = $request->file('header_image')->store('form-headers', 'public');
        } else {
            unset($validated['header_image']);
        }

        unset($validated['remove_header_image']);

        $form->update($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Form updated successfully.');
    }

    public function destroy(Form $form)
    {
        if ($form->header_image) {
            Storage::disk('public')->delete($form->header_image);
        }
        $form->delete();
        return redirect()->route('admin.forms.index')
            ->with('success', 'Form deleted successfully.');
    }
}
