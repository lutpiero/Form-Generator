@php
    $selectedType = old('type', $field->type ?? 'text');
    $tableColumns = old('config.columns', $field->config['columns'] ?? []);
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
            'table' => 'Table / Repeatable Group',
            'section' => 'Section Divider',
        ] as $value => $typeLabel)
            <option value="{{ $value }}" {{ $selectedType == $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3" id="optionsGroup" style="{{ in_array($selectedType, ['dropdown','radio','checkbox'], true) ? '' : 'display:none' }}">
    <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
    <textarea name="options" class="form-control" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options', isset($field) ? implode("\n", $field->options_array) : '') }}</textarea>
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
                                @foreach(['text' => 'Text', 'email' => 'Email', 'phone' => 'Phone Number', 'number' => 'Number', 'textarea' => 'Text Area', 'dropdown' => 'Dropdown', 'radio' => 'Radio Buttons', 'checkbox' => 'Checkboxes'] as $value => $typeLabel)
                                    <option value="{{ $value }}" {{ ($column['type'] ?? 'text') === $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            @error("config.columns.$index.type")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="config[columns][{{ $index }}][required]" value="1"
                                       {{ !empty($column['required']) ? 'checked' : '' }}>
                                <label class="form-check-label">Required column</label>
                            </div>
                        </div>
                        <div class="col-12 column-options-group" style="{{ in_array($column['type'] ?? 'text', ['dropdown', 'radio', 'checkbox'], true) ? '' : 'display:none' }}">
                            <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
                            <textarea name="config[columns][{{ $index }}][options]" rows="3" class="form-control">{{ implode("\n", $column['options'] ?? []) }}</textarea>
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
                            @foreach(['text' => 'Text', 'email' => 'Email', 'phone' => 'Phone Number', 'number' => 'Number', 'textarea' => 'Text Area', 'dropdown' => 'Dropdown', 'radio' => 'Radio Buttons', 'checkbox' => 'Checkboxes'] as $value => $typeLabel)
                                <option value="{{ $value }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" value="1" data-name-template="config[columns][__INDEX__][required]">
                            <label class="form-check-label">Required column</label>
                        </div>
                    </div>
                    <div class="col-12 column-options-group" style="display:none">
                        <label class="form-label fw-semibold">Options <span class="text-muted small">(one per line)</span></label>
                        <textarea rows="3" class="form-control" data-name-template="config[columns][__INDEX__][options]"></textarea>
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

<div id="inputOnlyFields" style="{{ in_array($selectedType, ['section', 'table'], true) ? 'display:none' : '' }}">
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
    var inputOnlyFields = document.getElementById('inputOnlyFields');
    var tableConfigGroup = document.getElementById('tableConfigGroup');
    var placeholderGroup = document.getElementById('placeholderGroup');
    var placeholderLabel = document.getElementById('placeholderLabel');
    var placeholderInput = document.getElementById('placeholderInput');
    var labelText = document.getElementById('labelText');
    var columnsContainer = document.getElementById('tableColumnsContainer');
    var addTableColumnButton = document.getElementById('addTableColumn');
    var tableColumnTemplate = document.getElementById('tableColumnTemplate');

    function toSnakeCase(value) {
        return value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function updateColumnOptions(columnItem) {
        var typeSelect = columnItem.querySelector('[data-column-type]');
        var optionsGroup = columnItem.querySelector('.column-options-group');
        if (!typeSelect || !optionsGroup) {
            return;
        }

        var showOptions = ['dropdown', 'radio', 'checkbox'].includes(typeSelect.value);
        optionsGroup.style.display = showOptions ? '' : 'none';
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
    }

    function refreshColumnIndexes() {
        Array.from(columnsContainer.querySelectorAll('.table-column-item')).forEach(function(columnItem, index) {
            updateColumnCard(columnItem, index);
        });
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
        var showOptions = ['dropdown', 'radio', 'checkbox'].includes(type);
        var isSection = type === 'section';
        var isTable = type === 'table';

        optionsGroup.style.display = showOptions ? '' : 'none';
        inputOnlyFields.style.display = (isSection || isTable) ? 'none' : '';
        tableConfigGroup.style.display = isTable ? '' : 'none';
        placeholderGroup.style.display = isTable ? 'none' : '';

        if (isSection) {
            labelText.innerHTML = 'Section Title <span class="text-danger">*</span>';
            placeholderLabel.textContent = 'Section Description';
            placeholderInput.placeholder = 'e.g. Please fill in your personal details (optional)';
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
                if (keyInput && (!keyInput.value || keyInput.value === toSnakeCase(keyInput.dataset.previousLabel || ''))) {
                    keyInput.value = toSnakeCase(event.target.value);
                }
                keyInput.dataset.previousLabel = event.target.value;
            }

            refreshColumnIndexes();
        });

        columnsContainer.addEventListener('change', function(event) {
            var columnItem = event.target.closest('.table-column-item');
            if (columnItem && event.target.matches('[data-column-type]')) {
                updateColumnOptions(columnItem);
            }
        });
    }

    refreshColumnIndexes();
    updateFieldVisibility(fieldType.value);
})();
</script>
@endpush
