@php
    $rowValues = is_array($rowValues ?? null) ? $rowValues : [];
    $baseName = "table_fields[{$field->id}][{$rowIndex}]";
@endphp

<tr data-table-row>
    @if($field->table_auto_number)
        <td class="text-muted text-center align-middle" data-table-row-number>{{ is_numeric($rowIndex) ? $rowIndex + 1 : '' }}</td>
    @endif

    @foreach($columns as $column)
        @php
            $columnKey = $column['key'];
            $errorKey = "table_fields.{$field->id}.{$rowIndex}.{$columnKey}";
            $otherInputKey = "{$columnKey}_other";
            $errorMessage = $errors->first($errorKey) ?: $errors->first($errorKey.'.0') ?: $errors->first("table_fields.{$field->id}.{$rowIndex}.{$otherInputKey}");
            $isInvalid = $errorMessage !== '';
            $columnValue = $rowValues[$columnKey] ?? null;
        @endphp
        <td data-column-type="{{ $column['type'] }}" data-required="{{ ($column['required'] ?? false) ? '1' : '0' }}">
            @switch($column['type'])
                @case('textarea')
                    <textarea
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                        rows="2"
                    >{{ is_string($columnValue) ? $columnValue : '' }}</textarea>
                    @break

                @case('dropdown')
                    <select
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        class="form-select form-select-sm {{ $isInvalid ? 'is-invalid' : '' }}"
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
                                    {{ $otherChecked ? 'checked' : '' }}
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
                                    {{ $otherChecked ? '' : 'disabled' }}
                                >
                            </div>
                        @endif
                    </div>
                    @break

                @case('phone')
                    <input
                        type="tel"
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        value="{{ is_string($columnValue) ? $columnValue : '' }}"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                    >
                    @break

                @default
                    <input
                        type="{{ $column['type'] }}"
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        value="{{ is_scalar($columnValue) ? $columnValue : '' }}"
                        class="form-control form-control-sm {{ $isInvalid ? 'is-invalid' : '' }}"
                    >
            @endswitch

            <div class="invalid-feedback {{ $errorMessage ? 'd-block' : 'd-none' }}" data-table-error {{ $errorMessage ? 'data-server-error=1' : '' }}>
                {{ $errorMessage }}
            </div>
        </td>
    @endforeach

    <td class="text-center align-middle">
        <input type="hidden" name="{{ $baseName }}[__row]" value="1">
        <button type="button" class="btn btn-outline-danger btn-sm js-table-remove-row" title="Remove row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
