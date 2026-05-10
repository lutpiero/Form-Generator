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

    // Matches the real-world name produced by Str::snake(Str::lower('(boleh pilih lebih dari 1)'))
    private const SPECIAL_CHAR_FIELD_NAME = '(boleh_pilih_lebih_dari1)';

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

    public function test_form_with_special_character_field_name_renders_correct_ids(): void
    {
        // Field names derived from labels like "(boleh pilih lebih dari 1)" produce
        // names like "(boleh_pilih_lebih_dari1)" which contain CSS-selector special
        // characters.  The HTML must emit the raw name as the element id so that
        // getElementById() (used in form-validation.js) can locate it safely.
        $form = Form::create([
            'name' => 'Special Char Form',
            'description' => '',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $specialName = self::SPECIAL_CHAR_FIELD_NAME;
        $form->fields()->create([
            'label' => 'Pilihan',
            'name' => $specialName,
            'type' => 'checkbox',
            'options' => json_encode(['Option A', FormField::OTHER_OPTION_VALUE]),
            'config' => ['other_label' => 'Lainnya'],
            'required' => false,
            'order' => 0,
        ]);

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();

        // The "other" text input must have the raw id (with parens) so getElementById works.
        $otherId = $specialName . '_other';
        $response->assertSee('id="' . $otherId . '"', false);

        // The toggle checkbox must carry data-other-input-id pointing to that id.
        $response->assertSee('data-other-input-id="' . $otherId . '"', false);

        // The page must NOT contain a querySelector call using a CSS #id selector
        // built from a dynamic value (which would break on special characters).
        $response->assertDontSee('querySelector(`#${', false);
        $response->assertDontSee("querySelector('#' +", false);
    }

    public function test_form_with_special_character_field_name_accepts_other_submission(): void
    {
        $form = Form::create([
            'name' => 'Special Char Form',
            'description' => '',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $specialName = self::SPECIAL_CHAR_FIELD_NAME;
        $form->fields()->create([
            'label' => 'Pilihan',
            'name' => $specialName,
            'type' => 'checkbox',
            'options' => json_encode(['Option A', FormField::OTHER_OPTION_VALUE]),
            'config' => ['other_label' => 'Lainnya'],
            'required' => false,
            'order' => 0,
        ]);

        $response = $this->post(route('forms.submit', $form), [
            $specialName => [FormField::OTHER_OPTION_VALUE],
            $specialName . '_other' => 'Custom answer',
        ]);

        $response->assertRedirect(route('forms.success', $form));

        $submission = FormSubmission::first();
        $this->assertNotNull($submission);
        $this->assertSame(['other:Custom answer'], $submission->data[$specialName]);
    }

    public function test_conditional_field_is_hidden_on_initial_load_when_condition_is_not_met(): void
    {
        [$form] = $this->createConditionalVisibilityForm();

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('data-field-name="company_name"', false);
        $response->assertSee('data-visibility-enabled="true"', false);
        $response->assertSee('data-visibility-field="employment_status"', false);
        $response->assertSee('data-visibility-operator="equals"', false);
        $response->assertSee('data-visibility-value="employed"', false);
        $response->assertSee('data-visibility-state="hidden"', false);
    }

    public function test_conditional_field_is_shown_when_condition_is_met(): void
    {
        [$form] = $this->createConditionalVisibilityForm();

        $response = $this
            ->withSession(['_old_input' => ['employment_status' => 'employed']])
            ->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('data-field-name="company_name"', false);
        $response->assertSee('data-visibility-state="visible"', false);
    }

    public function test_hidden_required_conditional_field_does_not_block_submission(): void
    {
        [$form] = $this->createConditionalVisibilityForm();

        $response = $this->post(route('forms.submit', $form), [
            'employment_status' => 'unemployed',
        ]);

        $response->assertRedirect(route('forms.success', $form));
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_shown_required_conditional_field_blocks_submission_when_empty(): void
    {
        [$form] = $this->createConditionalVisibilityForm();

        $response = $this->from(route('forms.show', $form))->post(route('forms.submit', $form), [
            'employment_status' => 'employed',
            'company_name' => '   ',
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors(['company_name']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_hidden_conditional_field_value_is_not_stored(): void
    {
        [$form] = $this->createConditionalVisibilityForm();

        $response = $this->post(route('forms.submit', $form), [
            'employment_status' => 'unemployed',
            'company_name' => 'Should not persist',
        ]);

        $response->assertRedirect(route('forms.success', $form));

        $submission = FormSubmission::first();
        $this->assertNotNull($submission);
        $this->assertArrayNotHasKey('company_name', $submission->data);
    }

    public function test_conditional_field_supports_is_not_empty_operator(): void
    {
        [$form] = $this->createConditionalVisibilityForm(
            controllerType: 'text',
            controllerName: 'employment_status',
            operator: 'is_not_empty',
            value: ''
        );

        $response = $this
            ->withSession(['_old_input' => ['employment_status' => 'any value']])
            ->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('data-visibility-operator="is_not_empty"', false);
        $response->assertSee('data-visibility-state="visible"', false);
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

    private function createConditionalVisibilityForm(
        string $controllerType = 'radio',
        string $controllerName = 'employment_status',
        string $operator = 'equals',
        string $value = 'employed'
    ): array
    {
        $form = Form::create([
            'name' => 'Employment Form',
            'description' => 'A test form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $controllerField = $form->fields()->create([
            'label' => 'Employment Status',
            'name' => $controllerName,
            'type' => $controllerType,
            'options' => in_array($controllerType, ['radio', 'dropdown'], true) ? json_encode(['employed', 'unemployed']) : null,
            'required' => true,
            'order' => 0,
        ]);

        $dependentField = $form->fields()->create([
            'label' => 'Company Name',
            'name' => 'company_name',
            'type' => 'text',
            'required' => true,
            'order' => 1,
            'config' => [
                'visibility' => [
                    'enabled' => true,
                    'field_id' => $controllerField->id,
                    'operator' => $operator,
                    'value' => $value,
                ],
            ],
        ]);

        return [$form, $controllerField, $dependentField];
    }
}
