@extends('layouts.public')

@section('title', $form->name)

@section('content')
<div class="form-card p-4 p-md-5">
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
