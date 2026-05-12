<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CognitoFormsImporter
{
    /**
     * Import a Cognito Forms public form by URL.
     * Returns ['title' => string, 'fields' => array]
     */
    public function import(string $url): array
    {
        // Validate URL
        if (!preg_match('~^https://www\.cognitoforms\.com/([^/?#]+)/([^/?#]+)~i', $url)) {
            throw new \Exception('Invalid Cognito Forms URL. Expected: https://www.cognitoforms.com/OrgName/FormName');
        }

        // ── Step 1: Fetch page HTML ──────────────────────────────────────────
        $html = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->timeout(15)->get($url)->body();

        // ── Step 2: Extract data-key and data-form from <script> tag ─────────
        // <script data-form="1" data-key="ByIWZqWyQEKFtmYjduHCqQ" ... src="/f/seamless.js...">
        $dataKey  = null;
        $dataForm = null;

        // Try: data-key before data-form
        if (preg_match('/<script[^>]+data-key="([A-Za-z0-9_=-]+)"[^>]+data-form="(\d+)"/s', $html, $m)) {
            $dataKey  = $m[1];
            $dataForm = $m[2];
        }
        // Try: data-form before data-key
        elseif (preg_match('/<script[^>]+data-form="(\d+)"[^>]+data-key="([A-Za-z0-9_=-]+)"/s', $html, $m)) {
            $dataForm = $m[1];
            $dataKey  = $m[2];
        }

        if (!$dataKey || !$dataForm) {
            throw new \Exception('Could not find the Cognito Forms script tag (data-key / data-form). Make sure the form is public.');
        }

        // ── Step 3: Fetch the JS form definition ─────────────────────────────
        $jsUrl = "https://www.cognitoforms.com/svc/load-form/form-def/{$dataKey}/{$dataForm}";
        $js = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'     => '*/*',
            'Referer'    => $url,
        ])->timeout(15)->get($jsUrl)->body();

        if (empty(trim($js))) {
            throw new \Exception('Form definition endpoint returned an empty response.');
        }

        // ── Step 4: Extract the template string (3rd IIFE argument) ──────────
        // In the JS: "dataForm", "\u003cform....\u003c/form\u003e",
        // Regex: after "dataForm", capture the next double-quoted JS string
        $quotedFormId = preg_quote('"' . $dataForm . '"', '/');
        if (!preg_match('/' . $quotedFormId . '\s*,\s*"((?:[^"\\\\]|\\\\.)*)"/s', $js, $tmplMatch)) {
            Log::error('CognitoFormsImporter: template extraction failed', ['url' => $url, 'js_snippet' => substr($js, 0, 500)]);
            throw new \Exception('Could not extract the form template. The Cognito Forms response format may have changed.');
        }

        $tmplRaw = $tmplMatch[1];

        // Decode \uXXXX unicode escapes
        $tmpl = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $tmplRaw);

        // Decode common escape sequences
        $tmpl = str_replace(['\\r', '\\n', '\\t', '\\/'], ["\r", "\n", "\t", '/'], $tmpl);

        // ── Step 5: Parse <c-section> and <c-field> tags from template ────────
        // After decoding, attributes use single quotes: source='FieldName' type='text'

        // Find all c-section opening tags with their byte offset
        preg_match_all("/<c-section\s[^>]*>/s", $tmpl, $secTagMatches, PREG_OFFSET_CAPTURE);

        // Find all c-field opening tags with their byte offset
        preg_match_all("/<c-field\s[^>]*>/s", $tmpl, $fieldTagMatches, PREG_OFFSET_CAPTURE);

        // Build a list of positioned events (sections + fields), sorted by position
        $events = [];

        foreach ($secTagMatches[0] as $match) {
            $tag = $match[0];
            $pos = $match[1];
            $source = '';
            if (preg_match("/source='([^']+)'/", $tag, $sm)) {
                $source = $sm[1];
            }
            if ($source) {
                $events[] = ['kind' => 'section', 'pos' => $pos, 'source' => $source];
            }
        }

        foreach ($fieldTagMatches[0] as $match) {
            $tag = $match[0];
            $pos = $match[1];
            $source  = '';
            $type    = '';
            $subtype = '';
            if (preg_match("/\bsource='([^']+)'/", $tag, $fm))  $source  = $fm[1];
            if (preg_match("/\btype='([^']+)'/",   $tag, $fm))  $type    = $fm[1];
            if (preg_match("/\bsubtype='([^']+)'/", $tag, $fm)) $subtype = $fm[1];
            if ($source && $type) {
                $events[] = ['kind' => 'field', 'pos' => $pos, 'source' => $source, 'cogType' => $type, 'subtype' => $subtype];
            }
        }

        usort($events, fn($a, $b) => $a['pos'] <=> $b['pos']);

        // ── Step 6: Extract field labels and required flags from model JS ─────
        // Pattern in the JS:  FieldName: {\n    label: "Field Label",\n    required: true/{ ... }
        // We use a block-finding approach: find each field name followed by { ... }

        $fieldMeta = [];

        // Extract label for each named property block: PropName: { ... label: "..." ... }
        // We match blocks that are NOT nested (no inner {}) to keep it simple
        preg_match_all('/\b([A-Za-z][A-Za-z0-9_]*)\s*:\s*\{([^{}]*)\}/s', $js, $blockMatches);

        foreach ($blockMatches[1] as $i => $propName) {
            $block = $blockMatches[2][$i];

            // Skip known system/non-field properties
            if (in_array($propName, [
                'Entry', 'Form', 'Id', 'Confirmation', 'NarahubungPengisiForm',
                'INFORMASIDATAUMUM', 'INFORMASIDATASPESIFIK', 'Card', 'DeliveryStatus',
                'ReplyTo', 'Sender', 'Order', 'Origin', 'User', 'CustomerCard',
                'PaymentToken', 'TargetFormInfo',
            ])) {
                continue;
            }

            if (preg_match('/\blabel\s*:\s*"([^"]+)"/', $block, $lm)) {
                $label    = $lm[1];
                $required = (bool) preg_match('/\brequired\s*:/', $block);
                // Only add if not already found (first occurrence wins — most specific section type)
                if (!isset($fieldMeta[$propName])) {
                    $fieldMeta[$propName] = ['label' => $label, 'required' => $required];
                }
            }
        }

        // ── Step 7: Extract form title ────────────────────────────────────────
        $formTitle = 'Imported Form';
        // From: "Name":"FORMULIR KATALOG AOE 2025 (PESERTA NON PEMKAB)"
        if (preg_match('/"Name"\s*:\s*"([^"]+)"/', $js, $titleMatch)) {
            $formTitle = $titleMatch[1];
        }

        // ── Step 8: Build ordered result ─────────────────────────────────────
        $fields = [];

        foreach ($events as $event) {
            if ($event['kind'] === 'section') {
                $secName  = $event['source'];
                $secLabel = $fieldMeta[$secName]['label'] ?? $secName;
                $fields[] = [
                    'type'       => 'label',
                    'label'      => $secLabel,
                    'required'   => false,
                    'source'     => $secName,
                    'is_section' => true,
                ];
            } else {
                $source = $event['source'];
                $meta   = $fieldMeta[$source] ?? ['label' => $source, 'required' => false];
                $fields[] = [
                    'type'       => $this->mapFieldType($event['cogType'], $event['subtype']),
                    'label'      => $meta['label'],
                    'required'   => $meta['required'],
                    'source'     => $source,
                    'is_section' => false,
                ];
            }
        }

        if (empty($fields)) {
            throw new \Exception('No fields could be extracted from the form definition. The form may be empty or the format has changed.');
        }

        return [
            'title'  => $formTitle,
            'fields' => $fields,
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
            $type === 'choice'  && $subtype === 'dropdown'     => 'select',
            $type === 'choice'                                 => 'radio',
            $type === 'file'                                   => 'file',
            default                                            => 'text',
        };
    }
}
