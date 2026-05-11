@php
    $selectedType = old('type', $field->type ?? 'text');
    $fieldConfig = isset($field) && is_array($field->config) ? $field->config : [];
    $tableColumns = old('config.columns', $fieldConfig['columns'] ?? []);
    $tableColumns = is_array($tableColumns) ? $tableColumns : [];
    $customAnswerEnabled = old('allow_custom_answer', isset($field) ? $field->hasOtherOption() : false);
    $customAnswerLabel = old('other_label', isset($field) ? $field->other_label : \App\Models\FormField::DEFAULT_OTHER_LABEL);
    $visibilityRule = old('visibility', $fieldConfig['visibility'] ?? []);
    $visibilityEnabled = old('visibility.enabled', !empty($visibilityRule['enabled']));
    $visibilityFieldId = old('visibility.field_id', $visibilityRule['field_id'] ?? '');
    $visibilityOperator = old('visibility.operator', $visibilityRule['operator'] ?? 'equals');
    $visibilityValue = old('visibility.value', $visibilityRule['value'] ?? '');
    $emptyCheckOperators = ['is_empty', 'is_not_empty'];
@endphp

<div class="mb-3" id="labelGroup">
    <label class="form-label fw-semibold" id="labelText">Label <span class="text-danger">*</span></label>
    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
           value="{{ old('label', $field->label ?? '') }}" required placeholder="e.g. Your Name">
    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Field Type <span class="text-danger">*</span></label>
    <select name="type" id="fieldType" class="form-select @error('type') is-invalid @enderror">
        @foreach([
            'text' => 'Text',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'number' => 'Number',
            'textarea' => 'Text Area',
            'dropdown' => 'Dropdown',
            'radio' => 'Radio Buttons',
            'checkbox' => 'Checkboxes',
            'checkbox_dropdown' => 'Checkbox Dropdown',
            'table' => 'Table / Repeatable Group',
            'section' => 'Section Divider',
            'label' => 'Label',
        ] as $value => $typeLabel)
            <option value="{{ $value }}" {{ $selectedType == $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3" id="optionsGroup" style="{{ in_array($selectedType, ['dropdown','radio','checkbox','checkbox_dropdown'], true) ? '' : 'display:none' }}">
    <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
    <textarea name="options" class="form-control" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options', isset($field) ? implode("\n", $field->selectable_options) : '') }}</textarea>
</div>

<div class="mb-3" id="customAnswerGroup" style="{{ old('type', $field->type ?? 'text') === 'checkbox' ? '' : 'display:none' }}">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="allow_custom_answer" id="allow_custom_answer" value="1"
            {{ $customAnswerEnabled ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="allow_custom_answer">Allow custom answer</label>
    </div>
    <div class="mt-3" id="customAnswerLabelGroup" style="{{ $customAnswerEnabled ? '' : 'display:none' }}">
        <label class="form-label fw-semibold" for="other_label">Custom answer label</label>
        <input type="text" name="other_label" id="other_label" class="form-control @error('other_label') is-invalid @enderror"
               value="{{ $customAnswerLabel }}"
               placeholder="e.g. Other, Lainnya, Please specify...">
        @error('other_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-text">Adds a customizable checkbox option with a free-text answer on the public form.</div>
</div>

<div id="tableConfigGroup" class="border rounded p-3 bg-light-subtle mb-3" style="{{ $selectedType === 'table' ? '' : 'display:none' }}">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h6 class="mb-1">Table Columns</h6>
            <p class="text-muted small mb-0">Add, remove, and reorder the columns that will appear in each repeatable row.</p>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addTableColumn">
            <i class="bi bi-plus-circle"></i> Add Column
        </button>
    </div>

    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="config[auto_number]" id="tableAutoNumber" value="1"
               {{ old('config.auto_number', $field->table_auto_number ?? false) ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="tableAutoNumber">Show auto-number column</label>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold" for="tableMaxRows">Max Rows</label>
        <input type="number" name="config[max_rows]" id="tableMaxRows" class="form-control @error('config.max_rows') is-invalid @enderror"
               min="0" value="{{ old('config.max_rows', $field->table_max_rows ?? '') }}"
               placeholder="Leave blank or 0 for unlimited">
        @error('config.max_rows')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Maximum number of rows a user can add. Leave blank or set to 0 for unlimited.</div>
    </div>

    @error('config.columns')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <div id="tableColumnsContainer" class="d-flex flex-column gap-3">
        @foreach($tableColumns as $index => $column)
            <div class="card shadow-sm table-column-item">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="mb-0">Column <span data-column-number>{{ $loop->iteration }}</span></h6>
                            <small class="text-muted" data-column-key-preview>{{ $column['key'] ?? '' }}</small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary js-column-move-up"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn btn-outline-secondary js-column-move-down"><i class="bi bi-arrow-down"></i></button>
                            <button type="button" class="btn btn-outline-danger js-remove-column"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>

                    <input type="hidden" name="config[columns][{{ $index }}][key]" value="{{ $column['key'] ?? '' }}" data-column-key>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Column Label <span class="text-danger">*</span></label>
                            <input type="text" name="config[columns][{{ $index }}][label]" value="{{ $column['label'] ?? '' }}"
                                   class="form-control @error("config.columns.$index.label") is-invalid @enderror" data-column-label>
                            @error("config.columns.$index.label")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Column Type <span class="text-danger">*</span></label>
                            <select name="config[columns][{{ $index }}][type]" class="form-select @error("config.columns.$index.type") is-invalid @enderror" data-column-type>
                                @foreach(['text' => 'Text', 'email' => 'Email', 'phone' => 'Phone Number', 'number' => 'Number', 'textarea' => 'Text Area', 'dropdown' => 'Dropdown', 'radio' => 'Radio Buttons', 'checkbox' => 'Checkboxes', 'checkbox_dropdown' => 'Checkbox Dropdown', 'label' => 'Label'] as $value => $typeLabel)
                                    <option value="{{ $value }}" {{ ($column['type'] ?? 'text') === $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            @error("config.columns.$index.type")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 column-required-group" style="{{ ($column['type'] ?? 'text') === 'label' ? 'display:none' : '' }}">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="config[columns][{{ $index }}][required]" value="1"
                                       {{ !empty($column['required']) ? 'checked' : '' }}>
                                <label class="form-check-label">Required column</label>
                            </div>
                        </div>
                        <div class="col-12 column-options-group" style="{{ in_array($column['type'] ?? 'text', ['dropdown', 'radio', 'checkbox', 'checkbox_dropdown'], true) ? '' : 'display:none' }}">
                            <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
                            <textarea name="config[columns][{{ $index }}][options]" rows="3" class="form-control">{{ is_array($column['options'] ?? []) ? implode("\n", $column['options'] ?? []) : ($column['options'] ?? '') }}</textarea>
                        </div>
                        <div class="col-12 column-custom-answer-group" style="{{ in_array($column['type'] ?? 'text', ['checkbox', 'radio', 'dropdown'], true) ? '' : 'display:none' }}">
                            @php
                                $columnCustomAnswerEnabled = !empty($column['allow_custom_answer']);
                                $columnOtherLabel = \App\Models\FormField::normalizeOtherLabel($column['other_label'] ?? null);
                            @endphp
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="config[columns][{{ $index }}][allow_custom_answer]" value="1"
                                       data-column-custom-answer {{ $columnCustomAnswerEnabled ? 'checked' : '' }}>
                                <label class="form-check-label">Allow custom answer</label>
                            </div>
                            <div class="column-other-label-group" style="{{ $columnCustomAnswerEnabled ? '' : 'display:none' }}">
                                <label class="form-label fw-semibold">Custom answer label</label>
                                <input type="text" name="config[columns][{{ $index }}][other_label]" value="{{ $columnOtherLabel }}"
                                       class="form-control @error("config.columns.$index.other_label") is-invalid @enderror"
                                       placeholder="e.g. Other, Lainnya, Please specify...">
                                @error("config.columns.$index.other_label")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @php
                            $columnVisibilityRule = is_array($column['visibility'] ?? null) ? $column['visibility'] : [];
                            $columnVisibilityEnabled = !empty($columnVisibilityRule['enabled']);
                            $columnVisibilityField = $columnVisibilityRule['field'] ?? '';
                            $columnVisibilityOperator = $columnVisibilityRule['operator'] ?? 'equals';
                            $columnVisibilityValue = $columnVisibilityRule['value'] ?? '';
                        @endphp
                        <div class="col-12 column-visibility-group border rounded p-3 bg-white">
                            <h6 class="mb-3">Conditional Visibility</h6>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="config[columns][{{ $index }}][visibility][enabled]" value="1"
                                       data-column-visibility-enabled {{ $columnVisibilityEnabled ? 'checked' : '' }}>
                                <label class="form-check-label">Enable conditional visibility</label>
                            </div>
                            <div class="column-visibility-details" style="{{ $columnVisibilityEnabled ? '' : 'display:none' }}">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Controlling column</label>
                                    <select name="config[columns][{{ $index }}][visibility][field]"
                                            class="form-select"
                                            data-column-visibility-field
                                            data-selected-value="{{ $columnVisibilityField }}">
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Operator</label>
                                    <select name="config[columns][{{ $index }}][visibility][operator]" class="form-select" data-column-visibility-operator>
                                        <option value="equals" {{ $columnVisibilityOperator === 'equals' ? 'selected' : '' }}>equals</option>
                                        <option value="not_equals" {{ $columnVisibilityOperator === 'not_equals' ? 'selected' : '' }}>not equals</option>
                                        <option value="is_empty" {{ $columnVisibilityOperator === 'is_empty' ? 'selected' : '' }}>is empty</option>
                                        <option value="is_not_empty" {{ $columnVisibilityOperator === 'is_not_empty' ? 'selected' : '' }}>is not empty</option>
                                    </select>
                                </div>
                                <div class="column-visibility-value-group" style="{{ in_array($columnVisibilityOperator, $emptyCheckOperators, true) ? 'display:none' : '' }}">
                                    <label class="form-label fw-semibold">Expected value</label>
                                    <input type="text" name="config[columns][{{ $index }}][visibility][value]" class="form-control"
                                           value="{{ $columnVisibilityValue }}" data-column-visibility-value placeholder="e.g. yes">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <template id="tableColumnTemplate">
        <div class="card shadow-sm table-column-item">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-0">Column <span data-column-number></span></h6>
                        <small class="text-muted" data-column-key-preview></small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary js-column-move-up"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary js-column-move-down"><i class="bi bi-arrow-down"></i></button>
                        <button type="button" class="btn btn-outline-danger js-remove-column"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <input type="hidden" data-name-template="config[columns][__INDEX__][key]" data-column-key>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Column Label <span class="text-danger">*</span></label>
                        <input type="text" data-name-template="config[columns][__INDEX__][label]" class="form-control" data-column-label>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Column Type <span class="text-danger">*</span></label>
                        <select data-name-template="config[columns][__INDEX__][type]" class="form-select" data-column-type>
                            @foreach(['text' => 'Text', 'email' => 'Email', 'phone' => 'Phone Number', 'number' => 'Number', 'textarea' => 'Text Area', 'dropdown' => 'Dropdown', 'radio' => 'Radio Buttons', 'checkbox' => 'Checkboxes', 'checkbox_dropdown' => 'Checkbox Dropdown', 'label' => 'Label'] as $value => $typeLabel)
                                <option value="{{ $value }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 column-required-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" value="1" data-name-template="config[columns][__INDEX__][required]">
                            <label class="form-check-label">Required column</label>
                        </div>
                    </div>
                    <div class="col-12 column-options-group" style="display:none">
                        <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
                        <textarea rows="3" class="form-control" data-name-template="config[columns][__INDEX__][options]"></textarea>
                    </div>
                    <div class="col-12 column-custom-answer-group" style="display:none">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" value="1" data-name-template="config[columns][__INDEX__][allow_custom_answer]" data-column-custom-answer>
                            <label class="form-check-label">Allow custom answer</label>
                        </div>
                        <div class="column-other-label-group" style="display:none">
                            <label class="form-label fw-semibold">Custom answer label</label>
                            <input type="text" class="form-control" value="{{ \App\Models\FormField::DEFAULT_OTHER_LABEL }}"
                                   placeholder="e.g. Other, Lainnya, Please specify..."
                                   data-name-template="config[columns][__INDEX__][other_label]">
                        </div>
                    </div>
                    <div class="col-12 column-visibility-group border rounded p-3 bg-white">
                        <h6 class="mb-3">Conditional Visibility</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" value="1" data-name-template="config[columns][__INDEX__][visibility][enabled]" data-column-visibility-enabled>
                            <label class="form-check-label">Enable conditional visibility</label>
                        </div>
                        <div class="column-visibility-details" style="display:none">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Controlling column</label>
                                <select class="form-select" data-name-template="config[columns][__INDEX__][visibility][field]" data-column-visibility-field></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Operator</label>
                                <select class="form-select" data-name-template="config[columns][__INDEX__][visibility][operator]" data-column-visibility-operator>
                                    <option value="equals">equals</option>
                                    <option value="not_equals">not equals</option>
                                    <option value="is_empty">is empty</option>
                                    <option value="is_not_empty">is not empty</option>
                                </select>
                            </div>
                            <div class="column-visibility-value-group">
                                <label class="form-label fw-semibold">Expected value</label>
                                <input type="text" class="form-control" placeholder="e.g. yes"
                                       data-name-template="config[columns][__INDEX__][visibility][value]"
                                       data-column-visibility-value>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<div class="mb-3" id="placeholderGroup">
    <label class="form-label fw-semibold" id="placeholderLabel">Placeholder</label>
    <input type="text" name="placeholder" id="placeholderInput" class="form-control"
           value="{{ old('placeholder', $field->placeholder ?? '') }}" placeholder="e.g. Enter your name...">
</div>

<div id="inputOnlyFields" style="{{ in_array($selectedType, ['section', 'table', 'label'], true) ? 'display:none' : '' }}">
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

<div class="border rounded p-3 bg-light-subtle mb-3" id="visibilityConfigGroup" style="{{ in_array($selectedType, ['section', 'table', 'label'], true) ? 'display:none' : '' }}">
    <h6 class="mb-3">Conditional Visibility</h6>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="visibility[enabled]" id="visibility_enabled" value="1"
               {{ $visibilityEnabled ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="visibility_enabled">Enable conditional visibility</label>
    </div>

    <div id="visibilityDetailsGroup" style="{{ $visibilityEnabled ? '' : 'display:none' }}">
        <div class="mb-3">
            <label class="form-label fw-semibold" for="visibility_field_id">Controlling field</label>
            <select name="visibility[field_id]" id="visibility_field_id" class="form-select @error('visibility.field_id') is-invalid @enderror">
                <option value="">Select a field</option>
                @foreach($visibilityControllerFields as $controllerField)
                    <option value="{{ $controllerField->id }}" {{ (string) $visibilityFieldId === (string) $controllerField->id ? 'selected' : '' }}>
                        {{ $controllerField->label }} ({{ $controllerField->name }})
                    </option>
                @endforeach
            </select>
            @error('visibility.field_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold" for="visibility_operator">Operator</label>
            <select name="visibility[operator]" id="visibility_operator" class="form-select @error('visibility.operator') is-invalid @enderror">
                <option value="equals" {{ $visibilityOperator === 'equals' ? 'selected' : '' }}>equals</option>
                <option value="not_equals" {{ $visibilityOperator === 'not_equals' ? 'selected' : '' }}>not equals</option>
                <option value="is_empty" {{ $visibilityOperator === 'is_empty' ? 'selected' : '' }}>is empty</option>
                <option value="is_not_empty" {{ $visibilityOperator === 'is_not_empty' ? 'selected' : '' }}>is not empty</option>
            </select>
            @error('visibility.operator')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div id="visibilityValueGroup" style="{{ in_array($visibilityOperator, $emptyCheckOperators, true) ? 'display:none' : '' }}">
            <label class="form-label fw-semibold" for="visibility_value">Expected value</label>
            <input type="text" name="visibility[value]" id="visibility_value" class="form-control @error('visibility.value') is-invalid @enderror"
                   value="{{ $visibilityValue }}" placeholder="e.g. employed">
            @error('visibility.value')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var fieldType = document.getElementById('fieldType');
    var optionsGroup = document.getElementById('optionsGroup');
    var customAnswerGroup = document.getElementById('customAnswerGroup');
    var customAnswerLabelGroup = document.getElementById('customAnswerLabelGroup');
    var allowCustomAnswer = document.getElementById('allow_custom_answer');
    var inputOnlyFields = document.getElementById('inputOnlyFields');
    var tableConfigGroup = document.getElementById('tableConfigGroup');
    var visibilityConfigGroup = document.getElementById('visibilityConfigGroup');
    var visibilityEnabled = document.getElementById('visibility_enabled');
    var visibilityDetailsGroup = document.getElementById('visibilityDetailsGroup');
    var visibilityOperator = document.getElementById('visibility_operator');
    var visibilityValueGroup = document.getElementById('visibilityValueGroup');
    var placeholderGroup = document.getElementById('placeholderGroup');
    var placeholderLabel = document.getElementById('placeholderLabel');
    var placeholderInput = document.getElementById('placeholderInput');
    var labelText = document.getElementById('labelText');
    var columnsContainer = document.getElementById('tableColumnsContainer');
    var addTableColumnButton = document.getElementById('addTableColumn');
    var tableColumnTemplate = document.getElementById('tableColumnTemplate');
    var emptyCheckOperators = @js($emptyCheckOperators);

    function toSnakeCase(value) {
        return value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function updateColumnOptions(columnItem) {
        var typeSelect = columnItem.querySelector('[data-column-type]');
        var requiredGroup = columnItem.querySelector('.column-required-group');
        var optionsGroup = columnItem.querySelector('.column-options-group');
        var customAnswerGroup = columnItem.querySelector('.column-custom-answer-group');
        var customAnswerToggle = columnItem.querySelector('[data-column-custom-answer]');
        var otherLabelGroup = columnItem.querySelector('.column-other-label-group');
        if (!typeSelect || !optionsGroup) {
            return;
        }

        var showOptions = ['dropdown', 'radio', 'checkbox', 'checkbox_dropdown'].includes(typeSelect.value);
        var showCustomAnswer = ['dropdown', 'radio', 'checkbox'].includes(typeSelect.value);
        if (requiredGroup) {
            requiredGroup.style.display = typeSelect.value === 'label' ? 'none' : '';
        }
        optionsGroup.style.display = showOptions ? '' : 'none';
        if (customAnswerGroup) {
            customAnswerGroup.style.display = showCustomAnswer ? '' : 'none';
        }
        if (otherLabelGroup) {
            otherLabelGroup.style.display = showCustomAnswer && customAnswerToggle && customAnswerToggle.checked ? '' : 'none';
        }
    }

    function updateVisibilityValueGroup(operatorElement, valueGroupElement) {
        if (!operatorElement || !valueGroupElement) {
            return;
        }

        valueGroupElement.style.display = emptyCheckOperators.includes(operatorElement.value) ? 'none' : '';
    }

    function updateColumnVisibilityState(columnItem) {
        var enabledToggle = columnItem.querySelector('[data-column-visibility-enabled]');
        var detailsGroup = columnItem.querySelector('.column-visibility-details');
        var operatorElement = columnItem.querySelector('[data-column-visibility-operator]');
        var valueGroupElement = columnItem.querySelector('.column-visibility-value-group');

        if (detailsGroup) {
            detailsGroup.style.display = enabledToggle && enabledToggle.checked ? '' : 'none';
        }

        updateVisibilityValueGroup(operatorElement, valueGroupElement);
    }

    function refreshColumnVisibilityOptions() {
        var columnItems = Array.from(columnsContainer.querySelectorAll('.table-column-item'));

        columnItems.forEach(function(columnItem) {
            var currentKeyInput = columnItem.querySelector('[data-column-key]');
            var currentKey = currentKeyInput ? currentKeyInput.value : '';
            var select = columnItem.querySelector('[data-column-visibility-field]');

            if (!select) {
                return;
            }

            var selectedValue = select.value || select.dataset.selectedValue || '';
            var options = ['<option value="">Select a column</option>'];

            columnItems.forEach(function(item) {
                var keyInput = item.querySelector('[data-column-key]');
                var labelInput = item.querySelector('[data-column-label]');
                var key = keyInput ? keyInput.value : '';

                if (!key || key === currentKey) {
                    return;
                }

                var label = labelInput && labelInput.value ? labelInput.value : key;
                options.push('<option value="' + key + '">' + label + ' (' + key + ')</option>');
            });

            select.innerHTML = options.join('');
            if (selectedValue && select.querySelector('option[value="' + selectedValue + '"]')) {
                select.value = selectedValue;
            } else {
                select.value = '';
            }

            select.dataset.selectedValue = select.value;
        });
    }

    function updateColumnCard(columnItem, index) {
        columnItem.querySelectorAll('[data-name-template]').forEach(function(element) {
            element.name = element.dataset.nameTemplate.replace(/__INDEX__/g, index);
        });

        var numberLabel = columnItem.querySelector('[data-column-number]');
        if (numberLabel) {
            numberLabel.textContent = index + 1;
        }

        var labelInput = columnItem.querySelector('[data-column-label]');
        var keyInput = columnItem.querySelector('[data-column-key]');
        var keyPreview = columnItem.querySelector('[data-column-key-preview]');
        var generatedKey = toSnakeCase(labelInput ? labelInput.value : '');

        if (keyInput && !keyInput.value) {
            keyInput.value = generatedKey;
        }

        if (keyInput && labelInput) {
            keyInput.dataset.previousLabel = labelInput.value;
        }

        if (keyPreview) {
            keyPreview.textContent = (keyInput && keyInput.value) ? 'Key: ' + keyInput.value : 'Key will be generated from the label';
        }

        updateColumnOptions(columnItem);
        updateColumnVisibilityState(columnItem);
    }

    function refreshColumnIndexes() {
        Array.from(columnsContainer.querySelectorAll('.table-column-item')).forEach(function(columnItem, index) {
            updateColumnCard(columnItem, index);
        });
        refreshColumnVisibilityOptions();
    }

    function shouldRegenerateKey(keyInput, previousLabel) {
        return keyInput && (!keyInput.value || keyInput.value === toSnakeCase(previousLabel || ''));
    }

    function createColumn() {
        if (!tableColumnTemplate) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = tableColumnTemplate.innerHTML.trim();
        var columnItem = wrapper.firstElementChild;
        columnsContainer.appendChild(columnItem);
        refreshColumnIndexes();
    }

    function updateFieldVisibility(type) {
        var showOptions = ['dropdown', 'radio', 'checkbox', 'checkbox_dropdown'].includes(type);
        var isCheckbox = type === 'checkbox';
        var isSection = type === 'section';
        var isTable = type === 'table';
        var isLabel = type === 'label';

        optionsGroup.style.display = showOptions ? '' : 'none';
        customAnswerGroup.style.display = isCheckbox ? '' : 'none';
        if (customAnswerLabelGroup) {
            customAnswerLabelGroup.style.display = isCheckbox && allowCustomAnswer && allowCustomAnswer.checked ? '' : 'none';
        }
        inputOnlyFields.style.display = (isSection || isTable || isLabel) ? 'none' : '';
        visibilityConfigGroup.style.display = (isSection || isTable || isLabel) ? 'none' : '';

        if (isSection) {
            labelText.innerHTML = 'Section Title <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Section Description';
            placeholderInput.placeholder = 'e.g. Please fill in your personal details (optional)';
        } else if (isLabel) {
            labelText.innerHTML = 'Label Text <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Help Text';
            placeholderInput.placeholder = 'e.g. Additional instructions (optional)';
        } else if (isTable) {
            labelText.innerHTML = 'Table Label <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Placeholder';
            placeholderInput.placeholder = '';
        } else {
            labelText.innerHTML = 'Label <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Placeholder';
            placeholderInput.placeholder = 'e.g. Enter your name...';
        }

        if (isTable && columnsContainer.children.length === 0) {
            createColumn();
        }
    }

    fieldType.addEventListener('change', function() {
        updateFieldVisibility(this.value);
    });

    if (allowCustomAnswer) {
        allowCustomAnswer.addEventListener('change', function() {
            if (customAnswerLabelGroup) {
                customAnswerLabelGroup.style.display = this.checked ? '' : 'none';
            }
        });
    }

    if (visibilityEnabled) {
        visibilityEnabled.addEventListener('change', function() {
            if (visibilityDetailsGroup) {
                visibilityDetailsGroup.style.display = this.checked ? '' : 'none';
            }
        });
    }

    if (visibilityOperator) {
        visibilityOperator.addEventListener('change', function() {
            updateVisibilityValueGroup(visibilityOperator, visibilityValueGroup);
        });
    }

    if (addTableColumnButton) {
        addTableColumnButton.addEventListener('click', createColumn);
    }

    if (columnsContainer) {
        columnsContainer.addEventListener('click', function(event) {
            var columnItem = event.target.closest('.table-column-item');
            if (!columnItem) {
                return;
            }

            if (event.target.closest('.js-remove-column')) {
                columnItem.remove();
                refreshColumnIndexes();
                return;
            }

            if (event.target.closest('.js-column-move-up') && columnItem.previousElementSibling) {
                columnsContainer.insertBefore(columnItem, columnItem.previousElementSibling);
                refreshColumnIndexes();
                return;
            }

            if (event.target.closest('.js-column-move-down') && columnItem.nextElementSibling) {
                columnsContainer.insertBefore(columnItem.nextElementSibling, columnItem);
                refreshColumnIndexes();
            }
        });

        columnsContainer.addEventListener('input', function(event) {
            var columnItem = event.target.closest('.table-column-item');
            if (!columnItem) {
                return;
            }

            if (event.target.matches('[data-column-label]')) {
                var keyInput = columnItem.querySelector('[data-column-key]');
                if (shouldRegenerateKey(keyInput, keyInput ? keyInput.dataset.previousLabel : '')) {
                    keyInput.value = toSnakeCase(event.target.value);
                }
                keyInput.dataset.previousLabel = event.target.value;
            }

            refreshColumnIndexes();
        });

        columnsContainer.addEventListener('change', function(event) {
            var columnItem = event.target.closest('.table-column-item');
            if (columnItem && (event.target.matches('[data-column-type]') || event.target.matches('[data-column-custom-answer]'))) {
                updateColumnOptions(columnItem);
            }

            if (columnItem && event.target.matches('[data-column-visibility-enabled], [data-column-visibility-operator], [data-column-visibility-field]')) {
                if (event.target.matches('[data-column-visibility-field]')) {
                    event.target.dataset.selectedValue = event.target.value;
                }
                updateColumnVisibilityState(columnItem);
            }
        });
    }

    refreshColumnIndexes();
    updateVisibilityValueGroup(visibilityOperator, visibilityValueGroup);
    updateFieldVisibility(fieldType.value);
})();
</script>
@endpush
