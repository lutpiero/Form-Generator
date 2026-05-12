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

            // Step 2 — Internal form-definition endpoint returns a JS IIFE (not JSON)
            'https://www.cognitoforms.com/svc/load-form/form-def/TestFormId12345678901/1' => Http::response(<<<'JS'
(function (apiKey, formId, tmpl, model, theme, peopleFormEmailPath) {
})(
    "TestFormId12345678901",
    "1",
    "<c-section source='PersonalInfo'></c-section><c-field source='FullName' type='name' subtype='none' field-index='1' :cols='10'></c-field><c-field source='Bio' type='text' subtype='multiplelines' field-index='2'></c-field><c-field source='ContactEmail' type='email' subtype='none' field-index='3'></c-field>",
    (function(core, getModule) {
        var options = {
            'Forms.FormEntry.Acme.CustomerIntake': {
                Form: {
                    init: function() { return {"Name":"Customer Intake"}; }
                },
                PersonalInfo: {
                    label: "Personal Information",
                    type: 'Forms.FormEntry.Acme.CustomerIntake.PersonalInfo'
                }
            },
            'Forms.FormEntry.Acme.CustomerIntake.PersonalInfo': {
                FullName: {
                    label: "Full Name",
                    required: true,
                    type: 'Name'
                },
                Bio: {
                    label: "Biography",
                    type: String
                },
                ContactEmail: {
                    label: "Contact Email",
                    required: true,
                    type: String
                }
            }
        };
        return options;
    }),
    {isChameleon: false},
    null
);
JS, 200),
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
            return str_contains($message, 'Imported 4 field(s)');
        });

        $this->assertSame('Customer Intake', $form->name);
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
                return str_contains($message, 'Could not find the Cognito Forms script tag');
            });

        $this->assertSame(0, Form::count());
    }
}
