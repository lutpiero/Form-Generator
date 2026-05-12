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
            // API endpoint attempts return 404 — fall through to HTML scraping
            'https://www.cognitoforms.com/api/forms/Acme/CustomerIntake' => Http::response('', 404),
            'https://api.cognitoforms.com/forms/Acme/CustomerIntake' => Http::response('', 404),
            'https://www.cognitoforms.com/api/Acme/CustomerIntake' => Http::response('', 404),
            // JSON content-negotiation attempt on the original URL also returns no JSON
            // The wildcard below also handles this implicitly, but the HTML URL is matched separately
            'https://www.cognitoforms.com/Acme/CustomerIntake' => Http::response(<<<'HTML'
                <html>
                    <head></head>
                    <body>
                        <script>
                            window.__INITIAL_STATE__ = {
                                "formName": "Customer Intake",
                                "description": "Imported from Cognito",
                                "fields": [
                                    {"type": "Section", "label": "Personal Information"},
                                    {"type": "Text", "name": "FullName", "label": "Full Name", "required": true, "placeholder": "John Doe"},
                                    {"type": "Text", "label": "Biography", "multiline": true},
                                    {
                                        "type": "Choice",
                                        "name": "PreferredContact",
                                        "label": "Preferred Contact",
                                        "presentation": "Radio",
                                        "choices": [
                                            {"label": "Email"},
                                            {"label": "Phone"}
                                        ]
                                    },
                                    {"type": "Signature", "label": "Sign Here"}
                                ]
                            };
                        </script>
                    </body>
                </html>
                HTML, 200),
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
            'https://www.cognitoforms.com/api/forms/Acme/UnavailableForm' => Http::response('', 404),
            'https://api.cognitoforms.com/forms/Acme/UnavailableForm' => Http::response('', 404),
            'https://www.cognitoforms.com/api/Acme/UnavailableForm' => Http::response('', 404),
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
                return str_contains($message, 'Could not load the Cognito Forms schema')
                    && str_contains($message, 'https://www.cognitoforms.com/YourOrg/YourFormName');
            });

        $this->assertSame(0, Form::count());
    }
}
