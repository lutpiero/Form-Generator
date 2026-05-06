<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;

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
            $fieldRules = [];
            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }
            if ($field->type === 'email') {
                $fieldRules[] = 'email';
            }
            if ($field->type === 'number') {
                $fieldRules[] = 'numeric';
            }
            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        // Filter only form field data
        $data = [];
        foreach ($form->fields as $field) {
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
}
