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

        <div class="row g-3">
        @foreach($form->fields as $field)
        @if($field->type === 'section')
        <div class="col-12 my-4">
            <hr>
            <h5 class="fw-semibold mb-1">{{ $field->label }}</h5>
            @if($field->placeholder)
                <p class="text-muted small mb-0">{{ $field->placeholder }}</p>
            @endif
        </div>
        @elseif($field->type === 'label')
        <div class="col-12 mb-4" data-display-label-field>
            <div class="form-control-plaintext fw-semibold py-0">{{ $field->label }}</div>
            @if($field->placeholder)
                <div class="text-muted small">{{ $field->placeholder }}</div>
            @endif
        </div>
        @elseif($field->type === 'table')
        <div class="col-12">
            @include('public.forms.partials.table-field', ['field' => $field])
        </div>
        @else
        @php
            $fieldError = $errors->first($field->name);
            $otherFieldError = in_array($field->type, ['checkbox', 'radio'], true)
                ? $errors->first($field->other_input_name)
                : null;
            $visibilityRule = $field->visibility_rule;
            $controllerField = $visibilityRule ? $fieldsById->get($visibilityRule['field_id']) : null;
            $hasVisibilityRule = $visibilityRule && $controllerField;
            $isInitiallyVisible = !$hasVisibilityRule
                || $field->passesVisibilityCondition(old($controllerField->name));
            $fieldDisabledAttr = $isInitiallyVisible ? '' : 'disabled';
            $fieldConfig = is_array($field->config) ? $field->config : [];
            $minValue = is_numeric($fieldConfig['min_value'] ?? null) ? $fieldConfig['min_value'] : null;
            $maxValue = is_numeric($fieldConfig['max_value'] ?? null) ? $fieldConfig['max_value'] : null;
            $minLength = ($fieldConfig['min_length'] ?? null);
            $maxLength = ($fieldConfig['max_length'] ?? null);
            $minLength = is_numeric($minLength) ? max(0, (int) $minLength) : null;
            $maxLength = is_numeric($maxLength) ? max(0, (int) $maxLength) : null;
        @endphp
        <div class="col-md-{{ $field->col_width }} form-field"
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
            @php
                $labelForTypes = ['radio', 'checkbox', 'checkbox_dropdown'];
                $labelFor = in_array($field->type, $labelForTypes) ? null
                    : ($field->type === 'searchable_select' ? $field->name.'_search' : $field->name);
            @endphp
            <label class="form-label fw-semibold" @if($labelFor) for="{{ $labelFor }}" @endif>
                {{ $field->label }}
                @if($field->required) <span class="text-danger">*</span> @endif
            </label>

            @switch($field->type)
                @case('textarea')
                    <textarea name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        rows="4"
                        placeholder="{{ $field->placeholder }}"
                        @if($minLength !== null) minlength="{{ $minLength }}" @endif
                        @if($maxLength !== null) maxlength="{{ $maxLength }}" @endif
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
                @case('searchable_select')
                    @php $searchableSelectValue = old($field->name, $field->default_value); @endphp
                    <div class="searchable-select-wrapper position-relative" data-searchable-select>
                        <input type="text"
                               id="{{ $field->name }}_search"
                               class="form-control @error($field->name) is-invalid @enderror"
                               placeholder="{{ $field->placeholder ?: 'Type to search...' }}"
                               value="{{ $searchableSelectValue }}"
                               autocomplete="off"
                               role="combobox"
                               aria-autocomplete="list"
                               aria-expanded="false"
                               aria-controls="{{ $field->name }}_listbox"
                               {{ $field->required ? 'required' : '' }}
                               {{ $fieldDisabledAttr }}
                               data-ss-input>
                        <input type="hidden"
                               name="{{ $field->name }}"
                               id="{{ $field->name }}"
                               value="{{ $searchableSelectValue }}"
                               aria-label="{{ $field->label }}"
                               data-ss-hidden>
                        <span class="visually-hidden" aria-live="polite" aria-atomic="true" data-ss-live></span>
                        <ul id="{{ $field->name }}_listbox"
                            class="searchable-select-dropdown list-unstyled mb-0"
                            role="listbox"
                            style="display:none"
                            data-ss-listbox>
                            @foreach($field->options_array as $option)
                                <li id="{{ $field->name }}_option_{{ $loop->index }}"
                                    role="option"
                                    class="searchable-select-option{{ $searchableSelectValue == $option ? ' active' : '' }}"
                                    data-ss-option="{{ $option }}">{{ $option }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @break
                @case('radio')
                    @php
                        $oldRadioValue = old($field->name, $field->default_value);
                        $oldRadioOtherValue = old($field->other_input_name);

                        if (!$oldRadioOtherValue) {
                            $oldRadioOtherValue = FormField::extractOtherResponse($oldRadioValue);
                        }
                    @endphp
                    @foreach($field->selectable_options as $option)
                        <div class="form-check">
                            <input class="form-check-input @if($fieldError || $otherFieldError) is-invalid @endif" type="radio"
                                   name="{{ $field->name }}" value="{{ $option }}"
                                    id="{{ $field->name }}_{{ $loop->index }}"
                                     {{ $oldRadioValue == $option ? 'checked' : '' }}
                                    {{ $field->required ? 'required' : '' }}
                                    {{ $fieldDisabledAttr }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @if($field->hasOtherOption())
                        @php
                            $radioOtherChecked = $oldRadioValue == FormField::OTHER_OPTION_VALUE || FormField::isOtherResponse($oldRadioValue);
                            $radioOtherFieldDisabledAttr = (!$isInitiallyVisible || !$radioOtherChecked) ? 'disabled' : '';
                        @endphp
                        <div class="form-check" data-other-option>
                            <input class="form-check-input @if($fieldError || $otherFieldError) is-invalid @endif" type="radio"
                                   name="{{ $field->name }}" value="{{ FormField::OTHER_OPTION_VALUE }}"
                                   id="{{ $field->name }}_other_toggle"
                                   data-other-toggle
                                   data-other-input-id="{{ $field->other_input_name }}"
                                   {{ $radioOtherChecked ? 'checked' : '' }}
                                   {{ $field->required ? 'required' : '' }}
                                   {{ $fieldDisabledAttr }}>
                            <label class="form-check-label" for="{{ $field->name }}_other_toggle">{{ $field->other_label }}</label>
                            <input type="text"
                                    name="{{ $field->other_input_name }}"
                                   id="{{ $field->other_input_name }}"
                                   value="{{ $oldRadioOtherValue }}"
                                    class="form-control mt-2 @error($field->other_input_name) is-invalid @enderror"
                                    placeholder="Please specify"
                                    data-other-label="{{ $field->other_label }}"
                                    data-other-input-field
                                    {{ $radioOtherFieldDisabledAttr }}>
                        </div>
                    @endif
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
                @case('checkbox_dropdown')
                    @php
                        $oldCheckboxValues = (array) old($field->name, []);
                        $oldCheckboxLookup = array_flip($oldCheckboxValues);
                        $selectedLabels = collect($field->selectable_options)
                            ->filter(fn ($option) => array_key_exists($option, $oldCheckboxLookup))
                            ->values();
                        $selectedCount = $selectedLabels->count();
                        $defaultSummary = $field->placeholder ?: 'Select options...';
                        $selectionSummary = $selectedCount === 0
                            ? $defaultSummary
                            : ($selectedCount <= 2
                                ? $selectedLabels->implode(', ')
                                : "{$selectedCount} selected");
                    @endphp
                    <div class="dropdown checkbox-dropdown" data-checkbox-dropdown>
                        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center @if($fieldError) is-invalid @endif"
                                type="button"
                                id="{{ $field->name }}_dropdown"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                            <span class="text-truncate pe-2" data-checkbox-dropdown-summary>{{ $selectionSummary }}</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-2 checkbox-dropdown-menu" aria-labelledby="{{ $field->name }}_dropdown">
                            @foreach($field->selectable_options as $option)
                                <li>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input @if($fieldError) is-invalid @endif" type="checkbox"
                                               name="{{ $field->name }}[]" value="{{ $option }}"
                                               id="{{ $field->name }}_dropdown_{{ $loop->index }}"
                                               data-checkbox-dropdown-option
                                               {{ array_key_exists($option, $oldCheckboxLookup) ? 'checked' : '' }}
                                               {{ $fieldDisabledAttr }}>
                                        <label class="form-check-label" for="{{ $field->name }}_dropdown_{{ $loop->index }}">{{ $option }}</label>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
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
                @case('number')
                    <input type="number" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        @if($minValue !== null) min="{{ $minValue }}" @endif
                        @if($maxValue !== null) max="{{ $maxValue }}" @endif
                        @if($minLength !== null) minlength="{{ $minLength }}" @endif
                        @if($maxLength !== null) maxlength="{{ $maxLength }}" @endif
                        {{ $field->required ? 'required' : '' }}
                        {{ $fieldDisabledAttr }}>
                    @break
                @case('text')
                    <input type="text" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        @if($minLength !== null) minlength="{{ $minLength }}" @endif
                        @if($maxLength !== null) maxlength="{{ $maxLength }}" @endif
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
        </div>{{-- end .row --}}

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

@push('styles')
<style>
    .checkbox-dropdown-menu {
        max-height: 260px;
        overflow-y: auto;
    }
    .searchable-select-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
        background: #fff;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 .375rem .375rem;
        max-height: 220px;
        overflow-y: auto;
        box-shadow: 0 4px 8px rgba(0,0,0,.1);
    }
    .searchable-select-option {
        padding: .4rem .75rem;
        cursor: pointer;
        font-size: .9375rem;
    }
    .searchable-select-option:hover,
    .searchable-select-option.focused {
        background-color: #f0f4ff;
    }
    .searchable-select-option.active {
        background-color: #e7f0ff;
        font-weight: 500;
    }
    .searchable-select-no-results {
        padding: .4rem .75rem;
        color: #6c757d;
        font-size: .9375rem;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var defaultOtherLabel = @js(FormField::DEFAULT_OTHER_LABEL);
    var otherOptionValue = @js(FormField::OTHER_OPTION_VALUE);

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

        var maxRows = parseInt(tableWrapper.dataset.maxRows, 10);
        if (!isNaN(maxRows) && maxRows > 0) {
            var addButton = tableWrapper.querySelector('.js-table-add-row');
            var maxMsg = tableWrapper.querySelector('.js-table-max-rows-msg');
            var atLimit = rows.length >= maxRows;
            if (addButton) {
                addButton.disabled = atLimit;
                addButton.classList.toggle('d-none', atLimit);
            }
            if (maxMsg) {
                maxMsg.classList.toggle('d-none', !atLimit);
            }
        }
    }

    function clearCellValidation(cell) {
        cell.classList.remove('table-cell-invalid');

        cell.querySelectorAll('[data-table-error]').forEach(function (feedback) {
            feedback.textContent = '';
            feedback.classList.add('d-none');
        });

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

    function getTableCellOtherInput(cell) {
        var columnKey = cell.dataset.columnKey;
        if (!columnKey) {
            return null;
        }

        return Array.from(cell.querySelectorAll('[data-other-input-field]')).find(function (input) {
            return (input.name || '').endsWith('[' + columnKey + '_other]');
        }) || null;
    }

    function getTableCellOtherToggle(cell, otherInput) {
        var toggles = Array.from(cell.querySelectorAll('[data-other-toggle]'));
        if (toggles.length === 0) {
            return null;
        }

        if (otherInput && otherInput.id) {
            var matchedByInputId = toggles.find(function (toggle) {
                return toggle.dataset.otherInputId === otherInput.id;
            });

            if (matchedByInputId) {
                return matchedByInputId;
            }
        }

        var columnKey = cell.dataset.columnKey;
        if (!columnKey) {
            return toggles[0] || null;
        }

        return toggles.find(function (toggle) {
            return (toggle.name || '').endsWith('[' + columnKey + ']');
        }) || null;
    }

    function getTableCellSelect(cell) {
        var columnKey = cell.dataset.columnKey;
        if (!columnKey) {
            return null;
        }

        return Array.from(cell.querySelectorAll('select')).find(function (select) {
            return (select.name || '').endsWith('[' + columnKey + ']');
        }) || null;
    }

    function getCheckboxOtherState(cell) {
        var input = getTableCellOtherInput(cell);
        var toggle = getTableCellOtherToggle(cell, input);

        return {
            toggle: toggle,
            input: input,
            label: input?.dataset.otherLabel || defaultOtherLabel,
        };
    }

    function initializeTableCheckboxDropdownSummary(cell) {
        if (!cell || cell.dataset.columnType !== 'checkbox_dropdown') {
            return;
        }

        var summaryElement = cell.querySelector('[data-table-checkbox-dropdown-summary]');
        if (summaryElement && !summaryElement.dataset.placeholder) {
            summaryElement.dataset.placeholder = (summaryElement.textContent || '').trim() || 'Select options...';
        }
    }

    function updateTableCheckboxDropdownSummary(cell) {
        if (!cell || cell.dataset.columnType !== 'checkbox_dropdown') {
            return;
        }

        var summaryElement = cell.querySelector('[data-table-checkbox-dropdown-summary]');
        if (!summaryElement) {
            return;
        }

        var checked = Array.from(cell.querySelectorAll('[data-table-checkbox-dropdown-option]:checked'));
        if (checked.length === 0) {
            summaryElement.textContent = summaryElement.dataset.placeholder || 'Select options...';
            return;
        }

        var selectedLabels = checked
            .map(function (input) {
                var labelElement = input.closest('.form-check')?.querySelector('.form-check-label');
                var label = labelElement ? labelElement.textContent.trim() : '';
                return label || input.value;
            })
            .filter(function (value) { return value !== ''; });

        summaryElement.textContent = selectedLabels.length <= 2
            ? selectedLabels.join(', ')
            : selectedLabels.length + ' selected';
    }

    function updateTableCellOtherInputState(cell) {
        var type = cell.dataset.columnType;
        if (!['checkbox', 'radio', 'dropdown'].includes(type)) {
            return;
        }

        var otherInput = getTableCellOtherInput(cell);
        if (!otherInput) {
            return;
        }

        var isOther = false;
        if (type === 'checkbox' || type === 'radio') {
            var otherToggle = getTableCellOtherToggle(cell, otherInput);
            isOther = !!(otherToggle && otherToggle.checked);
        } else if (type === 'dropdown') {
            var select = getTableCellSelect(cell);
            isOther = !!(select && select.value === otherOptionValue);
        }

        otherInput.disabled = !isOther;
        if (!isOther) {
            otherInput.value = '';
            otherInput.classList.remove('is-invalid');
        }
    }

    function cellHasValue(cell, type) {
        if (cell.dataset.visibilityState === 'hidden') {
            return false;
        }

        var inputs = cell.querySelectorAll('input, select, textarea');

        if (type === 'checkbox' || type === 'checkbox_dropdown') {
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
            ? normalizedActual.includes(normalizedExpected)
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

        if (cell.dataset.columnType === 'checkbox' || cell.dataset.columnType === 'checkbox_dropdown') {
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

    function syncInlineDependentCell(cell, visible) {
        var controls = cell.querySelectorAll('input, select, textarea, button');

        cell.style.display = visible ? '' : 'none';
        cell.dataset.visibilityState = visible ? 'visible' : 'hidden';

        controls.forEach(function (control) {
            control.disabled = !visible;
        });

        if (!visible) {
            clearCellValues(cell);
            clearCellValidation(cell);
            updateTableCheckboxDropdownSummary(cell);
            return;
        }

        // Restore "other" input state for radio/dropdown inside the revealed cell.
        updateTableCellOtherInputState(cell);
        updateTableCheckboxDropdownSummary(cell);
    }

    function syncRowVisibility(row, changedColumnKey) {
        // Handle inline dependent cells (rendered inside their controlling <td>).
        row.querySelectorAll('[data-inline-dependent-cell]').forEach(function (cell) {
            if (changedColumnKey && cell.dataset.visibilityField !== changedColumnKey) {
                return;
            }

            var actualValue = getRowCellValue(row, cell.dataset.visibilityField || '');
            var visible = evaluateVisibilityCondition(actualValue, cell.dataset.visibilityOperator || 'equals', cell.dataset.visibilityValue || '');
            syncInlineDependentCell(cell, visible);
        });
    }

    function syncRowVisibilityForCell(cell) {
        var row = cell.closest('[data-table-row]');
        if (row) {
            syncRowVisibility(row);
        }
    }

    function validateCell(cell, tableValid, valid) {
        if (cell.dataset.visibilityState === 'hidden') {
            clearCellValidation(cell);
            return { tableValid: tableValid, valid: valid };
        }

        clearCellValidation(cell);

        var otherState = cell.dataset.columnType === 'checkbox' ? getCheckboxOtherState(cell) : null;
        if (otherState?.toggle?.checked && otherState.input?.value.trim() === '') {
            markCellInvalid(cell, 'Please enter a value for ' + otherState.label + '.');
            return { tableValid: false, valid: false };
        }

        // Validate "other" text input for radio and dropdown
        if (cell.dataset.columnType === 'radio' || cell.dataset.columnType === 'dropdown') {
            var otherToggleOrSelect = cell.dataset.columnType === 'radio'
                ? cell.querySelector('[data-other-toggle]')
                : null;
            var selectEl = cell.dataset.columnType === 'dropdown' ? cell.querySelector('select') : null;
            var otherInputEl = cell.querySelector('[data-other-input-field]');
            var needsOther = otherToggleOrSelect
                ? otherToggleOrSelect.checked
                : (selectEl ? selectEl.value === otherOptionValue : false);
            if (needsOther && otherInputEl && otherInputEl.value.trim() === '') {
                var otherLabel = otherInputEl.dataset.otherLabel || defaultOtherLabel;
                markCellInvalid(cell, 'Please enter a value for ' + otherLabel + '.');
                return { tableValid: false, valid: false };
            }
        }

        if (cell.dataset.required === '1' && !cellHasValue(cell, cell.dataset.columnType)) {
            markCellInvalid(cell, 'This field is required.');
            return { tableValid: false, valid: false };
        }

        return { tableValid: tableValid, valid: valid };
    }

    function validateRepeatableTables(form) {
        var valid = true;

        form.querySelectorAll('[data-repeatable-table]').forEach(function (tableWrapper) {
            var summaryError = tableWrapper.querySelector('[data-table-summary-error]');
            var tableValid = true;

            tableWrapper.querySelectorAll('[data-table-row]').forEach(function (row) {
                row.querySelectorAll('td[data-column-type]').forEach(function (cell) {
                    var result = validateCell(cell, tableValid, valid);
                    tableValid = result.tableValid;
                    valid = result.valid;

                    // Also validate any inline dependent cells inside this <td>.
                    cell.querySelectorAll('[data-inline-dependent-cell]').forEach(function (depCell) {
                        var depResult = validateCell(depCell, tableValid, valid);
                        tableValid = depResult.tableValid;
                        valid = depResult.valid;
                    });
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
            row.querySelectorAll('[data-column-type="checkbox_dropdown"]').forEach(function (cell) {
                initializeTableCheckboxDropdownSummary(cell);
                updateTableCheckboxDropdownSummary(cell);
            });
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
                    newRow.querySelectorAll('[data-column-type="checkbox_dropdown"]').forEach(function (cell) {
                        initializeTableCheckboxDropdownSummary(cell);
                        updateTableCheckboxDropdownSummary(cell);
                    });
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
            var summaryError = tableWrapper.querySelector('[data-table-summary-error]');

            // If change happened inside an inline dependent cell, handle it directly.
            var inlineCell = event.target.closest('[data-inline-dependent-cell]');
            if (inlineCell) {
                clearCellValidation(inlineCell);
                updateTableCheckboxDropdownSummary(inlineCell);
                updateTableCellOtherInputState(inlineCell);
                if (summaryError) {
                    summaryError.classList.add('d-none');
                }
                return;
            }

            var cell = event.target.closest('td[data-column-type]');
            if (cell) {
                syncRowVisibilityForCell(cell);
                clearCellValidation(cell);
                updateTableCheckboxDropdownSummary(cell);
                updateTableCellOtherInputState(cell);
                if (summaryError) {
                    summaryError.classList.add('d-none');
                }
            }
        });

        tableWrapper.addEventListener('input', function (event) {
            var summaryError = tableWrapper.querySelector('[data-table-summary-error]');

            var inlineCell = event.target.closest('[data-inline-dependent-cell]');
            if (inlineCell) {
                clearCellValidation(inlineCell);
                updateTableCheckboxDropdownSummary(inlineCell);
                if (summaryError) {
                    summaryError.classList.add('d-none');
                }
                return;
            }

            var cell = event.target.closest('td[data-column-type="checkbox"], td[data-column-type="checkbox_dropdown"]');
            if (cell) {
                syncRowVisibilityForCell(cell);
                clearCellValidation(cell);
                updateTableCheckboxDropdownSummary(cell);
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
<script>
(function () {
    function initSearchableSelect(wrapper) {
        var searchInput = wrapper.querySelector('[data-ss-input]');
        var hiddenInput = wrapper.querySelector('[data-ss-hidden]');
        var listbox = wrapper.querySelector('[data-ss-listbox]');
        var liveRegion = wrapper.querySelector('[data-ss-live]');
        if (!searchInput || !hiddenInput || !listbox) {
            return;
        }

        var allOptions = Array.from(listbox.querySelectorAll('[data-ss-option]'));
        var focusedIndex = -1;

        var noResultsEl = document.createElement('li');
        noResultsEl.className = 'searchable-select-no-results';
        noResultsEl.textContent = 'No results found';
        noResultsEl.style.display = 'none';
        listbox.appendChild(noResultsEl);

        function getVisibleOptions() {
            return allOptions.filter(function (opt) {
                return opt.style.display !== 'none';
            });
        }

        function announce(text) {
            if (liveRegion) {
                liveRegion.textContent = '';
                // Force re-announcement by briefly clearing then setting
                requestAnimationFrame(function () {
                    liveRegion.textContent = text;
                });
            }
        }

        function openDropdown() {
            listbox.style.display = '';
            searchInput.setAttribute('aria-expanded', 'true');
            var count = getVisibleOptions().length;
            announce(count + ' option' + (count !== 1 ? 's' : '') + ' available');
        }

        function closeDropdown() {
            listbox.style.display = 'none';
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.removeAttribute('aria-activedescendant');
            focusedIndex = -1;
            allOptions.forEach(function (opt) { opt.classList.remove('focused'); });
        }

        function selectOption(value, label) {
            hiddenInput.value = value;
            searchInput.value = label;
            allOptions.forEach(function (opt) {
                opt.classList.toggle('active', opt.dataset.ssOption === value);
            });
            closeDropdown();
        }

        function filterOptions(query) {
            var lower = query.toLowerCase();
            var hasVisible = false;

            allOptions.forEach(function (opt) {
                var match = opt.dataset.ssOption.toLowerCase().includes(lower);
                opt.style.display = match ? '' : 'none';
                if (match) {
                    hasVisible = true;
                }
                opt.classList.remove('focused');
            });

            noResultsEl.style.display = hasVisible ? 'none' : '';

            focusedIndex = -1;
            searchInput.removeAttribute('aria-activedescendant');
        }

        function moveFocus(direction) {
            var visible = getVisibleOptions();
            if (visible.length === 0) {
                return;
            }

            allOptions.forEach(function (opt) { opt.classList.remove('focused'); });

            if (direction === 'down') {
                focusedIndex = focusedIndex < visible.length - 1 ? focusedIndex + 1 : 0;
            } else {
                focusedIndex = focusedIndex > 0 ? focusedIndex - 1 : visible.length - 1;
            }

            visible[focusedIndex].classList.add('focused');
            visible[focusedIndex].scrollIntoView({ block: 'nearest' });
            if (visible[focusedIndex].id) {
                searchInput.setAttribute('aria-activedescendant', visible[focusedIndex].id);
            }
        }

        searchInput.addEventListener('focus', function () {
            filterOptions(searchInput.value);
            openDropdown();
        });

        searchInput.addEventListener('input', function () {
            hiddenInput.value = searchInput.value;
            filterOptions(searchInput.value);
            openDropdown();
        });

        searchInput.addEventListener('keydown', function (e) {
            var isOpen = listbox.style.display !== 'none';
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                openDropdown();
                moveFocus('down');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                openDropdown();
                moveFocus('up');
            } else if (e.key === 'Enter') {
                if (isOpen) {
                    e.preventDefault();
                    var visible = getVisibleOptions();
                    if (focusedIndex >= 0 && visible[focusedIndex]) {
                        var opt = visible[focusedIndex];
                        selectOption(opt.dataset.ssOption, opt.textContent);
                    } else {
                        closeDropdown();
                    }
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // Use mousedown to prevent blur before selection, then click to select.
        listbox.addEventListener('mousedown', function (e) {
            if (e.target.closest('[data-ss-option]')) {
                e.preventDefault();
            }
        });

        listbox.addEventListener('click', function (e) {
            var optEl = e.target.closest('[data-ss-option]');
            if (optEl) {
                selectOption(optEl.dataset.ssOption, optEl.textContent);
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                closeDropdown();
            }
        });
    }

    document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);
})();
</script>
@endpush
