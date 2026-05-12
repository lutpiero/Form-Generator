<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CognitoFormsImporter
{
    /** Minimum and maximum character length for a valid Cognito Forms internal form ID. */
    private const FORM_ID_MIN_LENGTH = 10;
    private const FORM_ID_MAX_LENGTH = 50;

    private const BROWSER_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Referer' => 'https://www.cognitoforms.com/',
    ];

    /**
     * @return array{form: Form, imported: int, skipped: array<int, string>}
     */
    public function import(string $url): array
    {
        $schema = $this->fetchSchema($url);
        $sourceFields = $schema['fields'];

        if ($sourceFields === []) {
            throw new RuntimeException('No form fields were found in the Cognito Forms page schema.');
        }

        return DB::transaction(function () use ($schema, $sourceFields) {
            $form = Form::create([
                'name' => $schema['formTitle'],
                'description' => null,
                'is_active' => true,
                'captcha_enabled' => false,
                'captcha_type' => 'math',
            ]);

            $usedNames = [];
            $imported = 0;
            $skipped = [];

            foreach ($sourceFields as $fieldData) {
                $mappedField = $this->mapField($fieldData);

                if ($mappedField['skip']) {
                    $skipped[] = sprintf('%s (%s)', $mappedField['label'], $mappedField['reason']);
                    continue;
                }

                $fieldName = $this->ensureUniqueFieldName($this->sanitizeName($mappedField['name']), $usedNames);

                $form->fields()->create([
                    'label' => Str::limit($mappedField['label'], 255, ''),
                    'name' => $fieldName,
                    'type' => $mappedField['type'],
                    'required' => !in_array($mappedField['type'], ['section', 'label'], true) && $mappedField['required'],
                    'placeholder' => null,
                    'default_value' => null,
                    'options' => $mappedField['options'] === [] ? null : json_encode($mappedField['options']),
                    'config' => null,
                    'order' => $imported,
                ]);

                $imported++;
            }

            if ($imported === 0) {
                throw new RuntimeException('No supported fields could be imported from this Cognito Forms URL.');
            }

            return [
                'form' => $form,
                'imported' => $imported,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Fetch and parse the form definition from a public Cognito Forms URL using a two-step approach:
     *   Step 1 – GET the HTML shell and extract the internal form key and form number.
     *   Step 2 – Fetch the JS form-definition endpoint and parse the IIFE payload.
     *
     * @return array{formTitle: string, fields: array<int, array<string, mixed>>}
     */
    private function fetchSchema(string $url): array
    {
        // Step 1 — Fetch HTML shell and extract form key / form number
        $htmlResponse = Http::timeout(20)
            ->withHeaders(array_merge(self::BROWSER_HEADERS, [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]))
            ->get($url);

        if (!$htmlResponse->successful()) {
            throw new RuntimeException('Unable to fetch the Cognito Forms page. Please verify the URL is public and accessible.');
        }

        $formIdData = $this->extractFormIdAndNumber($htmlResponse->body());

        if ($formIdData === null) {
            throw new RuntimeException(
                'Could not extract a Cognito form schema from the provided URL. '
                . 'Please ensure the form is public and the URL is correct. '
                . 'Example format: https://www.cognitoforms.com/YourOrg/YourFormName'
            );
        }

        [$formKey, $formNumber] = $formIdData;

        Log::debug('CognitoFormsImporter: extracted form ID', ['formKey' => $formKey, 'formNumber' => $formNumber, 'url' => $url]);

        // Step 2 — Fetch the JS form definition
        $jsResponse = Http::timeout(20)
            ->withHeaders(array_merge(self::BROWSER_HEADERS, [
                'Accept' => '*/*',
                'Referer' => $url,
            ]))
            ->get("https://www.cognitoforms.com/svc/load-form/form-def/{$formKey}/{$formNumber}");

        if (!$jsResponse->successful()) {
            throw new RuntimeException(
                'Could not load the Cognito Forms schema. The form definition endpoint returned an unexpected response.'
            );
        }

        $js = $jsResponse->body();

        if (empty($js)) {
            throw new RuntimeException(
                'Could not load the Cognito Forms schema. The form definition endpoint returned an empty response.'
            );
        }

        Log::debug('CognitoFormsImporter: received JS form definition', [
            'formKey' => $formKey,
            'formNumber' => $formNumber,
            'length' => strlen($js),
        ]);

        return $this->parseJsFormDefinition($js, $formNumber);
    }

    /**
     * Parse the Cognito Forms JS IIFE payload into a structured PHP array.
     *
     * The JS has the shape:
     *   (function(apiKey, formId, tmpl, model, theme, ...) { ... })(
     *       "KEY", "FORM_NUMBER", "HTML_TEMPLATE", (function(core, getModule) { ... }), ..., null
     *   );
     *
     * We extract the 3rd argument (HTML template) and the 4th argument (model function body).
     *
     * @return array{formTitle: string, fields: array<int, array<string, mixed>>}
     */
    private function parseJsFormDefinition(string $js, string $formNumber): array
    {
        $tmpl = $this->extractTmplString($js, $formNumber);

        if ($tmpl === null) {
            Log::warning('CognitoFormsImporter: could not extract template string from JS', ['length' => strlen($js)]);
            throw new RuntimeException(
                'Could not parse the Cognito Forms page schema. The form definition format was not recognised.'
            );
        }

        $formTitle = $this->extractFormTitle($js);
        $fields = $this->buildFieldList($tmpl, $js);

        return [
            'formTitle' => $formTitle,
            'fields' => $fields,
        ];
    }

    /**
     * Extract and decode the HTML template string (3rd IIFE argument) from the raw JS.
     *
     * In the JS, the arguments are: ("apiKey", "formNumber", "tmplString", modelFn, theme, null).
     * We locate the 3rd argument by matching `"<formNumber>",` followed by a quoted string.
     */
    private function extractTmplString(string $js, string $formNumber): ?string
    {
        $escapedNumber = preg_quote($formNumber, '/');

        // Match: "formNumber", "tmpl string (possibly with JS escape sequences)"
        if (!preg_match('/"' . $escapedNumber . '",\s*"((?:[^"\\\\]|\\\\.)*)"/s', $js, $m)) {
            return null;
        }

        $raw = $m[1];

        // 1. Decode \uXXXX unicode escapes (e.g. \u003c → <, \u0027 → ')
        $decoded = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $m): string {
                $char = mb_chr(hexdec($m[1]), 'UTF-8');
                // mb_chr returns false for invalid codepoints; fall back to the original sequence
                return $char !== false ? $char : $m[0];
            },
            $raw
        );

        if (!is_string($decoded)) {
            $decoded = $raw;
        }

        // 2. Decode remaining simple JS escape sequences
        $decoded = str_replace(
            ['\\\\', '\\"', '\\/', '\\n', '\\r', '\\t'],
            ['\\',   '"',   '/',   "\n",  "\r",  "\t"],
            $decoded
        );

        return $decoded;
    }

    /**
     * Extract the form title from the model JS.
     * Looks for `"Name": "..."` inside the options object.
     */
    private function extractFormTitle(string $js): string
    {
        if (preg_match('/"Name"\s*:\s*"([^"]+)"/', $js, $m)) {
            return $m[1];
        }

        return 'Imported Cognito Form';
    }

    /**
     * Build the ordered list of field descriptors by scanning <c-section> and <c-field>
     * tags in the decoded HTML template and enriching them with labels / required flags
     * extracted from the model JS.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFieldList(string $tmpl, string $js): array
    {
        $elements = [];

        // Collect all <c-section> tags with their byte-offset in the template
        if (preg_match_all('/<c-section\s([^>]+)>/s', $tmpl, $sectionMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($sectionMatches[0] as $i => $match) {
                $attrStr = $sectionMatches[1][$i][0];
                if (preg_match("/source='([^']+)'/", $attrStr, $src) && $src[1] !== '') {
                    $elements[] = [
                        'kind'   => 'section',
                        'source' => $src[1],
                        'offset' => $match[1],
                    ];
                }
            }
        }

        // Collect all <c-field> tags with their byte-offset in the template
        if (preg_match_all('/<c-field\s([^>]+)>/s', $tmpl, $fieldMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($fieldMatches[0] as $i => $match) {
                $attrStr = $fieldMatches[1][$i][0];
                preg_match("/source='([^']+)'/", $attrStr, $src);
                preg_match("/\btype='([^']+)'/", $attrStr, $type);
                preg_match("/subtype='([^']+)'/", $attrStr, $subtype);
                $source = $src[1] ?? '';
                if ($source !== '') {
                    $elements[] = [
                        'kind'        => 'field',
                        'source'      => $source,
                        'cognito_type' => $type[1] ?? '',
                        'subtype'     => $subtype[1] ?? '',
                        'offset'      => $match[1],
                    ];
                }
            }
        }

        // Sort by position in the template so order matches the rendered form
        usort($elements, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        // Build field descriptors
        $fields = [];
        foreach ($elements as $el) {
            if ($el['kind'] === 'section') {
                $fields[] = [
                    'source'       => $el['source'],
                    'cognito_type' => 'section',
                    'subtype'      => '',
                    'label'        => $this->extractSectionLabel($el['source'], $js),
                    'required'     => false,
                ];
            } else {
                $fields[] = [
                    'source'       => $el['source'],
                    'cognito_type' => $el['cognito_type'],
                    'subtype'      => $el['subtype'],
                    'label'        => $this->extractFieldLabel($el['source'], $js),
                    'required'     => $this->extractFieldRequired($el['source'], $js),
                ];
            }
        }

        return $fields;
    }

    /**
     * Extract the human-readable label for a section from the model JS.
     * Looks for `sectionName: { ... label: "..." ... }`.
     */
    private function extractSectionLabel(string $sectionName, string $js): string
    {
        $block = $this->extractDefinitionBlock($sectionName, $js);

        if ($block !== null && preg_match('/\blabel\s*:\s*"([^"]+)"/', $block, $m)) {
            return $m[1];
        }

        return $sectionName;
    }

    /**
     * Extract the human-readable label for a field from the model JS.
     * Looks for `fieldName: { ... label: "..." ... }`.
     */
    private function extractFieldLabel(string $fieldName, string $js): string
    {
        $block = $this->extractDefinitionBlock($fieldName, $js);

        if ($block !== null && preg_match('/\blabel\s*:\s*"([^"]+)"/', $block, $m)) {
            return $m[1];
        }

        return $fieldName;
    }

    /**
     * Determine whether a field is required by checking for the `required:` key
     * in its model definition.
     */
    private function extractFieldRequired(string $fieldName, string $js): bool
    {
        $block = $this->extractDefinitionBlock($fieldName, $js);

        return $block !== null && (bool) preg_match('/\brequired\s*:/', $block);
    }

    /**
     * Extract the JS object body (including one level of nested braces) for a named key.
     *
     * Handles definitions like:
     *   fieldName: {
     *       label: "...",
     *       required: { message: "..." },   <- nested brace is handled
     *       type: '...'
     *   }
     *
     * Returns the full matched block (including outer braces), or null if not found.
     */
    private function extractDefinitionBlock(string $name, string $js): ?string
    {
        // Match: name: { <content supporting one level of nested {}>  }
        if (preg_match(
            '/\b' . preg_quote($name, '/') . '\s*:\s*(\{(?:[^{}]|\{[^{}]*\})*\})/s',
            $js,
            $m
        )) {
            return $m[1];
        }

        return null;
    }

    /**
     * Map a Cognito Forms field descriptor to a Form-Generator field definition.
     *
     * @param array{source: string, cognito_type: string, subtype: string, label: string, required: bool} $fieldData
     * @return array{skip: bool, reason: string, label: string, name: string, type: string, required: bool, options: array<int, string>}
     */
    private function mapField(array $fieldData): array
    {
        $cognitoType = strtolower($fieldData['cognito_type'] ?? '');
        $subtype = strtolower($fieldData['subtype'] ?? '');
        $label = $fieldData['label'] !== '' ? $fieldData['label'] : ($fieldData['source'] ?? 'Imported Field');
        $source = $fieldData['source'] ?? '';

        if ($cognitoType === 'section') {
            return [
                'skip'     => false,
                'reason'   => '',
                'label'    => $label,
                'name'     => $source,
                'type'     => 'label',
                'required' => false,
                'options'  => [],
            ];
        }

        $mappedType = $this->mapCognitoType($cognitoType, $subtype);
        $options = [];

        if ($mappedType === 'checkbox' && $cognitoType === 'yesno') {
            $options = FormField::normalizeOptions('checkbox', ['Yes']);
        }

        return [
            'skip'     => false,
            'reason'   => '',
            'label'    => $label,
            'name'     => $source,
            'type'     => $mappedType,
            'required' => $fieldData['required'],
            'options'  => $options,
        ];
    }

    /**
     * Map a Cognito Forms type + subtype pair to the corresponding Form-Generator field type.
     */
    private function mapCognitoType(string $cognitoType, string $subtype): string
    {
        // text with multiplelines subtype → textarea
        if ($cognitoType === 'text' && $subtype === 'multiplelines') {
            return 'textarea';
        }

        return match ($cognitoType) {
            'email'                => 'email',
            'number', 'currency'   => 'number',
            'yesno'                => 'checkbox',
            'address'              => 'textarea',
            'choice'               => $subtype === 'radio' ? 'radio' : 'dropdown',
            default                => 'text',   // name, phone, website, date, time, file, text/singleline, unknown
        };
    }

    /**
     * Extract the Cognito Forms internal form key and form number from the raw HTML of the public form page.
     * Tries the seamless.js script-tag attributes first, then falls back to other patterns.
     *
     * @return array{0: string, 1: string}|null [formKey, formNumber]
     */
    private function extractFormIdAndNumber(string $html): ?array
    {
        // Primary: seamless.js script tag – data-key before data-form
        if (preg_match('/<script[^>]+data-key="([A-Za-z0-9_-]+)"[^>]+data-form="(\d+)"/', $html, $m)
            && $this->isValidFormId($m[1])) {
            return [$m[1], $m[2]];
        }

        // Primary: seamless.js script tag – data-form before data-key
        if (preg_match('/<script[^>]+data-form="(\d+)"[^>]+data-key="([A-Za-z0-9_-]+)"/', $html, $m)
            && $this->isValidFormId($m[2])) {
            return [$m[2], $m[1]];
        }

        // Fallback: full internal endpoint URL embedded in the HTML/JS (includes form number)
        if (preg_match('/load-form\/form-def\/([A-Za-z0-9_-]+)\/(\d+)/', $html, $m)
            && $this->isValidFormId($m[1])) {
            return [$m[1], $m[2]];
        }

        // Fallback: partial form-def path (assume form number 1)
        if (preg_match('/form-def\/([A-Za-z0-9_-]+)/', $html, $m)
            && $this->isValidFormId($m[1])) {
            return [$m[1], '1'];
        }

        // Fallback: JSON-style formId property (assume form number 1)
        foreach (['"formId"\s*:\s*"([A-Za-z0-9_-]+)"', '"FormId"\s*:\s*"([A-Za-z0-9_-]+)"'] as $pattern) {
            if (preg_match("/{$pattern}/", $html, $m) && $this->isValidFormId($m[1])) {
                return [$m[1], '1'];
            }
        }

        return null;
    }

    /**
     * Validate that the extracted form ID conforms to Cognito Forms' expected format:
     * alphanumeric + hyphens/underscores, between FORM_ID_MIN_LENGTH and FORM_ID_MAX_LENGTH characters.
     */
    private function isValidFormId(string $id): bool
    {
        $len = strlen($id);

        return $len >= self::FORM_ID_MIN_LENGTH
            && $len <= self::FORM_ID_MAX_LENGTH
            && (bool) preg_match('/^[A-Za-z0-9_-]+$/', $id);
    }

    /**
     * @param array<string, bool> $usedNames
     */
    private function ensureUniqueFieldName(string $name, array &$usedNames): string
    {
        $base = $name !== '' ? $name : 'field';
        $candidate = $base;
        $suffix = 2;

        while (isset($usedNames[$candidate])) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        $usedNames[$candidate] = true;

        return $candidate;
    }

    private function sanitizeName(string $name): string
    {
        $value = strtolower(trim($name));
        $replaced = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = is_string($replaced) ? $replaced : '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'field';
    }
}
