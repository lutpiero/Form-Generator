<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_admin_can_create_file_field_with_size_and_type_limits(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $form = $this->createForm();

        $response = $this->actingAs($user)->post(route('admin.forms.fields.store', $form), [
            'label' => 'Resume',
            'type' => 'file',
            'required' => '1',
            'config' => [
                'max_size_kb' => 2048,
                'allowed_extensions' => ['pdf', 'docx'],
            ],
        ]);

        $response->assertRedirect(route('admin.forms.show', $form));

        $field = $form->fields()->first();

        $this->assertNotNull($field);
        $this->assertSame('file', $field->type);
        $this->assertSame(2048, $field->file_max_size_kb);
        $this->assertSame(['pdf', 'docx'], $field->file_allowed_extensions);
    }

    public function test_admin_must_select_at_least_one_allowed_file_type(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $form = $this->createForm();

        $response = $this->actingAs($user)->post(route('admin.forms.fields.store', $form), [
            'label' => 'Resume',
            'type' => 'file',
            'config' => [
                'max_size_kb' => 2048,
                'allowed_extensions' => [],
            ],
        ]);

        $response->assertSessionHasErrors('config.allowed_extensions');
    }

    public function test_public_form_renders_file_input_with_limits(): void
    {
        $field = $this->createFileField([
            'config' => [
                'max_size_kb' => 1024,
                'allowed_extensions' => ['pdf', 'png'],
            ],
        ]);

        $response = $this->get(route('forms.show', $field->form));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('type="file"', false);
        $response->assertSee('accept=".pdf,.png"', false);
        $response->assertSee('data-max-size-kb="1024"', false);
        $response->assertSee('Allowed types: PDF, PNG.', false);
    }

    public function test_valid_file_upload_is_stored_securely(): void
    {
        $field = $this->createFileField([
            'name' => 'resume',
            'required' => true,
            'config' => [
                'max_size_kb' => 2048,
                'allowed_extensions' => ['pdf'],
            ],
        ]);

        $uploadedFile = UploadedFile::fake()->create('my-resume.pdf', 100, 'application/pdf');

        $response = $this->post(route('forms.submit', $field->form), [
            'resume' => $uploadedFile,
        ]);

        $response->assertRedirect(route('forms.success', $field->form));

        $submission = FormSubmission::first();
        $this->assertNotNull($submission);

        $fileData = $submission->data['resume'];
        $this->assertIsArray($fileData);
        $this->assertSame('my-resume.pdf', $fileData['original_name']);
        $this->assertSame('application/pdf', $fileData['mime']);
        $this->assertStringStartsWith('form-uploads/'.$field->form_id.'/'.$submission->id.'/', $fileData['path']);
        $this->assertTrue(Storage::disk('local')->exists($fileData['path']));
    }

    public function test_oversized_file_is_rejected(): void
    {
        $field = $this->createFileField([
            'name' => 'resume',
            'config' => [
                'max_size_kb' => 100,
                'allowed_extensions' => ['pdf'],
            ],
        ]);

        $uploadedFile = UploadedFile::fake()->create('large.pdf', 200, 'application/pdf');

        $response = $this->post(route('forms.submit', $field->form), [
            'resume' => $uploadedFile,
        ]);

        $response->assertSessionHasErrors('resume');
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $field = $this->createFileField([
            'name' => 'resume',
            'config' => [
                'max_size_kb' => 2048,
                'allowed_extensions' => ['pdf'],
            ],
        ]);

        $uploadedFile = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $response = $this->post(route('forms.submit', $field->form), [
            'resume' => $uploadedFile,
        ]);

        $response->assertSessionHasErrors('resume');
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_required_file_field_must_be_provided(): void
    {
        $field = $this->createFileField([
            'name' => 'resume',
            'required' => true,
        ]);

        $response = $this->post(route('forms.submit', $field->form), []);

        $response->assertSessionHasErrors('resume');
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_admin_can_download_submission_file(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $field = $this->createFileField([
            'name' => 'resume',
            'config' => [
                'max_size_kb' => 2048,
                'allowed_extensions' => ['pdf'],
            ],
        ]);

        $uploadedFile = UploadedFile::fake()->create('my-resume.pdf', 100, 'application/pdf');

        $this->post(route('forms.submit', $field->form), [
            'resume' => $uploadedFile,
        ]);

        $submission = FormSubmission::first();

        $response = $this->actingAs($user)->get(route('admin.forms.submissions.files.download', [
            $field->form,
            $submission,
            $field,
        ]));

        $response->assertOk();
        $response->assertDownload('my-resume.pdf');
    }

    public function test_guest_cannot_download_submission_file(): void
    {
        $field = $this->createFileField(['name' => 'resume']);

        $this->post(route('forms.submit', $field->form), [
            'resume' => UploadedFile::fake()->create('my-resume.pdf', 100, 'application/pdf'),
        ]);

        $submission = FormSubmission::first();

        $response = $this->get(route('admin.forms.submissions.files.download', [
            $field->form,
            $submission,
            $field,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_deleting_submission_removes_uploaded_file(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $field = $this->createFileField(['name' => 'resume']);

        $this->post(route('forms.submit', $field->form), [
            'resume' => UploadedFile::fake()->create('my-resume.pdf', 100, 'application/pdf'),
        ]);

        $submission = FormSubmission::first();
        $path = $submission->data['resume']['path'];
        $this->assertTrue(Storage::disk('local')->exists($path));

        $this->actingAs($user)->delete(route('admin.forms.submissions.destroy', [
            $field->form,
            $submission,
        ]));

        Storage::disk('local')->assertMissing($path);
    }

    private function createForm(): Form
    {
        return Form::create([
            'name' => 'Upload Form',
            'description' => 'A test form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);
    }

    private function createFileField(array $overrides = []): FormField
    {
        $form = $this->createForm();

        return $form->fields()->create(array_merge([
            'label' => 'Resume',
            'name' => 'resume',
            'type' => 'file',
            'required' => false,
            'config' => [
                'max_size_kb' => FormField::DEFAULT_MAX_FILE_SIZE_KB,
                'allowed_extensions' => ['pdf'],
            ],
            'order' => 0,
        ], $overrides));
    }
}
