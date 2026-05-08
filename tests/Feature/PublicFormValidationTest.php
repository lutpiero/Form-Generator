<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_checkbox_field_with_custom_answer_option(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Inspection Form',
            'description' => 'A test form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $response = $this->actingAs($user)->post(route('admin.forms.fields.store', $form), [
            'label' => 'Equipment',
            'type' => 'checkbox',
            'options' => "Fuse Box\nCable",
            'allow_custom_answer' => '1',
            'other_label' => 'Lainnya',
        ]);

        $response->assertRedirect(route('admin.forms.show', $form));

        $field = $form->fields()->first();

        $this->assertNotNull($field);
        $this->assertSame(['Fuse Box', 'Cable', FormField::OTHER_OPTION_VALUE], $field->options_array);
        $this->assertSame('Lainnya', $field->other_label);
    }

    public function test_public_form_renders_other_checkbox_input_and_validation_script(): void
    {
        $field = $this->createCheckboxField(['required' => true]);

        $response = $this->get(route('forms.show', $field->form));

        $response->assertOk();
        $response->assertSee('data-form-validation', false);
        $response->assertSee('novalidate', false);
        $response->assertSee('value="__other__"', false);
        $response->assertSee('name="preferences_other"', false);
        $response->assertSee('data-other-option', false);
        $response->assertSee('Lainnya');
        $response->assertSee('js/form-validation.js', false);
    }

    public function test_checkbox_submission_requires_other_text_when_other_is_selected(): void
    {
        $field = $this->createCheckboxField(['required' => true]);

        $response = $this->from(route('forms.show', $field->form))->post(route('forms.submit', $field->form), [
            'preferences' => [FormField::OTHER_OPTION_VALUE],
            'preferences_other' => '   ',
        ]);

        $response->assertRedirect(route('forms.show', $field->form));
        $response->assertSessionHasErrors(['preferences_other' => 'Please enter a value for Lainnya.']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_checkbox_submission_stores_other_value_with_selected_options(): void
    {
        $field = $this->createCheckboxField(['required' => true]);

        $response = $this->post(route('forms.submit', $field->form), [
            'preferences' => ['Fuse Box', FormField::OTHER_OPTION_VALUE],
            'preferences_other' => 'Custom breaker',
        ]);

        $response->assertRedirect(route('forms.success', $field->form));

        $submission = FormSubmission::first();

        $this->assertNotNull($submission);
        $this->assertSame(['Fuse Box', 'other:Custom breaker'], $submission->data['preferences']);
    }

    public function test_phone_field_rejects_invalid_format(): void
    {
        $form = Form::create([
            'name' => 'Contact Form',
            'description' => 'A test form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $form->fields()->create([
            'label' => 'Phone',
            'name' => 'phone',
            'type' => 'phone',
            'required' => true,
            'order' => 0,
        ]);

        $response = $this->from(route('forms.show', $form))->post(route('forms.submit', $form), [
            'phone' => 'abc#123',
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors(['phone' => 'Please enter a valid phone number.']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    private function createCheckboxField(array $overrides = []): FormField
    {
        $form = Form::create([
            'name' => 'Checklist Form',
            'description' => 'A test form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        return $form->fields()->create(array_merge([
            'label' => 'Preferences',
            'name' => 'preferences',
            'type' => 'checkbox',
            'options' => json_encode(['Fuse Box', 'Cable', FormField::OTHER_OPTION_VALUE]),
            'config' => ['other_label' => 'Lainnya'],
            'required' => false,
            'order' => 0,
        ], $overrides));
    }
}
