@php
    $columns = $field->table_columns;
    $tableRows = old("table_fields.{$field->id}");
    if (!is_array($tableRows) || $tableRows === []) {
        $tableRows = [[]];
    }
@endphp

<div class="mb-4" data-repeatable-table data-field-id="{{ $field->id }}">
    <label class="form-label fw-semibold">{{ $field->label }}</label>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    @if($field->table_auto_number)
                        <th class="text-center" style="width: 70px;">#</th>
                    @endif
                    @foreach($columns as $column)
                        <th>
                            {{ $column['label'] }}
                            @if($column['required'] ?? false)
                                <span class="text-danger">*</span>
                            @endif
                        </th>
                    @endforeach
                    <th class="text-center" style="width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @foreach($tableRows as $rowIndex => $rowValues)
                    @include('public.forms.partials.table-row', [
                        'field' => $field,
                        'columns' => $columns,
                        'rowIndex' => $rowIndex,
                        'rowValues' => $rowValues,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>

    <template data-table-row-template>
        @include('public.forms.partials.table-row', [
            'field' => $field,
            'columns' => $columns,
            'rowIndex' => '__INDEX__',
            'rowValues' => [],
        ])
    </template>

    <div class="d-flex justify-content-between align-items-center gap-3">
        <button type="button" class="btn btn-outline-primary btn-sm js-table-add-row">
            <i class="bi bi-plus-circle"></i> Add Row
        </button>
        <div class="text-danger small d-none" data-table-summary-error>Please complete the required fields in each row.</div>
    </div>
</div>
