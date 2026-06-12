<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormFileUploadService
{
    public function buildValidationRules(FormField $field): array
    {
        $extensions = $field->file_allowed_extensions;
        $mimeTypes = $field->file_allowed_mime_types;

        $rules = [
            $field->required ? 'required' : 'nullable',
            'file',
            'max:'.$field->file_max_size_kb,
        ];

        if ($extensions !== []) {
            $rules[] = 'mimes:'.implode(',', $extensions);
        }

        if ($mimeTypes !== []) {
            $rules[] = 'mimetypes:'.implode(',', $mimeTypes);
        }

        $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($field, $extensions): void {
            if (!$value instanceof UploadedFile || !$value->isValid()) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());

            if (in_array($extension, FormField::BLOCKED_FILE_EXTENSIONS, true)) {
                $fail('This file type is not allowed.');

                return;
            }

            if ($extensions !== [] && !in_array($extension, $extensions, true)) {
                $fail('This file type is not allowed.');

                return;
            }

            if ($this->hasBlockedExtensionInFilename($value->getClientOriginalName())) {
                $fail('This file type is not allowed.');
            }
        };

        return $rules;
    }

    public function storeUploadedFile(UploadedFile $file, Form $form, FormSubmission $submission, FormField $field): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, FormField::BLOCKED_FILE_EXTENSIONS, true)
            || !in_array($extension, $field->file_allowed_extensions, true)) {
            throw new \InvalidArgumentException('Disallowed file extension.');
        }

        $detectedMime = $file->getMimeType() ?: '';
        if ($detectedMime !== '' && !in_array($detectedMime, $field->file_allowed_mime_types, true)) {
            throw new \InvalidArgumentException('Disallowed file MIME type.');
        }

        $storedName = Str::uuid()->toString().'.'.$extension;
        $directory = $this->uploadDirectory($form->id, $submission->id);
        $path = $file->storeAs($directory, $storedName, 'local');

        if ($path === false) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        return [
            'path' => $path,
            'original_name' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
            'mime' => $detectedMime,
            'size' => $file->getSize(),
        ];
    }

    public function downloadSubmissionFile(Form $form, FormField $field, mixed $fileData): StreamedResponse
    {
        if (!is_array($fileData) || empty($fileData['path'])) {
            abort(404);
        }

        $path = (string) $fileData['path'];

        if (!$this->isPathWithinForm($path, $form->id)) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $downloadName = $this->sanitizeOriginalFilename((string) ($fileData['original_name'] ?? 'download'));

        return Storage::disk('local')->download($path, $downloadName);
    }

    public function deleteSubmissionFiles(FormSubmission $submission): void
    {
        $form = $submission->form;

        if ($form === null) {
            return;
        }

        foreach ($form->fields as $field) {
            if ($field->type !== 'file') {
                continue;
            }

            $fileData = $submission->data[$field->name] ?? null;
            $this->deleteStoredFile($form->id, $fileData);
        }

        Storage::disk('local')->deleteDirectory($this->uploadDirectory($form->id, $submission->id));
    }

    public function deleteFormUploads(Form $form): void
    {
        Storage::disk('local')->deleteDirectory('form-uploads/'.$form->id);
    }

    public function isFileUploadValue(mixed $value): bool
    {
        return is_array($value) && !empty($value['path']);
    }

    public function uploadDirectory(int $formId, int $submissionId): string
    {
        return 'form-uploads/'.$formId.'/'.$submissionId;
    }

    protected function deleteStoredFile(int $formId, mixed $fileData): void
    {
        if (!is_array($fileData) || empty($fileData['path'])) {
            return;
        }

        $path = (string) $fileData['path'];

        if (!$this->isPathWithinForm($path, $formId)) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    protected function isPathWithinForm(string $path, int $formId): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $expectedPrefix = 'form-uploads/'.$formId.'/';

        return str_starts_with($normalizedPath, $expectedPrefix)
            && !str_contains($normalizedPath, '..');
    }

    protected function hasBlockedExtensionInFilename(string $filename): bool
    {
        $basename = strtolower(basename($filename));
        $parts = explode('.', $basename);

        foreach ($parts as $part) {
            if (in_array($part, FormField::BLOCKED_FILE_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace("\0", '', $filename));
        $filename = trim($filename);

        return $filename === '' ? 'download' : mb_substr($filename, 0, 255);
    }
}
