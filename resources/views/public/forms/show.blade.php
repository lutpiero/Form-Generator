@extends('layouts.public')
@php use App\Models\FormField; @endphp

@section('title', $form->name)

@section('content')
<div class="form-card p-4 p-md-5">
    @if($form->header_image)
        <div class="mb-4 mx-n4 mx-md-n5 mt-n4 mt-md-n5">
            <img src="{{ Storage::url($form->header_image) }}" alt="{{ $form->name }}"
                 class="img-fluid w-100 rounded-top" style="max-height: 300px; object-fit: cover;">
        </div>
    @endif
    <h2 class="form-title mb-2">{{ $form->name }}</h2>
    @if($form->description)
        <p class="text-muted mb-4">{{ $form->description }}</p>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('forms.submit', $form) }}" id="public-form" data-form-validation novalidate>
        @csrf
        @php
            $fieldsById = $form->fields->keyBy('id');
        @endphp

        @foreach($form->fields as $field)
        @if($field->type === 'section')
        <div class="my-4">
            <hr>
            <h5 class="fw-semibold mb-1">{{ $field->label }}</h5>
            @if($field->placeholder)
                <p class="text-muted small mb-0">{{ $field->placeholder }}</p>
            @endif
        </div>
        @elseif($field->type === 'table')
            @include('public.forms.partials.table-field', ['field' => $field])
        @else
        @php
            $fieldError = $errors->first($field->name);
            $otherFieldError = $field->type === 'checkbox' ? $errors->first($field->other_input_name) : null;
            $visibilityRule = $field->visibility_rule;
            $controllerField = $visibilityRule ? $fieldsById->get($visibilityRule['field_id']) : null;
            $hasVisibilityRule = $visibilityRule && $controllerField;
            $isInitiallyVisible = !$hasVisibilityRule
                || $field->passesVisibilityCondition(old($controllerField->name));
            $fieldDisabledAttr = $isInitiallyVisible ? '' : 'disabled';
        @endphp
        <div class="mb-3 form-field"
             data-field-type="{{ $field->type }}"
             data-field-name="{{ $field->name }}"
             data-label="{{ $field->label }}"
             data-required="{{ $field->required ? 'true' : 'false' }}"
             data-visibility-enabled="{{ $hasVisibilityRule ? 'true' : 'false' }}"
             data-visibility-field="{{ $hasVisibilityRule ? $controllerField->name : '' }}"
             data-visibility-operator="{{ $hasVisibilityRule ? $visibilityRule['operator'] : '' }}"
             data-visibility-value="{{ $hasVisibilityRule ? $visibilityRule['value'] : '' }}"
             data-visibility-state="{{ $isInitiallyVisible ? 'visible' : 'hidden' }}"
             style="{{ $isInitiallyVisible ? '' : 'display:none;' }}">
            <label class="form-label fw-semibold" @if(!in_array($field->type, ['radio', 'checkbox'])) for="{{ $field->name }}" @endif>
                {{ $field->label }}
                @if($field->required) <span class="text-danger">*</span> @endif
            </label>

            @switch($field->type)
                @case('textarea')
                    <textarea name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        rows="4"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}
                        {{ $fieldDisabledAttr }}>{{ old($field->name, $field->default_value) }}</textarea>
                    @break
                @case('dropdown')
                    <select name="{{ $field->name }}" id="{{ $field->name }}" class="form-select @error($field->name) is-invalid @enderror" {{ $field->required ? 'required' : '' }} {{ $fieldDisabledAttr }}>
                        <option value="">{{ $field->placeholder ?: 'Select an option' }}</option>
                        @foreach($field->options_array as $option)
                            <option value="{{ $option }}" {{ old($field->name) == $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                    @break
                @case('radio')
                    @foreach($field->options_array as $option)
                        <div class="form-check">
                            <input class="form-check-input @error($field->name) is-invalid @enderror" type="radio"
                                   name="{{ $field->name }}" value="{{ $option }}"
                                   id="{{ $field->name }}_{{ $loop->index }}"
                                    {{ old($field->name) == $option ? 'checked' : '' }}
                                   {{ $field->required ? 'required' : '' }}
                                   {{ $fieldDisabledAttr }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @break
                @case('checkbox')
                    @php
                        $oldCheckboxValues = collect((array) old($field->name, []));
                        $oldOtherValue = old($field->other_input_name);

                        if (!$oldOtherValue) {
                            $storedOtherValue = $oldCheckboxValues->first(fn ($value) => FormField::isOtherResponse($value));
                            $oldOtherValue = FormField::extractOtherResponse($storedOtherValue);
                        }
                    @endphp
                    @foreach($field->selectable_options as $option)
                        <div class="form-check">
                            <input class="form-check-input @if($fieldError) is-invalid @endif" type="checkbox"
                                   name="{{ $field->name }}[]" value="{{ $option }}"
                                   id="{{ $field->name }}_{{ $loop->index }}"
                                   {{ $oldCheckboxValues->contains($option) ? 'checked' : '' }}
                                   {{ $fieldDisabledAttr }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @if($field->hasOtherOption())
                        @php
                            $otherChecked = $oldCheckboxValues->contains(FormField::OTHER_OPTION_VALUE)
                                || $oldCheckboxValues->contains(fn ($value) => FormField::isOtherResponse($value));
                            $otherFieldDisabledAttr = (!$isInitiallyVisible || !$otherChecked) ? 'disabled' : '';
                        @endphp
                        <div class="form-check" data-other-option>
                            <input class="form-check-input @if($fieldError || $otherFieldError) is-invalid @endif" type="checkbox"
                                   name="{{ $field->name }}[]" value="{{ FormField::OTHER_OPTION_VALUE }}"
                                   id="{{ $field->name }}_other_toggle"
                                   data-other-toggle
                                   data-other-input-id="{{ $field->other_input_name }}"
                                   {{ $otherChecked ? 'checked' : '' }}
                                   {{ $fieldDisabledAttr }}>
                            <label class="form-check-label" for="{{ $field->name }}_other_toggle">{{ $field->other_label }}</label>
                            <input type="text"
                                    name="{{ $field->other_input_name }}"
                                   id="{{ $field->other_input_name }}"
                                   value="{{ $oldOtherValue }}"
                                    class="form-control mt-2 @error($field->other_input_name) is-invalid @enderror"
                                    placeholder="Please specify"
                                    data-other-label="{{ $field->other_label }}"
                                    data-other-input-field
                                    {{ $otherFieldDisabledAttr }}>
                        </div>
                    @endif
                    @break
                @case('phone')
                    <input type="tel" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        pattern="{{ App\Models\FormField::PHONE_PATTERN }}"
                        inputmode="tel"
                        {{ $field->required ? 'required' : '' }}
                        {{ $fieldDisabledAttr }}>
                    @break
                @default
                    <input type="{{ $field->type }}" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}
                        {{ $fieldDisabledAttr }}>
            @endswitch

            <div class="invalid-feedback{{ $fieldError || $otherFieldError ? ' d-block' : '' }}" data-feedback>
                {{ $fieldError ?: $otherFieldError }}
            </div>
        </div>
        @endif
        @endforeach

        {{-- Honeypot --}}
        @if($form->captcha_enabled && $form->captcha_type === 'honeypot')
            <div style="display:none">
                <input type="text" name="_honeypot" tabindex="-1" autocomplete="off">
            </div>
        @endif

        {{-- Math CAPTCHA --}}
        @if($form->captcha_enabled && $form->captcha_type === 'math' && $captcha)
            <div class="mb-3 p-3 bg-light rounded form-field" data-field-type="number" data-field-name="captcha_answer" data-label="CAPTCHA" data-required="true">
                <label class="form-label fw-semibold">
                    <i class="bi bi-shield-check"></i> CAPTCHA: {{ $captcha['question'] }}
                    <span class="text-danger">*</span>
                </label>
                <input type="number" name="captcha_answer" id="captcha_answer"
                    class="form-control @error('captcha_answer') is-invalid @enderror"
                    placeholder="Enter the answer" required>
                @error('captcha_answer')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary btn-submit text-white">
                <i class="bi bi-send"></i> Submit
            </button>
        </div>
    </form>
</div>
<script src="{{ asset('js/form-validation.js') }}" defer></script>
@endsection

@push('scripts')
<script>
(function () {
    var defaultOtherLabel = @js(FormField::DEFAULT_OTHER_LABEL);

    function updateTableState(tableWrapper) {
        var rows = Array.from(tableWrapper.querySelectorAll('[data-table-row]'));
        rows.forEach(function (row, index) {
            var numberCell = row.querySelector('[data-table-row-number]');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }

            var removeButton = row.querySelector('.js-table-remove-row');
            if (removeButton) {
                removeButton.disabled = rows.length === 1;
            }
        });
    }

    function clearCellValidation(cell) {
        cell.classList.remove('table-cell-invalid');

        var feedback = cell.querySelector('[data-table-error]');
        if (feedback) {
            feedback.textContent = '';
            feedback.classList.add('d-none');
        }

        cell.querySelectorAll('.is-invalid').forEach(function (element) {
            element.classList.remove('is-invalid');
        });
    }

    function markCellInvalid(cell, message) {
        cell.classList.add('table-cell-invalid');
        cell.querySelectorAll('input, select, textarea').forEach(function (element) {
            element.classList.add('is-invalid');
        });

        var feedback = cell.querySelector('[data-table-error]');
        if (feedback) {
            feedback.textContent = message;
            feedback.classList.remove('d-none');
        }
    }

    function getCheckboxOtherState(cell) {
        var toggle = cell.querySelector('[data-other-toggle]');
        var input = cell.querySelector('[data-other-input-field]');

        return {
            toggle: toggle,
            input: input,
            label: input?.dataset.otherLabel || defaultOtherLabel,
        };
    }

    function cellHasValue(cell, type) {
        if (cell.dataset.visibilityState === 'hidden') {
            return false;
        }

        var inputs = cell.querySelectorAll('input, select, textarea');

        if (type === 'checkbox') {
            return Array.from(inputs).some(function (input) {
                return input.type === 'checkbox' && input.checked;
            });
        }

        if (type === 'radio') {
            return Array.from(inputs).some(function (input) {
                return input.type === 'radio' && input.checked;
            });
        }

        return Array.from(inputs).some(function (input) {
            return input.type !== 'hidden' && String(input.value || '').trim() !== '';
        });
    }

    function normalizeVisibilityValue(value) {
        if (Array.isArray(value)) {
            return value
                .map(function (item) { return String(item || '').trim(); })
                .filter(function (item) { return item !== ''; });
        }

        return String(value || '').trim();
    }

    function evaluateVisibilityCondition(actualValue, operator, expectedValue) {
        var normalizedActual = normalizeVisibilityValue(actualValue);
        var normalizedExpected = String(expectedValue || '').trim();
        var equals = Array.isArray(normalizedActual)
            ? normalizedActual.indexOf(normalizedExpected) !== -1
            : normalizedActual === normalizedExpected;
        var isEmpty = Array.isArray(normalizedActual)
            ? normalizedActual.length === 0
            : normalizedActual === '';

        switch (operator) {
            case 'not_equals':
                return !equals;
            case 'is_empty':
                return isEmpty;
            case 'is_not_empty':
                return !isEmpty;
            default:
                return equals;
        }
    }

    function clearCellValues(cell) {
        cell.querySelectorAll('input, select, textarea').forEach(function (control) {
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = false;
            } else if (control.tagName === 'SELECT') {
                control.selectedIndex = 0;
            } else {
                control.value = '';
            }
        });
    }

    function getRowCellValue(row, columnKey) {
        var cell = row.querySelector('td[data-column-key="' + columnKey + '"]');
        if (!cell) {
            return '';
        }

        if (cell.dataset.columnType === 'checkbox') {
            return Array.from(cell.querySelectorAll('input[type="checkbox"]:checked'))
                .map(function (input) { return input.value; })
                .filter(function (value) { return value !== ''; });
        }

        if (cell.dataset.columnType === 'radio') {
            var checked = cell.querySelector('input[type="radio"]:checked');
            return checked ? checked.value : '';
        }

        var input = cell.querySelector('input:not([type="hidden"]), select, textarea');
        return input ? (input.value || '') : '';
    }

    function syncRowVisibility(row, changedColumnKey) {
        row.querySelectorAll('td[data-column-type]').forEach(function (cell) {
            if (cell.dataset.visibilityEnabled !== 'true') {
                return;
            }

            if (changedColumnKey && cell.dataset.visibilityField !== changedColumnKey) {
                return;
            }

            var actualValue = getRowCellValue(row, cell.dataset.visibilityField || '');
            var visible = evaluateVisibilityCondition(actualValue, cell.dataset.visibilityOperator || 'equals', cell.dataset.visibilityValue || '');
            var controls = cell.querySelectorAll('input, select, textarea');

            cell.style.display = visible ? '' : 'none';
            cell.dataset.visibilityState = visible ? 'visible' : 'hidden';

            controls.forEach(function (control) {
                control.disabled = !visible;
            });

            if (!visible) {
                clearCellValues(cell);
                clearCellValidation(cell);
            }
        });
    }

    function validateRepeatableTables(form) {
        var valid = true;

        form.querySelectorAll('[data-repeatable-table]').forEach(function (tableWrapper) {
            var summaryError = tableWrapper.querySelector('[data-table-summary-error]');
            var tableValid = true;

            tableWrapper.querySelectorAll('[data-table-row]').forEach(function (row) {
                row.querySelectorAll('td[data-column-type]').forEach(function (cell) {
                    if (cell.dataset.visibilityState === 'hidden') {
                        clearCellValidation(cell);
                        return;
                    }

                    clearCellValidation(cell);

                    var otherState = cell.dataset.columnType === 'checkbox' ? getCheckboxOtherState(cell) : null;
                    if (otherState?.toggle?.checked && otherState.input?.value.trim() === '') {
                        tableValid = false;
                        valid = false;
                        markCellInvalid(cell, 'Please enter a value for ' + otherState.label + '.');
                        return;
                    }

                    if (cell.dataset.required === '1' && !cellHasValue(cell, cell.dataset.columnType)) {
                        tableValid = false;
                        valid = false;
                        markCellInvalid(cell, 'This field is required.');
                    }
                });
            });

            if (summaryError) {
                summaryError.classList.toggle('d-none', tableValid);
            }
        });

        return valid;
    }

    document.querySelectorAll('[data-repeatable-table]').forEach(function (tableWrapper) {
        var tbody = tableWrapper.querySelector('[data-table-body]');
        var template = tableWrapper.querySelector('[data-table-row-template]');

        updateTableState(tableWrapper);
        tableWrapper.querySelectorAll('[data-table-row]').forEach(function (row) {
            syncRowVisibility(row);
        });

        tableWrapper.addEventListener('click', function (event) {
            var addButton = event.target.closest('.js-table-add-row');
            if (addButton && template && tbody) {
                var nextIndex = tbody.querySelectorAll('[data-table-row]').length;
                var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
                tbody.insertAdjacentHTML('beforeend', html);
                var newRow = tbody.querySelector('[data-table-row]:last-child');
                if (newRow) {
                    syncRowVisibility(newRow);
                }
                updateTableState(tableWrapper);
                return;
            }

            var removeButton = event.target.closest('.js-table-remove-row');
            if (removeButton) {
                var row = removeButton.closest('[data-table-row]');
                if (row && tbody.querySelectorAll('[data-table-row]').length > 1) {
                    row.remove();
                    updateTableState(tableWrapper);
                }
            }
        });

        tableWrapper.addEventListener('change', function (event) {
            var cell = event.target.closest('td[data-column-type]');
            if (cell) {
                var row = cell.closest('[data-table-row]');
                if (row) {
                    syncRowVisibility(row);
                }
                clearCellValidation(cell);
                var summaryError = tableWrapper.querySelector('[data-table-summary-error]');
                if (summaryError) {
                    summaryError.classList.add('d-none');
                }
            }
        });

        tableWrapper.addEventListener('input', function (event) {
            var cell = event.target.closest('td[data-column-type="checkbox"]');
            if (cell) {
                var row = cell.closest('[data-table-row]');
                if (row) {
                    syncRowVisibility(row);
                }
                clearCellValidation(cell);
            }
        });
    });

    var form = document.querySelector('.form-card form');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!validateRepeatableTables(form)) {
                event.preventDefault();
            }
        });
    }
})();
</script>
@endpush
