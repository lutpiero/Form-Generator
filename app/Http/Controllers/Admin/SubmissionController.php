<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Form $form)
    {
        $submissions = $form->submissions()->latest()->paginate(20);
        return view('admin.submissions.index', compact('form', 'submissions'));
    }

    public function show(Form $form, FormSubmission $submission)
    {
        return view('admin.submissions.show', compact('form', 'submission'));
    }

    public function destroy(Form $form, FormSubmission $submission)
    {
        $submission->delete();
        return redirect()->route('admin.forms.submissions.index', $form)
            ->with('success', 'Submission deleted.');
    }

    public function export(Form $form)
    {
        $submissions = $form->submissions()->latest()->get();
        $fields = $form->fields;

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $form->slug . '-submissions.csv"',
        ];

        $callback = function () use ($submissions, $fields) {
            $file = fopen('php://output', 'w');

            // Header row
            $headerRow = ['Submission ID', 'Submitted At', 'IP Address'];
            foreach ($fields as $field) {
                $headerRow[] = $field->label;
            }
            fputcsv($file, $headerRow);

            // Data rows
            foreach ($submissions as $submission) {
                $row = [
                    $submission->id,
                    $submission->created_at->format('Y-m-d H:i:s'),
                    $submission->ip_address,
                ];
                foreach ($fields as $field) {
                    $value = $submission->data[$field->name] ?? '';
                    $value = $this->formatSubmissionValue($value);
                    $row[] = $value;
                }
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function formatSubmissionValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($item) => FormField::displaySubmissionValue($item),
                $value
            ));
        }

        return (string) $value;
    }
}
