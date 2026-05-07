<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
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
        $messages = [];
        $attributes = [];

        foreach ($form->fields as $field) {
            if ($field->type === 'section') {
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
                    break;
                case 'checkbox':
                    $fieldRules[] = 'array';

                    if (!empty($field->options_array)) {
                        $rules["{$field->name}.*"] = ['string', Rule::in($field->options_array)];
                    }

                    if ($field->hasOtherOption()) {
                        $otherFieldName = $field->other_input_name;
                        $otherChecked = in_array(FormField::OTHER_OPTION_VALUE, (array) $request->input($field->name, []), true);

                        $rules[$otherFieldName] = [
                            $otherChecked ? 'required' : 'nullable',
                            'string',
                            'max:255',
                        ];
                        $attributes[$otherFieldName] = 'Other';
                        $messages["{$otherFieldName}.required"] = 'Please enter a value for Other.';
                    }
                    break;
            }

            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules, $messages, $attributes);

        // Filter only form field data (exclude section fields)
        $data = [];
        foreach ($form->fields as $field) {
            if ($field->type === 'section') {
                continue;
            }

            $value = $validated[$field->name] ?? null;

            if ($field->type === 'checkbox') {
                $value = array_values(array_filter(
                    (array) $value,
                    fn ($item) => $item !== null && $item !== ''
                ));

                if ($field->hasOtherOption() && in_array(FormField::OTHER_OPTION_VALUE, $value, true)) {
                    $otherValue = trim((string) ($validated[$field->other_input_name] ?? ''));
                    $value = array_map(
                        fn ($item) => $item === FormField::OTHER_OPTION_VALUE ? FormField::formatOtherResponse($otherValue) : $item,
                        $value
                    );
                }
            }

            $data[$field->name] = $value;
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
