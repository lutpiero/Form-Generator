<div class="mb-3" id="labelGroup">
    <label class="form-label fw-semibold" id="labelText">Label <span class="text-danger">*</span></label>
    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
           value="{{ old('label', $field->label ?? '') }}" required placeholder="e.g. Your Name">
    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Field Type <span class="text-danger">*</span></label>
    <select name="type" id="fieldType" class="form-select @error('type') is-invalid @enderror">
        @foreach(['text' => 'Text', 'email' => 'Email', 'phone' => 'Phone Number', 'number' => 'Number', 'textarea' => 'Text Area', 'dropdown' => 'Dropdown', 'radio' => 'Radio Buttons', 'checkbox' => 'Checkboxes', 'section' => 'Section Divider'] as $value => $typeLabel)
            <option value="{{ $value }}" {{ old('type', $field->type ?? 'text') == $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3" id="optionsGroup" style="{{ in_array(old('type', $field->type ?? 'text'), ['dropdown','radio','checkbox']) ? '' : 'display:none' }}">
    <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
    <textarea name="options" class="form-control" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options', isset($field) ? implode("\n", $field->selectable_options) : '') }}</textarea>
</div>

<div class="mb-3" id="customAnswerGroup" style="{{ old('type', $field->type ?? 'text') === 'checkbox' ? '' : 'display:none' }}">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="allow_custom_answer" id="allow_custom_answer" value="1"
            {{ old('allow_custom_answer', isset($field) && $field->hasOtherOption() ? '1' : '0') == '1' ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="allow_custom_answer">Allow custom answer</label>
    </div>
    <div class="form-text">Adds an <strong>Other</strong> option with a free-text answer on the public form.</div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold" id="placeholderLabel">Placeholder</label>
    <input type="text" name="placeholder" id="placeholderInput" class="form-control"
           value="{{ old('placeholder', $field->placeholder ?? '') }}" placeholder="e.g. Enter your name...">
</div>

<div id="inputOnlyFields" style="{{ old('type', $field->type ?? 'text') === 'section' ? 'display:none' : '' }}">
    <div class="mb-3">
        <label class="form-label fw-semibold">Default Value</label>
        <input type="text" name="default_value" class="form-control"
               value="{{ old('default_value', $field->default_value ?? '') }}">
    </div>

    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="required" id="required" value="1"
            {{ old('required', isset($field) && $field->required ? '1' : '0') == '1' ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="required">Required field</label>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var fieldType = document.getElementById('fieldType');
    var optionsGroup = document.getElementById('optionsGroup');
    var customAnswerGroup = document.getElementById('customAnswerGroup');
    var inputOnlyFields = document.getElementById('inputOnlyFields');
    var placeholderLabel = document.getElementById('placeholderLabel');
    var placeholderInput = document.getElementById('placeholderInput');
    var labelText = document.getElementById('labelText');

    function updateFieldVisibility(type) {
        var showOptions = ['dropdown', 'radio', 'checkbox'].includes(type);
        var isCheckbox = type === 'checkbox';
        var isSection = type === 'section';

        optionsGroup.style.display = showOptions ? '' : 'none';
        customAnswerGroup.style.display = isCheckbox ? '' : 'none';
        inputOnlyFields.style.display = isSection ? 'none' : '';

        if (isSection) {
            labelText.innerHTML = 'Section Title <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Section Description';
            placeholderInput.placeholder = 'e.g. Please fill in your personal details (optional)';
        } else {
            labelText.innerHTML = 'Label <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Placeholder';
            placeholderInput.placeholder = 'e.g. Enter your name...';
        }
    }

    fieldType.addEventListener('change', function() {
        updateFieldVisibility(this.value);
    });

    // Initialize state on page load
    updateFieldVisibility(fieldType.value);
})();
</script>
@endpush
