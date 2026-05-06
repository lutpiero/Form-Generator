<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'captcha_enabled' => 'boolean',
            'captcha_type' => 'required|in:math,honeypot',
            'success_message' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['captcha_enabled'] = $request->boolean('captcha_enabled', false);
        $validated['slug'] = Str::slug($validated['name']);

        // Make slug unique
        $originalSlug = $validated['slug'];
        $count = 1;
        while (Form::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $count++;
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
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'captcha_enabled' => 'boolean',
            'captcha_type' => 'required|in:math,honeypot',
            'success_message' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);
        $validated['captcha_enabled'] = $request->boolean('captcha_enabled', false);

        $form->update($validated);

        return redirect()->route('admin.forms.show', $form)
            ->with('success', 'Form updated successfully.');
    }

    public function destroy(Form $form)
    {
        $form->delete();
        return redirect()->route('admin.forms.index')
            ->with('success', 'Form deleted successfully.');
    }
}
