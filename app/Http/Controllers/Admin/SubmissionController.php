<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Services\FormFileUploadService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SubmissionController extends Controller
{
    public function __construct(
        protected FormFileUploadService $fileUploadService
    ) {}

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
        $this->fileUploadService->deleteSubmissionFiles($submission);
        $submission->delete();

        return redirect()->route('admin.forms.submissions.index', $form)
            ->with('success', 'Submission deleted.');
    }

    public function downloadFile(Form $form, FormSubmission $submission, FormField $field)
    {
        abort_unless($submission->form_id === $form->id, 404);
        abort_unless($field->form_id === $form->id && $field->type === 'file', 404);

        $fileData = $submission->data[$field->name] ?? null;

        return $this->fileUploadService->downloadSubmissionFile($form, $field, $fileData);
    }

    public function export(Form $form)
    {
        $submissions = $form->submissions()->latest()->get();
        $fields = $form->fields->filter(fn ($f) => !in_array($f->type, ['section', 'label'], true));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$form->slug.'-submissions.csv"',
        ];

        $callback = function () use ($submissions, $fields) {
            $file = fopen('php://output', 'w');

            $headerRow = ['Submission ID', 'Submitted At', 'IP Address'];
            foreach ($fields as $field) {
                $headerRow[] = $field->label;
            }
            fputcsv($file, $headerRow);

            foreach ($submissions as $submission) {
                $row = [
                    $submission->id,
                    $submission->created_at->format('Y-m-d H:i:s'),
                    $submission->ip_address,
                ];

                foreach ($fields as $field) {
                    $value = $submission->data[$field->name] ?? '';
                    $value = $field->formatSubmissionValue($value);
                    $row[] = $value;
                }

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Form $form)
    {
        $submissions = $form->submissions()->latest()->get();
        $fields = $form->fields->filter(fn ($f) => !in_array($f->type, ['section', 'label'], true))->values();
        $tableFields = $fields->filter(fn ($field) => $field->type === 'table')->values();
        $flatFields = $fields->filter(fn ($field) => $field->type !== 'table')->values();

        $spreadsheet = new Spreadsheet;
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Submissions');

        $headerRow = ['#', 'Submitted At'];
        foreach ($flatFields as $field) {
            $headerRow[] = $field->label;
        }
        foreach ($tableFields as $field) {
            $headerRow[] = $field->label;
        }

        $summarySheet->fromArray($headerRow, null, 'A1');
        $summarySheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headerRow)).'1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE9ECEF'],
            ],
        ]);

        $usedSheetNames = ['Submissions' => true];

        foreach ($submissions as $submissionIndex => $submission) {
            $rowNumber = $submissionIndex + 2;
            $columnIndex = 1;

            $summarySheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$rowNumber, $submissionIndex + 1);
            $summarySheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$rowNumber, $submission->created_at?->format('Y-m-d H:i:s') ?? '');

            foreach ($flatFields as $field) {
                $value = $submission->data[$field->name] ?? '';
                $summarySheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex++).$rowNumber,
                    $value === null ? '' : $field->formatSubmissionValue($value)
                );
            }

            foreach ($tableFields as $field) {
                $sheetName = $this->buildTableSheetName($submissionIndex + 1, $field->name, $usedSheetNames);
                $this->populateTableSheet(
                    $spreadsheet,
                    $sheetName,
                    $field,
                    $this->normalizeTableRows($submission->data[$field->name] ?? null)
                );

                $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;
                $summarySheet->setCellValue($cellCoordinate, '→ View Details');
                $summarySheet->getCell($cellCoordinate)->getHyperlink()->setUrl("sheet://'{$sheetName}'!A1");
                $columnIndex++;
            }
        }

        foreach (range(1, count($headerRow)) as $index) {
            $summarySheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'submissions_');
        $xlsxPath = $tempPath.'.xlsx';
        @unlink($tempPath);
        $writer->save($xlsxPath);

        return response()->download(
            $xlsxPath,
            sprintf('%s-submissions-%s.xlsx', $form->slug, now()->format('Y-m-d'))
        )->deleteFileAfterSend(true);
    }

    private function normalizeTableRows(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function buildTableSheetName(int $submissionIndex, string $fieldName, array &$usedNames): string
    {
        $prefix = "R{$submissionIndex}_";
        $maxFieldLength = max(1, 31 - strlen($prefix));
        $base = $prefix.substr($fieldName, 0, $maxFieldLength);
        $candidate = $base;
        $suffix = 1;

        while (isset($usedNames[$candidate])) {
            $suffixText = '_'.$suffix++;
            $candidate = substr($base, 0, 31 - strlen($suffixText)).$suffixText;
        }

        $usedNames[$candidate] = true;

        return $candidate;
    }

    private function populateTableSheet(Spreadsheet $spreadsheet, string $sheetName, FormField $field, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);

        $headerColumns = [];
        if ($field->table_auto_number) {
            $headerColumns[] = '#';
        }
        foreach ($field->table_columns as $column) {
            if (($column['type'] ?? null) === 'label') {
                continue;
            }

            $headerColumns[] = $column['label'] ?? '';
        }

        if ($headerColumns !== []) {
            $sheet->fromArray($headerColumns, null, 'A1');
            $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headerColumns)).'1')
                ->getFont()
                ->setBold(true);
        }

        foreach (array_values($rows) as $rowIndex => $rowData) {
            $currentRow = $rowIndex + 2;
            $columnIndex = 1;

            if ($field->table_auto_number) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$currentRow, $rowIndex + 1);
            }

            foreach ($field->table_columns as $column) {
                if (($column['type'] ?? null) === 'label') {
                    continue;
                }

                $columnValue = is_array($rowData) ? ($rowData[$column['key']] ?? null) : null;

                if (is_array($columnValue)) {
                    $formattedValue = collect($columnValue)
                        ->map(fn ($item) => FormField::displaySubmissionValue($item, $column['other_label'] ?? null))
                        ->implode(', ');
                } else {
                    $formattedValue = $columnValue === null ? '' : FormField::displaySubmissionValue($columnValue, $column['other_label'] ?? null);
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$currentRow, $formattedValue);
            }
        }

        foreach (range(1, max(count($headerColumns), 1)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
