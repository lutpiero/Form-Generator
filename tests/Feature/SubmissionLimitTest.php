<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'Test Form',
            'slug' => 'test-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ], $overrides));
    }

    private function addTextField(Form $form): FormField
    {
        return $form->fields()->create([
            'label' => 'Name',
            'name' => 'name',
            'type' => 'text',
            'required' => false,
            'order' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // Public show page
    // -------------------------------------------------------------------------

    public function test_form_shows_blocked_message_when_before_start(): void
    {
        $form = $this->makeForm(['submission_start_at' => now()->addDay()]);
        $this->addTextField($form);

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('not yet open for submissions');
        $response->assertDontSee('id="public-form"', false);
    }

    public function test_form_shows_blocked_message_when_after_end(): void
    {
        $form = $this->makeForm(['submission_end_at' => now()->subDay()]);
        $this->addTextField($form);

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('no longer accepting submissions');
        $response->assertDontSee('id="public-form"', false);
    }

    public function test_form_shows_blocked_message_when_max_submissions_reached(): void
    {
        $form = $this->makeForm(['max_submissions' => 1]);
        $this->addTextField($form);
        FormSubmission::create(['form_id' => $form->id, 'data' => []]);

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('maximum number of submissions');
        $response->assertDontSee('id="public-form"', false);
    }

    public function test_form_shows_form_when_within_window(): void
    {
        $form = $this->makeForm([
            'submission_start_at' => now()->subHour(),
            'submission_end_at' => now()->addHour(),
            'max_submissions' => 5,
        ]);
        $this->addTextField($form);

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('id="public-form"', false);
    }

    // -------------------------------------------------------------------------
    // Submit endpoint
    // -------------------------------------------------------------------------

    public function test_submission_is_blocked_before_start_at(): void
    {
        $form = $this->makeForm(['submission_start_at' => now()->addDay()]);
        $this->addTextField($form);

        $response = $this->post(route('forms.submit', $form), ['name' => 'Alice']);

        $response->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_submission_is_blocked_after_end_at(): void
    {
        $form = $this->makeForm(['submission_end_at' => now()->subDay()]);
        $this->addTextField($form);

        $response = $this->post(route('forms.submit', $form), ['name' => 'Alice']);

        $response->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_submission_is_blocked_when_max_reached(): void
    {
        $form = $this->makeForm(['max_submissions' => 2]);
        $this->addTextField($form);
        FormSubmission::create(['form_id' => $form->id, 'data' => []]);
        FormSubmission::create(['form_id' => $form->id, 'data' => []]);

        $response = $this->post(route('forms.submit', $form), ['name' => 'Alice']);

        $response->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 2);
    }

    public function test_submission_is_accepted_within_limits(): void
    {
        $form = $this->makeForm([
            'submission_start_at' => now()->subHour(),
            'submission_end_at' => now()->addHour(),
            'max_submissions' => 5,
        ]);
        $this->addTextField($form);
        FormSubmission::create(['form_id' => $form->id, 'data' => []]);

        $response = $this->post(route('forms.submit', $form), ['name' => 'Alice']);

        $response->assertRedirect(route('forms.success', $form));
        $this->assertDatabaseCount('form_submissions', 2);
    }

    public function test_submission_accepted_when_no_limits_set(): void
    {
        $form = $this->makeForm();
        $this->addTextField($form);

        $response = $this->post(route('forms.submit', $form), ['name' => 'Alice']);

        $response->assertRedirect(route('forms.success', $form));
        $this->assertDatabaseCount('form_submissions', 1);
    }

    // -------------------------------------------------------------------------
    // Admin validation
    // -------------------------------------------------------------------------

    public function test_admin_can_set_submission_limits_on_create(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(route('admin.forms.store'), [
            'name' => 'Limited Form',
            'slug' => 'limited-form',
            'captcha_type' => 'math',
            'is_active' => '1',
            'max_submissions' => '10',
            'submission_start_at' => '2030-01-01T00:00',
            'submission_end_at' => '2030-12-31T23:59',
        ]);

        $response->assertRedirect();
        $form = Form::where('slug', 'limited-form')->first();
        $this->assertNotNull($form);
        $this->assertSame(10, $form->max_submissions);
        $this->assertNotNull($form->submission_start_at);
        $this->assertNotNull($form->submission_end_at);
    }

    public function test_admin_max_submissions_must_be_at_least_one(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(route('admin.forms.store'), [
            'name' => 'Bad Form',
            'slug' => 'bad-form',
            'captcha_type' => 'math',
            'max_submissions' => '0',
        ]);

        $response->assertSessionHasErrors(['max_submissions']);
    }

    public function test_admin_end_must_be_after_start(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(route('admin.forms.store'), [
            'name' => 'Bad Form',
            'slug' => 'bad-form',
            'captcha_type' => 'math',
            'submission_start_at' => '2030-06-01T00:00',
            'submission_end_at' => '2030-01-01T00:00',
        ]);

        $response->assertSessionHasErrors(['submission_end_at']);
    }
}
