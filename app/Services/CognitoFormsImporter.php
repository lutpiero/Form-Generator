<?php

namespace App\Services;

class CognitoFormsImporter
{
    /**
     * Import a Cognito Forms definition from a pasted JSON string.
     * Returns ['title' => string, 'fields' => array, 'sections' => int]
     */
    public function importFromJson(string $jsonString): array
    {
        $data = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)) {
            throw new \Exception('Invalid JSON. Please check your input.');
        }

        // Take the first top-level key (the URL)
        $formData = reset($data);

        if (!isset($formData['sections'])) {
            throw new \Exception('Invalid format: missing "sections" key.');
        }

        // Extract form title from the URL key or formEntry
        $urlKey    = array_key_first($data);
        $formTitle = basename($urlKey); // fallback: last segment of URL
        // Better: extract from formEntry last segment
        if (isset($formData['formEntry'])) {
            $parts     = explode('.', $formData['formEntry']);
            $formTitle = end($parts);
        }

        $fields       = [];
        $sectionCount = 0;

        foreach ($formData['sections'] as $sectionKey => $section) {
            // Add section heading as a label field
            $fields[] = [
                'type'       => 'label',
                'label'      => $section['label'],
                'required'   => false,
                'source'     => $sectionKey,
                'is_section' => true,
            ];
            $sectionCount++;

            foreach ($section['fields'] as $field) {
                $fields[] = [
                    'type'       => $this->mapFieldType($field['type'], $field['subtype'] ?? ''),
                    'label'      => $field['label'],
                    'required'   => (bool) ($field['required'] ?? false),
                    'source'     => $field['key'],
                    'is_section' => false,
                ];
            }
        }

        if (empty($fields)) {
            throw new \Exception('No fields found in the provided JSON.');
        }

        return [
            'title'    => $formTitle,
            'fields'   => $fields,
            'sections' => $sectionCount,
        ];
    }

    /**
     * Map Cognito field type+subtype to Form-Generator field type.
     */
    private function mapFieldType(string $type, string $subtype): string
    {
        return match (true) {
            $type === 'text'   && $subtype === 'multiplelines' => 'textarea',
            $type === 'text'                                   => 'text',
            $type === 'email'                                  => 'email',
            $type === 'phone'                                  => 'text',
            $type === 'name'                                   => 'text',
            $type === 'address'                                => 'textarea',
            $type === 'website'                                => 'text',
            $type === 'number'                                 => 'number',
            $type === 'date'                                   => 'date',
            $type === 'time'                                   => 'time',
            $type === 'yesno'                                  => 'checkbox',
            $type === 'choice'  && $subtype === 'dropdown'     => 'dropdown',
            $type === 'choice'                                 => 'radio',
            $type === 'file'                                   => 'file',
            default                                            => 'text',
        };
    }
}
