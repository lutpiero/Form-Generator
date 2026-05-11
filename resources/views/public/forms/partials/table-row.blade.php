@php
    $rowValues = is_array($rowValues ?? null) ? $rowValues : [];
    $baseName = "table_fields[{$field->id}][{$rowIndex}]";
    $dependentColumnKeys = $dependentColumnKeys ?? [];
    $dependentsByController = $dependentsByController ?? [];
@endphp

<tr data-table-row>
    @if($field->table_auto_number)
        <td class="text-muted text-center align-top" data-table-row-number>{{ is_numeric($rowIndex) ? $rowIndex + 1 : '' }}</td>
    @endif

    @foreach($columns as $column)
        @php
            $columnKey = $column['key'];

            // Dependent columns are rendered inline inside their controlling column's <td>.
            if (in_array($columnKey, $dependentColumnKeys, true)) {
                continue;
            }

            $errorKey = "table_fields.{$field->id}.{$rowIndex}.{$columnKey}";
            $otherInputKey = "{$columnKey}_other";
            $errorMessage = $errors->first($errorKey) ?: $errors->first($errorKey.'.0') ?: $errors->first("table_fields.{$field->id}.{$rowIndex}.{$otherInputKey}");
            $isInvalid = $errorMessage !== '';
            $columnValue = $rowValues[$columnKey] ?? null;
            $columnVisible = true; // Non-dependent columns are always visible
            $columnDisabledAttr = '';
        @endphp
        <td class="align-top"
            data-column-type="{{ $column['type'] }}"
            data-column-key="{{ $columnKey }}"
            data-required="{{ ($column['required'] ?? false) ? '1' : '0' }}"
            data-visibility-enabled="false">
            @include('public.forms.partials._table-column-input')

            <div class="invalid-feedback {{ $errorMessage ? 'd-block' : 'd-none' }}" data-table-error {{ $errorMessage ? 'data-server-error=1' : '' }}>
                {{ $errorMessage }}
            </div>

            {{-- Render dependent columns inline inside this controlling cell --}}
            @foreach($dependentsByController[$columnKey] ?? [] as $depColumn)
                @php
                    $depColumnKey = $depColumn['key'];
                    $depErrorKey = "table_fields.{$field->id}.{$rowIndex}.{$depColumnKey}";
                    $depOtherInputKey = "{$depColumnKey}_other";
                    $depErrorMessage = $errors->first($depErrorKey) ?: $errors->first($depErrorKey.'.0') ?: $errors->first("table_fields.{$field->id}.{$rowIndex}.{$depOtherInputKey}");
                    $depIsInvalid = $depErrorMessage !== '';
                    $depColumnValue = $rowValues[$depColumnKey] ?? null;
                    $depColumnVisible = \App\Models\FormField::isTableColumnVisible($depColumn, $rowValues);
                    $depColumnVisibility = \App\Models\FormField::normalizeColumnVisibilityRule($depColumn['visibility'] ?? null);
                    $depColumnDisabledAttr = $depColumnVisible ? '' : 'disabled';
                @endphp
                <div class="mt-2"
                     data-inline-dependent-cell
                     data-column-type="{{ $depColumn['type'] }}"
                     data-column-key="{{ $depColumnKey }}"
                     data-required="{{ ($depColumn['required'] ?? false) ? '1' : '0' }}"
                     data-visibility-enabled="true"
                     data-visibility-field="{{ $depColumnVisibility['field'] ?? '' }}"
                     data-visibility-operator="{{ $depColumnVisibility['operator'] ?? '' }}"
                     data-visibility-value="{{ $depColumnVisibility['value'] ?? '' }}"
                     data-visibility-state="{{ $depColumnVisible ? 'visible' : 'hidden' }}"
                     style="{{ $depColumnVisible ? '' : 'display:none;' }}">
                    <label class="form-label small fw-semibold mb-1">
                        {{ $depColumn['label'] }}
                        @if($depColumn['required'] ?? false) <span class="text-danger">*</span> @endif
                    </label>
                    @php
                        // Re-bind loop variables using dep-column names for the shared partial.
                        $column = $depColumn;
                        $columnKey = $depColumnKey;
                        $columnValue = $depColumnValue;
                        $isInvalid = $depIsInvalid;
                        $columnDisabledAttr = $depColumnDisabledAttr;
                        $columnVisible = $depColumnVisible;
                    @endphp
                    @include('public.forms.partials._table-column-input')
                    <div class="invalid-feedback {{ $depErrorMessage ? 'd-block' : 'd-none' }}" data-table-error {{ $depErrorMessage ? 'data-server-error=1' : '' }}>
                        {{ $depErrorMessage }}
                    </div>
                </div>
            @endforeach
        </td>
    @endforeach

    <td class="text-center align-top">
        <input type="hidden" name="{{ $baseName }}[__row]" value="1">
        <button type="button" class="btn btn-outline-danger btn-sm js-table-remove-row" title="Remove row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>

