@php
    $rowValues = is_array($rowValues ?? null) ? $rowValues : [];
    $baseName = "table_fields[{$field->id}][{$rowIndex}]";
@endphp

<tr data-table-row>
    @if($field->table_auto_number)
        <td class="text-muted text-center align-top" data-table-row-number>{{ is_numeric($rowIndex) ? $rowIndex + 1 : '' }}</td>
    @endif

    @foreach($columns as $column)
        @php
            $columnKey = $column['key'];
            $errorKey = "table_fields.{$field->id}.{$rowIndex}.{$columnKey}";
            $otherInputKey = "{$columnKey}_other";
            $errorMessage = $errors->first($errorKey) ?: $errors->first($errorKey.'.0') ?: $errors->first("table_fields.{$field->id}.{$rowIndex}.{$otherInputKey}");
            $isInvalid = $errorMessage !== '';
            $columnValue = $rowValues[$columnKey] ?? null;
            $columnVisible = \App\Models\FormField::isTableColumnVisible($column, $rowValues);
            $columnVisibility = \App\Models\FormField::normalizeColumnVisibilityRule($column['visibility'] ?? null);
            $columnDisabledAttr = $columnVisible ? '' : 'disabled';
        @endphp
        <td class="align-top"
            data-column-type="{{ $column['type'] }}"
            data-column-key="{{ $columnKey }}"
            data-required="{{ ($column['required'] ?? false) ? '1' : '0' }}"
            data-visibility-enabled="{{ $columnVisibility ? 'true' : 'false' }}"
            data-visibility-field="{{ $columnVisibility['field'] ?? '' }}"
            data-visibility-operator="{{ $columnVisibility['operator'] ?? '' }}"
            data-visibility-value="{{ $columnVisibility['value'] ?? '' }}"
            data-visibility-state="{{ $columnVisible ? 'visible' : 'hidden' }}"
            style="{{ $columnVisible ? '' : 'display:none;' }}">
            @switch($column['type'])
                @case('textarea')
                    <textarea
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                        rows="2"
                        {{ $columnDisabledAttr }}
                    >{{ is_string($columnValue) ? $columnValue : '' }}</textarea>
                    @break

                @case('dropdown')
                    <select
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        class="form-select form-select-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                        {{ $columnDisabledAttr }}
                    >
                        <option value="">Select</option>
                        @foreach($column['options'] ?? [] as $option)
                            <option value="{{ $option }}" {{ $columnValue === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                    @break

                @case('radio')
                    <div class="d-flex flex-column gap-1">
                        @foreach($column['options'] ?? [] as $optionIndex => $option)
                            @php $radioInputId = "{$field->id}_{$rowIndex}_{$columnKey}_{$optionIndex}"; @endphp
                            <div class="form-check">
                                <input
                                    class="form-check-input {{ $isInvalid ? 'is-invalid' : '' }}"
                                    type="radio"
                                    name="{{ $baseName }}[{{ $columnKey }}]"
                                    value="{{ $option }}"
                                    id="{{ $radioInputId }}"
                                    {{ $columnValue === $option ? 'checked' : '' }}
                                    {{ $columnDisabledAttr }}
                                >
                                <label class="form-check-label small" for="{{ $radioInputId }}">{{ $option }}</label>
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('checkbox')
                    @php
                        $checkboxValues = collect((array) $columnValue);
                        $otherValue = $rowValues[$otherInputKey] ?? null;

                        if (!is_string($otherValue) || trim($otherValue) === '') {
                            $storedOtherValue = $checkboxValues->first(fn ($value) => \App\Models\FormField::isOtherResponse($value));
                            $otherValue = \App\Models\FormField::extractOtherResponse($storedOtherValue);
                        }

                        $otherChecked = $checkboxValues->contains(\App\Models\FormField::OTHER_OPTION_VALUE)
                            || $checkboxValues->contains(fn ($value) => \App\Models\FormField::isOtherResponse($value));
                        $otherFieldDisabledAttr = (!$columnVisible || !$otherChecked) ? 'disabled' : '';
                    @endphp
                    <div class="d-flex flex-column gap-1">
                        @foreach($column['options'] ?? [] as $optionIndex => $option)
                            @php $checkboxInputId = "{$field->id}_{$rowIndex}_{$columnKey}_{$optionIndex}"; @endphp
                            <div class="form-check">
                                <input
                                    class="form-check-input {{ $isInvalid ? 'is-invalid' : '' }}"
                                    type="checkbox"
                                    name="{{ $baseName }}[{{ $columnKey }}][]"
                                    value="{{ $option }}"
                                    id="{{ $checkboxInputId }}"
                                    {{ $checkboxValues->contains($option) ? 'checked' : '' }}
                                    {{ $columnDisabledAttr }}
                                >
                                <label class="form-check-label small" for="{{ $checkboxInputId }}">{{ $option }}</label>
                            </div>
                        @endforeach
                        @if(!empty($column['allow_custom_answer']))
                            @php $otherInputId = "{$field->id}_{$rowIndex}_{$columnKey}_other"; @endphp
                            <div class="form-check" data-other-option>
                                <input
                                    class="form-check-input {{ $isInvalid ? 'is-invalid' : '' }}"
                                    type="checkbox"
                                    name="{{ $baseName }}[{{ $columnKey }}][]"
                                    value="{{ \App\Models\FormField::OTHER_OPTION_VALUE }}"
                                    id="{{ $otherInputId }}_toggle"
                                    data-other-toggle
                                    data-other-input-id="{{ $otherInputId }}"
                                    {{ $otherChecked ? 'checked' : '' }}
                                    {{ $columnDisabledAttr }}
                                >
                                <label class="form-check-label small" for="{{ $otherInputId }}_toggle">{{ $column['other_label'] }}</label>
                                <input
                                    type="text"
                                    name="{{ $baseName }}[{{ $otherInputKey }}]"
                                    id="{{ $otherInputId }}"
                                    value="{{ is_string($otherValue) ? $otherValue : '' }}"
                                    class="form-control form-control-sm mt-2 {{ $isInvalid ? 'is-invalid' : '' }}"
                                    placeholder="Please specify"
                                    data-other-label="{{ $column['other_label'] }}"
                                    data-other-input-field
                                    {{ $otherFieldDisabledAttr }}
                                >
                            </div>
                        @endif
                    </div>
                    @break

                @case('checkbox_dropdown')
                    @php
                        $checkboxValues = collect((array) $columnValue);
                        $selectedLabels = collect($column['options'] ?? [])
                            ->filter(fn ($option) => $checkboxValues->contains($option))
                            ->values();
                        $selectedCount = $selectedLabels->count();
                        $defaultSummary = 'Select options...';
                        $selectionSummary = $selectedCount === 0
                            ? $defaultSummary
                            : ($selectedCount <= 2
                                ? $selectedLabels->implode(', ')
                                : "{$selectedCount} selected");
                    @endphp
                    <div class="dropdown checkbox-dropdown checkbox-dropdown-sm" data-table-checkbox-dropdown>
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center {{ $isInvalid ? 'is-invalid' : '' }}"
                                type="button"
                                id="{{ $field->id }}_{{ $rowIndex }}_{{ $columnKey }}_dropdown"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                {{ $columnDisabledAttr }}>
                            <span class="text-truncate pe-2" data-table-checkbox-dropdown-summary data-placeholder="{{ $defaultSummary }}">{{ $selectionSummary }}</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-2 checkbox-dropdown-menu" aria-labelledby="{{ $field->id }}_{{ $rowIndex }}_{{ $columnKey }}_dropdown">
                            @foreach($column['options'] ?? [] as $optionIndex => $option)
                                @php $checkboxInputId = "{$field->id}_{$rowIndex}_{$columnKey}_dropdown_{$optionIndex}"; @endphp
                                <li>
                                    <div class="form-check mb-0">
                                        <input
                                            class="form-check-input {{ $isInvalid ? 'is-invalid' : '' }}"
                                            type="checkbox"
                                            name="{{ $baseName }}[{{ $columnKey }}][]"
                                            value="{{ $option }}"
                                            id="{{ $checkboxInputId }}"
                                            data-table-checkbox-dropdown-option
                                            {{ $checkboxValues->contains($option) ? 'checked' : '' }}
                                            {{ $columnDisabledAttr }}
                                        >
                                        <label class="form-check-label small" for="{{ $checkboxInputId }}">{{ $option }}</label>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @break

                @case('phone')
                    <input
                        type="tel"
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        value="{{ is_string($columnValue) ? $columnValue : '' }}"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                        {{ $columnDisabledAttr }}
                    >
                    @break

                @default
                    <input
                        type="{{ $column['type'] }}"
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        value="{{ is_scalar($columnValue) ? $columnValue : '' }}"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                        {{ $columnDisabledAttr }}
                    >
            @endswitch

            <div class="invalid-feedback {{ $errorMessage ? 'd-block' : 'd-none' }}" data-table-error {{ $errorMessage ? 'data-server-error=1' : '' }}>
                {{ $errorMessage }}
            </div>
        </td>
    @endforeach

    <td class="text-center align-top">
        <input type="hidden" name="{{ $baseName }}[__row]" value="1">
        <button type="button" class="btn btn-outline-danger btn-sm js-table-remove-row" title="Remove row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
