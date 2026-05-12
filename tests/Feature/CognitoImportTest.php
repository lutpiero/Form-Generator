<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CognitoImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_public_cognito_form_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            // Step 1 — HTML page with seamless.js script tag (actual Cognito Forms format)
            'https://www.cognitoforms.com/Acme/CustomerIntake' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <script data-form="1"
                                data-key="TestFormId12345678901"
                                data-context="public"
                                src="/f/seamless.js?cachehash=abc123">
                        </script>
                    </head>
                    <body><div id="app"></div></body>
                </html>
                HTML, 200),

            // Step 2 — Internal form-definition endpoint returns the actual schema
            'https://www.cognitoforms.com/svc/load-form/form-def/TestFormId12345678901/1' => Http::response([
                'Form' => [
                    'Name' => 'Customer Intake',
                    'Description' => 'Imported from Cognito',
                    'Fields' => [
                        ['Type' => 'Section', 'Label' => 'Personal Information'],
                        ['Type' => 'Text', 'Name' => 'FullName', 'Label' => 'Full Name', 'Required' => true, 'Placeholder' => 'John Doe'],
                        ['Type' => 'Text', 'Label' => 'Biography', 'MultiLine' => true],
                        [
                            'Type' => 'Choice',
                            'Name' => 'PreferredContact',
                            'Label' => 'Preferred Contact',
                            'Presentation' => 'Radio',
                            'Choices' => [
                                ['Label' => 'Email'],
                                ['Label' => 'Phone'],
                            ],
                        ],
                        ['Type' => 'Signature', 'Label' => 'Sign Here'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'cognito_url' => 'https://www.cognitoforms.com/Acme/CustomerIntake',
            ]);

        $form = Form::first();

        $this->assertNotNull($form);
        $response->assertRedirect(route('admin.forms.show', $form));
        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, 'Imported 4 field(s)')
                && str_contains($message, 'Sign Here (Signature fields are not supported)');
        });

        $this->assertSame('Customer Intake', $form->name);
        $this->assertSame('Imported from Cognito', $form->description);

        $fields = $form->fields()->orderBy('order')->get();
        $this->assertCount(4, $fields);

        $this->assertSame('section', $fields[0]->type);
        $this->assertSame('text', $fields[1]->type);
        $this->assertTrue($fields[1]->required);
        $this->assertSame('John Doe', $fields[1]->placeholder);
        $this->assertSame('textarea', $fields[2]->type);
        $this->assertSame('radio', $fields[3]->type);
        $this->assertSame(['Email', 'Phone'], $fields[3]->options_array);
    }

    public function test_import_requires_cognitoforms_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'cognito_url' => 'https://example.com/some/form',
            ]);

        $response
            ->assertRedirect(route('admin.forms.index'))
            ->assertSessionHasErrors(['cognito_url']);
    }

    public function test_import_handles_schema_extraction_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            // HTML page has no recognisable form-ID pattern
            'https://www.cognitoforms.com/Acme/UnavailableForm' => Http::response('<html><body>No schema here.</body></html>', 200),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.index'))
            ->post(route('admin.forms.import.cognito'), [
                'cognito_url' => 'https://www.cognitoforms.com/Acme/UnavailableForm',
            ]);

        $response
            ->assertRedirect(route('admin.forms.index'))
            ->assertSessionHas('error', function (string $message) {
                return str_contains($message, 'Could not extract a Cognito form schema from the provided URL')
                    && str_contains($message, 'https://www.cognitoforms.com/YourOrg/YourFormName');
            });

        $this->assertSame(0, Form::count());
    }
}
