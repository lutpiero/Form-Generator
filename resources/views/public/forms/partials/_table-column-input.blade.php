@php
    $otherInputKey = "{$columnKey}_other";
@endphp
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
            @if(!empty($column['allow_custom_answer']))
                <option value="{{ \App\Models\FormField::OTHER_OPTION_VALUE }}"
                    data-other-option
                    {{ ($columnValue === \App\Models\FormField::OTHER_OPTION_VALUE || \App\Models\FormField::isOtherResponse($columnValue)) ? 'selected' : '' }}>
                    {{ $column['other_label'] }}
                </option>
            @endif
        </select>
        @if(!empty($column['allow_custom_answer']))
            @php
                $dropdownOtherValue = $rowValues[$otherInputKey] ?? null;
                if (!is_string($dropdownOtherValue) || trim($dropdownOtherValue) === '') {
                    $dropdownOtherValue = \App\Models\FormField::isOtherResponse($columnValue)
                        ? \App\Models\FormField::extractOtherResponse($columnValue)
                        : '';
                }
                $dropdownOtherSelected = $columnValue === \App\Models\FormField::OTHER_OPTION_VALUE
                    || \App\Models\FormField::isOtherResponse($columnValue);
                $dropdownOtherFieldDisabledAttr = (!$dropdownOtherSelected) ? 'disabled' : $columnDisabledAttr;
            @endphp
            <input
                type="text"
                name="{{ $baseName }}[{{ $otherInputKey }}]"
                id="{{ "{$field->id}_{$rowIndex}_{$columnKey}_other" }}"
                value="{{ $dropdownOtherValue }}"
                class="form-control form-control-sm mt-2 {{ $isInvalid ? 'is-invalid' : '' }}"
                placeholder="Please specify"
                data-other-label="{{ $column['other_label'] }}"
                data-other-input-field
                {{ $dropdownOtherFieldDisabledAttr }}
            >
        @endif
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
            @if(!empty($column['allow_custom_answer']))
                @php
                    $radioOtherInputId = "{$field->id}_{$rowIndex}_{$columnKey}_other";
                    $radioOtherValue = $rowValues[$otherInputKey] ?? null;
                    if (!is_string($radioOtherValue) || trim($radioOtherValue) === '') {
                        $radioOtherValue = \App\Models\FormField::isOtherResponse($columnValue)
                            ? \App\Models\FormField::extractOtherResponse($columnValue)
                            : '';
                    }
                    $radioOtherChecked = $columnValue === \App\Models\FormField::OTHER_OPTION_VALUE
                        || \App\Models\FormField::isOtherResponse($columnValue);
                    $radioOtherFieldDisabledAttr = (!$radioOtherChecked) ? 'disabled' : $columnDisabledAttr;
                @endphp
                <div class="form-check" data-other-option>
                    <input
                        class="form-check-input {{ $isInvalid ? 'is-invalid' : '' }}"
                        type="radio"
                        name="{{ $baseName }}[{{ $columnKey }}]"
                        value="{{ \App\Models\FormField::OTHER_OPTION_VALUE }}"
                        id="{{ $radioOtherInputId }}_toggle"
                        data-other-toggle
                        data-other-input-id="{{ $radioOtherInputId }}"
                        {{ $radioOtherChecked ? 'checked' : '' }}
                        {{ $columnDisabledAttr }}
                    >
                    <label class="form-check-label small" for="{{ $radioOtherInputId }}_toggle">{{ $column['other_label'] }}</label>
                    <input
                        type="text"
                        name="{{ $baseName }}[{{ $otherInputKey }}]"
                        id="{{ $radioOtherInputId }}"
                        value="{{ $radioOtherValue }}"
                        class="form-control form-control-sm mt-2 {{ $isInvalid ? 'is-invalid' : '' }}"
                        placeholder="Please specify"
                        data-other-label="{{ $column['other_label'] }}"
                        data-other-input-field
                        {{ $radioOtherFieldDisabledAttr }}
                    >
                </div>
            @endif
        </div>
        @break

    @case('checkbox')
        @php
            $checkboxValues = collect((array) $columnValue);
            $checkboxOtherValue = $rowValues[$otherInputKey] ?? null;

            if (!is_string($checkboxOtherValue) || trim($checkboxOtherValue) === '') {
                $storedOtherValue = $checkboxValues->first(fn ($value) => \App\Models\FormField::isOtherResponse($value));
                $checkboxOtherValue = \App\Models\FormField::extractOtherResponse($storedOtherValue);
            }

            $otherChecked = $checkboxValues->contains(\App\Models\FormField::OTHER_OPTION_VALUE)
                || $checkboxValues->contains(fn ($value) => \App\Models\FormField::isOtherResponse($value));
            $otherFieldDisabledAttr = (!$otherChecked) ? 'disabled' : $columnDisabledAttr;
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
                        value="{{ is_string($checkboxOtherValue) ? $checkboxOtherValue : '' }}"
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
