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
    private const EXCLUDED_NODE_TYPES = ['form', 'page', 'layout', 'rule', 'validation', 'style'];

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
        $sourceFields = $this->extractFieldNodes($schema);

        if ($sourceFields === []) {
            throw new RuntimeException('No form fields were found in the Cognito Forms page schema.');
        }

        return DB::transaction(function () use ($schema, $sourceFields) {
            $form = Form::create([
                'name' => $this->extractFormName($schema),
                'description' => $this->extractDescription($schema),
                'is_active' => true,
                'captcha_enabled' => false,
                'captcha_type' => 'math',
            ]);

            $usedNames = [];
            $imported = 0;
            $skipped = [];

            foreach ($sourceFields as $node) {
                $mappedField = $this->mapField($node);

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
                    'placeholder' => $mappedField['placeholder'] !== '' ? Str::limit($mappedField['placeholder'], 255, '') : null,
                    'default_value' => $mappedField['default'] !== '' ? Str::limit($mappedField['default'], 255, '') : null,
                    'options' => $mappedField['options'] === [] ? null : json_encode($mappedField['options']),
                    'config' => $mappedField['config'] === [] ? null : $mappedField['config'],
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
     * Fetch the form schema from a public Cognito Forms URL using a two-step approach:
     *   Step 1 – GET the HTML shell and extract the internal form ID from it.
     *   Step 2 – Call the internal form-definition endpoint with the extracted ID.
     *
     * @return array<string, mixed>
     */
    private function fetchSchema(string $url): array
    {
        // Step 1 — Fetch HTML shell and extract the internal form ID
        $htmlResponse = Http::timeout(20)
            ->withHeaders(array_merge(self::BROWSER_HEADERS, [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]))
            ->get($url);

        if (!$htmlResponse->successful()) {
            throw new RuntimeException('Unable to fetch the Cognito Forms page. Please verify the URL is public and accessible.');
        }

        $formId = $this->extractFormId($htmlResponse->body());

        if ($formId === null) {
            throw new RuntimeException(
                'Could not extract a Cognito form schema from the provided URL. '
                . 'Please ensure the form is public and the URL is correct. '
                . 'Example format: https://www.cognitoforms.com/YourOrg/YourFormName'
            );
        }

        Log::debug('CognitoFormsImporter: extracted form ID', ['formId' => $formId, 'url' => $url]);

        // Step 2 — Fetch the form definition from the internal API endpoint
        $formDefResponse = Http::timeout(20)
            ->withHeaders(array_merge(self::BROWSER_HEADERS, [
                'Accept' => 'application/json',
                'Referer' => $url,
            ]))
            ->get("https://www.cognitoforms.com/svc/load-form/form-def/{$formId}/1");

        if (!$formDefResponse->successful()) {
            throw new RuntimeException(
                'Could not load the Cognito Forms schema. The form definition API returned an unexpected response.'
            );
        }

        $schema = $formDefResponse->json();

        Log::debug('CognitoFormsImporter: received form definition', ['formId' => $formId, 'keys' => is_array($schema) ? array_keys($schema) : null]);

        if (!is_array($schema) || $schema === []) {
            throw new RuntimeException(
                'Could not load the Cognito Forms schema. The API returned an empty or malformed response.'
            );
        }

        return $schema;
    }

    /**
     * Extract the Cognito Forms internal form ID from the raw HTML of the public form page.
     * Tries several regex patterns in order of specificity.
     */
    private function extractFormId(string $html): ?string
    {
        $patterns = [
            // Full internal endpoint URL embedded in the HTML/JS
            '/load-form\/form-def\/([A-Za-z0-9_-]+)\/\d+/',
            // Partial form-def path
            '/form-def\/([A-Za-z0-9_-]+)/',
            // JSON property "formId"
            '/"formId"\s*:\s*"([A-Za-z0-9_-]+)"/',
            // JSON property "FormId" (PascalCase)
            '/"FormId"\s*:\s*"([A-Za-z0-9_-]+)"/',
            // Any long base64-like ID value (≥ 20 chars)
            '/"id"\s*:\s*"([A-Za-z0-9_-]{20,})"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, array<string, mixed>>
     */
    private function extractFieldNodes(array $schema): array
    {
        $nodes = [];
        $seen = [];

        $walk = function (mixed $value) use (&$walk, &$nodes, &$seen): void {
            if (!is_array($value)) {
                return;
            }

            if ($this->looksLikeFieldNode($value)) {
                $fingerprint = md5(json_encode([
                    strtolower($this->stringValue($value, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType', '_type'])),
                    strtolower($this->stringValue($value, ['name', 'Name'])),
                    strtolower($this->stringValue($value, ['label', 'Label', 'title', 'Title', 'text', 'Text'])),
                ]));

                if (!isset($seen[$fingerprint])) {
                    $seen[$fingerprint] = true;
                    $nodes[] = $value;
                }
            }

            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        $walk($schema);

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function looksLikeFieldNode(array $node): bool
    {
        $type = strtolower($this->stringValue($node, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType', '_type']));

        if ($type === '') {
            return false;
        }

        if (in_array($type, self::EXCLUDED_NODE_TYPES, true)) {
            return false;
        }

        $label = $this->stringValue($node, ['label', 'Label', 'title', 'Title', 'text', 'Text', 'name', 'Name']);

        return $label !== '' || array_key_exists('options', $node) || array_key_exists('choices', $node);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function extractFormName(array $schema): string
    {
        $nameKeys = [
            'name', 'Name', 'title', 'Title', 'formName', 'FormName', 'formTitle', 'FormTitle',
        ];

        $name = $this->stringValue($schema, $nameKeys);

        // Also check inside a nested 'Form' / 'form' object (Cognito's svc API wraps data this way)
        if ($name === '') {
            foreach (['Form', 'form'] as $key) {
                if (isset($schema[$key]) && is_array($schema[$key])) {
                    $name = $this->stringValue($schema[$key], $nameKeys);
                    if ($name !== '') {
                        break;
                    }
                }
            }
        }

        return $name !== '' ? Str::limit($name, 255, '') : 'Imported Cognito Form';
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function extractDescription(array $schema): ?string
    {
        $descKeys = ['description', 'Description', 'instructions', 'Instructions'];

        $description = $this->stringValue($schema, $descKeys);

        // Also check inside a nested 'Form' / 'form' object
        if ($description === '') {
            foreach (['Form', 'form'] as $key) {
                if (isset($schema[$key]) && is_array($schema[$key])) {
                    $description = $this->stringValue($schema[$key], $descKeys);
                    if ($description !== '') {
                        break;
                    }
                }
            }
        }

        if ($description === '') {
            return null;
        }

        return Str::limit($description, 65000, '');
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *   skip: bool,
     *   reason: string,
     *   label: string,
     *   name: string,
     *   type: string,
     *   required: bool,
     *   placeholder: string,
     *   default: string,
     *   options: array<int, string>,
     *   config: array<string, mixed>
     * }
     */
    private function mapField(array $node): array
    {
        $label = trim($this->stringValue($node, ['label', 'Label', 'title', 'Title', 'text', 'Text', 'name', 'Name']));
        $name = trim($this->stringValue($node, ['name', 'Name', 'key', 'Key']));

        if ($label === '') {
            $label = $name !== '' ? $name : 'Imported Field';
        }

        if ($name === '') {
            $name = $label;
        }

        $rawType = strtolower($this->stringValue($node, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType', '_type']));
        $placeholder = $this->stringValue($node, ['placeholder', 'Placeholder', 'prompt', 'Prompt']);
        $defaultValue = $this->stringValue($node, ['default', 'Default', 'defaultValue', 'DefaultValue']);
        $required = $this->boolValue($node, ['required', 'Required', 'isRequired', 'IsRequired']);

        $mappedType = 'text';
        $skip = false;
        $reason = '';
        $options = [];

        if (str_contains($rawType, 'section')) {
            $mappedType = 'section';
        } elseif (str_contains($rawType, 'signature')) {
            $skip = true;
            $reason = 'Signature fields are not supported';
        } elseif (str_contains($rawType, 'table') || str_contains($rawType, 'repeat')) {
            $skip = true;
            $reason = 'Repeating/table fields are not supported';
        } elseif (str_contains($rawType, 'email')) {
            $mappedType = 'email';
        } elseif (str_contains($rawType, 'phone')) {
            $mappedType = 'phone';
        } elseif (str_contains($rawType, 'number') || str_contains($rawType, 'currency')) {
            $mappedType = 'number';
        } elseif (str_contains($rawType, 'name')) {
            $mappedType = 'text';
        } elseif (str_contains($rawType, 'address')) {
            $mappedType = 'textarea';
        } elseif (str_contains($rawType, 'fileupload') || str_contains($rawType, 'file')) {
            $mappedType = 'text';
        } elseif (str_contains($rawType, 'date') || str_contains($rawType, 'time')) {
            $mappedType = 'text';
        } elseif (str_contains($rawType, 'yesno') || ($rawType === 'checkbox' && $this->extractOptions($node) === [])) {
            $mappedType = 'checkbox';
            $options = ['Yes'];
        } elseif (str_contains($rawType, 'checkbox')) {
            $mappedType = 'checkbox';
            $options = $this->extractOptions($node);
        } elseif (str_contains($rawType, 'choice')) {
            $presentation = strtolower($this->stringValue($node, ['presentation', 'Presentation', 'displayType', 'DisplayType', 'style', 'Style']));
            $allowMultiple = $this->boolValue($node, ['allowMultiple', 'AllowMultiple', 'multiple', 'Multiple']);

            if ($allowMultiple) {
                $mappedType = 'checkbox';
            } elseif (str_contains($presentation, 'radio')) {
                $mappedType = 'radio';
            } else {
                $mappedType = 'dropdown';
            }

            $options = $this->extractOptions($node);
        } elseif (str_contains($rawType, 'radio')) {
            $mappedType = 'radio';
            $options = $this->extractOptions($node);
        } elseif (str_contains($rawType, 'dropdown') || str_contains($rawType, 'select')) {
            $mappedType = 'dropdown';
            $options = $this->extractOptions($node);
        } elseif (str_contains($rawType, 'paragraph') || $this->boolValue($node, ['multiline', 'MultiLine', 'isMultiline', 'IsMultiline'])) {
            $mappedType = 'textarea';
        } elseif (str_contains($rawType, 'text')) {
            $mappedType = 'text';
        }

        $config = [];
        $description = $this->stringValue($node, ['description', 'Description', 'instructions', 'Instructions', 'helpText', 'HelpText']);
        if ($description !== '') {
            $config['description'] = Str::limit($description, 1000, '');
        }

        if (in_array($mappedType, FormField::OPTION_BASED_TYPES, true)) {
            $options = FormField::normalizeOptions($mappedType, $options);
        } else {
            $options = [];
        }

        return [
            'skip' => $skip,
            'reason' => $reason,
            'label' => $label,
            'name' => $name,
            'type' => $mappedType,
            'required' => $required,
            'placeholder' => trim($placeholder),
            'default' => trim($defaultValue),
            'options' => $options,
            'config' => $config,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @return array<int, string>
     */
    private function extractOptions(array $node): array
    {
        $rawOptions = null;

        foreach (['options', 'Options', 'choices', 'Choices', 'items', 'Items'] as $key) {
            if (array_key_exists($key, $node) && is_array($node[$key])) {
                $rawOptions = $node[$key];
                break;
            }
        }

        if (!is_array($rawOptions)) {
            return [];
        }

        $options = [];
        foreach ($rawOptions as $option) {
            if (is_string($option) || is_numeric($option)) {
                $options[] = trim((string) $option);
                continue;
            }

            if (!is_array($option)) {
                continue;
            }

            $value = $this->stringValue($option, ['label', 'Label', 'text', 'Text', 'name', 'Name', 'value', 'Value']);
            if ($value !== '') {
                $options[] = trim($value);
            }
        }

        return array_values(array_filter(array_unique($options), fn ($value) => $value !== ''));
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $keys
     */
    private function stringValue(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $keys
     */
    private function boolValue(array $values, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (bool) $value;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    return true;
                }
                if (in_array($normalized, ['0', 'false', 'no'], true)) {
                    return false;
                }
            }
        }

        return false;
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
