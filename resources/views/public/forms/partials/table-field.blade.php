@php
    $columns = $field->table_columns;
    $tableRows = old("table_fields.{$field->id}");
    if (!is_array($tableRows) || $tableRows === []) {
        $tableRows = [[]];
    }

    // Build maps for conditional (dependent) columns so they render inline inside their controlling column.
    $dependentColumnKeys = [];
    $dependentsByController = [];
    foreach ($columns as $col) {
        $vis = \App\Models\FormField::normalizeColumnVisibilityRule($col['visibility'] ?? null);
        if ($vis) {
            $dependentColumnKeys[] = $col['key'];
            $dependentsByController[$vis['field']][] = $col;
        }
    }

    $maxRows = $field->table_max_rows;
    $atLimit = $maxRows > 0 && count($tableRows) >= $maxRows;
@endphp

<div class="mb-4" data-repeatable-table data-field-id="{{ $field->id }}"@if($maxRows > 0) data-max-rows="{{ $maxRows }}"@endif>
    <label class="form-label fw-semibold">{{ $field->label }}</label>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    @if($field->table_auto_number)
                        <th class="text-center align-top" style="width: 70px;">#</th>
                    @endif
                    @foreach($columns as $column)
                        @if(!in_array($column['key'], $dependentColumnKeys, true))
                            <th class="align-top">
                                {{ $column['label'] }}
                                @if($column['required'] ?? false)
                                    <span class="text-danger">*</span>
                                @endif
                            </th>
                        @endif
                    @endforeach
                    <th class="text-center align-top" style="width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody data-table-body>
                @foreach($tableRows as $rowIndex => $rowValues)
                    @include('public.forms.partials.table-row', [
                        'field' => $field,
                        'columns' => $columns,
                        'rowIndex' => $rowIndex,
                        'rowValues' => $rowValues,
                        'dependentColumnKeys' => $dependentColumnKeys,
                        'dependentsByController' => $dependentsByController,
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
            'dependentColumnKeys' => $dependentColumnKeys,
            'dependentsByController' => $dependentsByController,
        ])
    </template>

    <div class="d-flex justify-content-between align-items-center gap-3">
        <div>
            <button type="button" class="btn btn-outline-primary btn-sm js-table-add-row{{ $atLimit ? ' d-none' : '' }}"{{ $atLimit ? ' disabled' : '' }}>
                <i class="bi bi-plus-circle"></i> Add Row
            </button>
            @if($maxRows > 0)
                <small class="text-muted js-table-max-rows-msg{{ $atLimit ? '' : ' d-none' }}">Maximum of {{ $maxRows }} row(s) allowed.</small>
            @endif
        </div>
        <div class="text-danger small d-none" data-table-summary-error>Please complete the required fields in each row.</div>
    </div>
</div>
