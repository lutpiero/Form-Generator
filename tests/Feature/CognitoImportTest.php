<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CognitoImportTest extends TestCase
{
    use RefreshDatabase;

    private function validJson(): string
    {
        return json_encode([
            'https://www.cognitoforms.com/Acme/CustomerIntake' => [
                'formEntry' => 'Forms.FormEntry.Acme.CustomerIntake',
                'sections'  => [
                    'PersonalInfo' => [
                        'label'  => 'Personal Information',
                        'type'   => 'Forms.FormEntry.Acme.CustomerIntake.PersonalInfo',
                        'fields' => [
                            [
                                'key'      => 'FullName',
                                'label'    => 'Full Name',
                                'type'     => 'name',
                                'subtype'  => 'none',
                                'required' => true,
                            ],
                            [
                                'key'      => 'Bio',
                                'label'    => 'Biography',
                                'type'     => 'text',
                                'subtype'  => 'multiplelines',
                                'required' => false,
                            ],
                            [
                                'key'      => 'ContactEmail',
                                'label'    => 'Contact Email',
                                'type'     => 'email',
                                'subtype'  => 'none',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_admin_can_import_cognito_form_from_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'json_data' => $this->validJson(),
            ]);

        $form = Form::first();

        $this->assertNotNull($form);
        $response->assertRedirect(route('admin.forms.show', $form));
        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, '3 fields') && str_contains($message, '1 sections');
        });

        $this->assertSame('CustomerIntake', $form->name);
        $this->assertNull($form->description);

        $fields = $form->fields()->orderBy('order')->get();
        $this->assertCount(4, $fields);

        $this->assertSame('label', $fields[0]->type);
        $this->assertSame('Personal Information', $fields[0]->label);
        $this->assertSame('text', $fields[1]->type);
        $this->assertTrue($fields[1]->required);
        $this->assertSame('Full Name', $fields[1]->label);
        $this->assertSame('textarea', $fields[2]->type);
        $this->assertFalse($fields[2]->required);
        $this->assertSame('Biography', $fields[2]->label);
        $this->assertSame('email', $fields[3]->type);
        $this->assertTrue($fields[3]->required);
        $this->assertSame('Contact Email', $fields[3]->label);
    }

    public function test_import_requires_json_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), []);

        $response
            ->assertRedirect(route('admin.forms.index'))
            ->assertSessionHasErrors(['json_data']);
    }

    public function test_import_handles_invalid_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'json_data' => 'this is not valid json',
            ]);

        $response
            ->assertRedirect(route('admin.forms.index'))
            ->assertSessionHas('error', function (string $message) {
                return str_contains($message, 'Invalid JSON');
            });

        $this->assertSame(0, Form::count());
    }

    public function test_import_handles_missing_sections_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $json = json_encode([
            'https://www.cognitoforms.com/Acme/BrokenForm' => [
                'formEntry' => 'Forms.FormEntry.Acme.BrokenForm',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'json_data' => $json,
            ]);

        $response
            ->assertRedirect(route('admin.forms.index'))
            ->assertSessionHas('error', function (string $message) {
                return str_contains($message, 'missing "sections" key');
            });

        $this->assertSame(0, Form::count());
    }
}
