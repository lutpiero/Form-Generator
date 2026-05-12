<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CognitoFormsImporter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

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

            $message = "Imported {$result['imported']} field(s) from Cognito Forms.";
            if ($result['skipped'] !== []) {
                $message .= ' Skipped: '.implode('; ', $result['skipped']).'.';
            }

            return redirect()->route('admin.forms.show', $result['form'])
                ->with('success', $message);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }
}
