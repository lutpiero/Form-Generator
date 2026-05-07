@extends('layouts.public')

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

    <form method="POST" action="{{ route('forms.submit', $form) }}">
        @csrf

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
        <div class="mb-3">
            <label class="form-label fw-semibold">
                {{ $field->label }}
                @if($field->required) <span class="text-danger">*</span> @endif
            </label>

            @switch($field->type)
                @case('textarea')
                    <textarea name="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        rows="4"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}>{{ old($field->name, $field->default_value) }}</textarea>
                    @break
                @case('dropdown')
                    <select name="{{ $field->name }}" class="form-select @error($field->name) is-invalid @enderror" {{ $field->required ? 'required' : '' }}>
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
                                   {{ $field->required ? 'required' : '' }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @break
                @case('checkbox')
                    @foreach($field->options_array as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="{{ $field->name }}[]" value="{{ $option }}"
                                   id="{{ $field->name }}_{{ $loop->index }}"
                                   {{ in_array($option, (array) old($field->name, [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @break
                @case('phone')
                    <input type="tel" name="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}>
                    @break
                @default
                    <input type="{{ $field->type }}" name="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}>
            @endswitch

            @error($field->name)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
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
            <div class="mb-3 p-3 bg-light rounded">
                <label class="form-label fw-semibold">
                    <i class="bi bi-shield-check"></i> CAPTCHA: {{ $captcha['question'] }}
                    <span class="text-danger">*</span>
                </label>
                <input type="number" name="captcha_answer"
                    class="form-control @error('captcha_answer') is-invalid @enderror"
                    placeholder="Enter the answer" required>
                @error('captcha_answer')
                    <div class="invalid-feedback">{{ $message }}</div>
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
@endsection

@push('scripts')
<script>
(function () {
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

    function cellHasValue(cell, type) {
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

    function validateRepeatableTables(form) {
        var valid = true;

        form.querySelectorAll('[data-repeatable-table]').forEach(function (tableWrapper) {
            var summaryError = tableWrapper.querySelector('[data-table-summary-error]');
            var tableValid = true;

            tableWrapper.querySelectorAll('[data-table-row]').forEach(function (row) {
                row.querySelectorAll('td[data-required="1"]').forEach(function (cell) {
                    clearCellValidation(cell);

                    if (!cellHasValue(cell, cell.dataset.columnType)) {
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

        tableWrapper.addEventListener('click', function (event) {
            var addButton = event.target.closest('.js-table-add-row');
            if (addButton && template && tbody) {
                var nextIndex = tbody.querySelectorAll('[data-table-row]').length;
                var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
                tbody.insertAdjacentHTML('beforeend', html);
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
            var cell = event.target.closest('td[data-required="1"]');
            if (cell) {
                clearCellValidation(cell);
                var summaryError = tableWrapper.querySelector('[data-table-summary-error]');
                if (summaryError) {
                    summaryError.classList.add('d-none');
                }
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
