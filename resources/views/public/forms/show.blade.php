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

    <form method="POST" action="{{ route('forms.submit', $form) }}" id="public-form" data-form-validation novalidate>
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
        @else
        @php
            $fieldError = $errors->first($field->name);
            $otherFieldError = $field->type === 'checkbox' ? $errors->first($field->other_input_name) : null;
        @endphp
        <div class="mb-3 form-field" data-field-type="{{ $field->type }}" data-field-name="{{ $field->name }}" data-label="{{ $field->label }}" data-required="{{ $field->required ? 'true' : 'false' }}">
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
                        {{ $field->required ? 'required' : '' }}>{{ old($field->name, $field->default_value) }}</textarea>
                    @break
                @case('dropdown')
                    <select name="{{ $field->name }}" id="{{ $field->name }}" class="form-select @error($field->name) is-invalid @enderror" {{ $field->required ? 'required' : '' }}>
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
                    @php
                        $oldCheckboxValues = collect((array) old($field->name, []));
                        $oldOtherValue = old($field->other_input_name);

                        if (!$oldOtherValue) {
                            $storedOtherValue = $oldCheckboxValues->first(fn ($value) => is_string($value) && str_starts_with($value, 'other:'));
                            $oldOtherValue = $storedOtherValue ? substr($storedOtherValue, 6) : '';
                        }
                    @endphp
                    @foreach($field->selectable_options as $option)
                        <div class="form-check">
                            <input class="form-check-input @if($fieldError) is-invalid @endif" type="checkbox"
                                   name="{{ $field->name }}[]" value="{{ $option }}"
                                   id="{{ $field->name }}_{{ $loop->index }}"
                                   {{ $oldCheckboxValues->contains($option) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $field->name }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                    @if($field->hasOtherOption())
                        @php
                            $otherChecked = $oldCheckboxValues->contains(App\Models\FormField::OTHER_OPTION_VALUE)
                                || $oldCheckboxValues->contains(fn ($value) => is_string($value) && str_starts_with($value, 'other:'));
                        @endphp
                        <div class="form-check" data-other-option>
                            <input class="form-check-input @if($fieldError || $otherFieldError) is-invalid @endif" type="checkbox"
                                   name="{{ $field->name }}[]" value="{{ App\Models\FormField::OTHER_OPTION_VALUE }}"
                                   id="{{ $field->name }}_other"
                                   data-other-toggle
                                   data-other-input="#{{ $field->other_input_name }}"
                                   {{ $otherChecked ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $field->name }}_other">Other</label>
                            <input type="text"
                                   name="{{ $field->other_input_name }}"
                                   id="{{ $field->other_input_name }}"
                                   value="{{ $oldOtherValue }}"
                                   class="form-control mt-2 @error($field->other_input_name) is-invalid @enderror"
                                   placeholder="Please specify"
                                   data-other-input-field
                                   {{ $otherChecked ? '' : 'disabled' }}>
                        </div>
                    @endif
                    @break
                @case('phone')
                    <input type="tel" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}>
                    @break
                @default
                    <input type="{{ $field->type }}" name="{{ $field->name }}" id="{{ $field->name }}"
                        class="form-control @error($field->name) is-invalid @enderror"
                        value="{{ old($field->name, $field->default_value) }}"
                        placeholder="{{ $field->placeholder }}"
                        {{ $field->required ? 'required' : '' }}>
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
