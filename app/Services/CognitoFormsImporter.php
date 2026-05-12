<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CognitoFormsImporter
{
    private const EXCLUDED_NODE_TYPES = ['form', 'page', 'layout', 'rule', 'validation', 'style'];

    /**
     * @return array{form: Form, imported: int, skipped: array<int, string>}
     */
    public function import(string $url): array
    {
        $response = Http::timeout(20)->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('Unable to fetch the Cognito Forms page. Please verify the URL is public and accessible.');
        }

        $schema = $this->extractSchema($response->body());
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
     * @return array<string, mixed>
     */
    private function extractSchema(string $html): array
    {
        $candidates = [];

        $anchors = [
            'window.__INITIAL_STATE__',
            'window.__FORM__',
            'window.__NEXT_DATA__',
            'cognitoforms.data',
            'formDefinition',
            'formModel',
        ];

        foreach ($anchors as $anchor) {
            $json = $this->extractJsonObjectAfterAnchor($html, $anchor);
            if ($json === null) {
                continue;
            }

            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $candidates[] = $decoded;
            }
        }

        if (preg_match_all('/<script[^>]*type=["\']application\/json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $scriptJson) {
                $decoded = json_decode(trim($scriptJson), true);
                if (is_array($decoded)) {
                    $candidates[] = $decoded;
                }
            }
        }

        $bestCandidate = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $score = count($this->extractFieldNodes($candidate));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        if (!is_array($bestCandidate) || $bestScore <= 0) {
            throw new RuntimeException('Could not extract a Cognito form schema from the provided URL.');
        }

        return $bestCandidate;
    }

    private function extractJsonObjectAfterAnchor(string $content, string $anchor): ?string
    {
        $anchorPos = strpos($content, $anchor);

        if ($anchorPos === false) {
            return null;
        }

        $start = strpos($content, '{', $anchorPos);
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);

        for ($i = $start; $i < $length; $i++) {
            $char = $content[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, ($i - $start) + 1);
                }
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
                    strtolower($this->stringValue($value, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType'])),
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
        $type = strtolower($this->stringValue($node, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType']));

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
        $name = $this->stringValue($schema, [
            'name',
            'Name',
            'title',
            'Title',
            'formName',
            'FormName',
            'formTitle',
            'FormTitle',
        ]);

        return $name !== '' ? Str::limit($name, 255, '') : 'Imported Cognito Form';
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function extractDescription(array $schema): ?string
    {
        $description = $this->stringValue($schema, ['description', 'Description', 'instructions', 'Instructions']);

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

        $rawType = strtolower($this->stringValue($node, ['type', 'Type', 'fieldType', 'FieldType', 'controlType', 'ControlType']));
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
