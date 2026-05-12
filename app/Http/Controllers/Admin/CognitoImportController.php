<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\CognitoFormsImporter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CognitoImportController extends Controller
{
    public function import(Request $request, CognitoFormsImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'cognito_url' => [
                'required',
                'url',
                function (string $attribute, string $value, Closure $fail): void {
                    $parts = parse_url($value);
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

                    if (!in_array($scheme, ['http', 'https'], true) || ($host !== 'cognitoforms.com' && !Str::endsWith($host, '.cognitoforms.com'))) {
                        $fail('The URL must be a public cognitoforms.com URL.');
                    }
                },
            ],
        ]);

        try {
            $result = $importer->import($validated['cognito_url']);

            $form = DB::transaction(function () use ($result): Form {
                $form = Form::create([
                    'name'            => $result['title'],
                    'description'     => null,
                    'is_active'       => true,
                    'captcha_enabled' => false,
                    'captcha_type'    => 'math',
                ]);

                $usedNames = [];
                $order     = 0;

                foreach ($result['fields'] as $fieldData) {
                    $name = $this->ensureUniqueFieldName(
                        $this->sanitizeName($fieldData['source']),
                        $usedNames
                    );

                    $form->fields()->create([
                        'label'         => Str::limit($fieldData['label'], 255, ''),
                        'name'          => $name,
                        'type'          => $fieldData['type'],
                        'required'      => !$fieldData['is_section'] && $fieldData['required'],
                        'placeholder'   => null,
                        'default_value' => null,
                        'options'       => null,
                        'config'        => null,
                        'order'         => $order,
                    ]);

                    $order++;
                }

                return $form;
            });

            $imported = count($result['fields']);

            return redirect()->route('admin.forms.show', $form)
                ->with('success', "Imported {$imported} field(s) from Cognito Forms.");
        } catch (\Exception $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }

    private function sanitizeName(string $name): string
    {
        $value    = strtolower(trim($name));
        $replaced = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value    = is_string($replaced) ? $replaced : '';
        $value    = trim($value, '_');

        return $value !== '' ? $value : 'field';
    }

    /**
     * @param array<string, bool> $usedNames
     */
    private function ensureUniqueFieldName(string $name, array &$usedNames): string
    {
        $base      = $name !== '' ? $name : 'field';
        $candidate = $base;
        $suffix    = 2;

        while (isset($usedNames[$candidate])) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        $usedNames[$candidate] = true;

        return $candidate;
    }
}
