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
            $errorMessage = $errors->first($errorKey) ?: $errors->first($errorKey.'.0');
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
                                    {{ in_array($option, (array) $columnValue, true) ? 'checked' : '' }}
                                >
                                <label class="form-check-label small" for="{{ $checkboxInputId }}">{{ $option }}</label>
                            </div>
                        @endforeach
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
