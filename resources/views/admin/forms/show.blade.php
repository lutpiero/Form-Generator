@extends('layouts.admin')

@section('title', $form->name)
@section('page-title', $form->name)

@section('content')
<div class="row g-4">
    <!-- Form Info -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Form Details</h6>
                <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr><th>Status</th><td>
                        @if($form->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td></tr>
                    <tr><th>CAPTCHA</th><td>
                        @if($form->captcha_enabled)
                            <span class="badge bg-info">{{ ucfirst($form->captcha_type) }}</span>
                        @else
                            <span class="text-muted">Disabled</span>
                        @endif
                    </td></tr>
                    <tr><th>Fields</th><td>{{ $form->fields->count() }}</td></tr>
                    <tr><th>Submissions</th><td>
                        <a href="{{ route('admin.forms.submissions.index', $form) }}">
                            {{ $form->submissions->count() }}
                        </a>
                    </td></tr>
                    <tr><th>Slug</th><td><code>{{ $form->slug }}</code></td></tr>
                </table>
                @if($form->description)
                    <p class="text-muted small">{{ $form->description }}</p>
                @endif
                <hr>
                <div class="d-grid gap-2">
                    <a href="{{ route('forms.show', $form) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right"></i> View Public Form
                    </a>
                    <a href="{{ route('admin.forms.submissions.index', $form) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-inbox"></i> View Submissions
                    </a>
                    @if($form->submissions->count() > 0)
                    <a href="{{ route('admin.forms.submissions.export', $form) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Fields -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-list-ul"></i> Form Fields ({{ $form->fields->count() }})</h6>
                <a href="{{ route('admin.forms.fields.create', $form) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus"></i> Add Field
                </a>
            </div>
            <div class="card-body p-0">
                @if($form->fields->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-list-ul display-4"></i>
                        <p class="mt-3">No fields yet. <a href="{{ route('admin.forms.fields.create', $form) }}">Add your first field</a>.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Label</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="fields-table-body">
                                @foreach($form->fields as $field)
                                <tr data-field-id="{{ $field->id }}">
                                    <td class="text-muted" data-field-order-num>{{ $loop->iteration }}</td>
                                    <td>{{ $field->label }}</td>
                                    <td><span class="badge bg-secondary">{{ $field->type }}</span></td>
                                    <td>
                                        @if($field->required)
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                            <i class="bi bi-dash text-muted"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary js-field-move-up" title="Move up"><i class="bi bi-arrow-up"></i></button>
                                            <button type="button" class="btn btn-outline-secondary js-field-move-down" title="Move down"><i class="bi bi-arrow-down"></i></button>
                                            <a href="{{ route('admin.forms.fields.edit', [$form, $field]) }}" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.forms.fields.destroy', [$form, $field]) }}" onsubmit="return confirm('Delete this field?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
@push('scripts')
<script>
(function () {
    var tbody = document.getElementById('fields-table-body');
    if (!tbody) return;

    var reorderUrl = @js(route('admin.forms.fields.reorder', $form));
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    function getFieldOrder() {
        return Array.from(tbody.querySelectorAll('tr[data-field-id]'))
            .map(function (tr) { return parseInt(tr.dataset.fieldId, 10); });
    }

    function refreshRowNumbers() {
        Array.from(tbody.querySelectorAll('tr[data-field-id]')).forEach(function (tr, i) {
            var numCell = tr.querySelector('[data-field-order-num]');
            if (numCell) numCell.textContent = i + 1;
        });
    }

    function saveOrder() {
        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ fields: getFieldOrder() }),
        }).catch(function () {
            console.error('Failed to save field order. Please reload the page and try again.');
        });
    }

    tbody.addEventListener('click', function (event) {
        var upBtn = event.target.closest('.js-field-move-up');
        var downBtn = event.target.closest('.js-field-move-down');

        if (!upBtn && !downBtn) return;

        var row = (upBtn || downBtn).closest('tr[data-field-id]');
        if (!row) return;

        if (upBtn && row.previousElementSibling) {
            tbody.insertBefore(row, row.previousElementSibling);
        } else if (downBtn && row.nextElementSibling) {
            tbody.insertBefore(row.nextElementSibling, row);
        }

        refreshRowNumbers();
        saveOrder();
    });
})();
</script>
@endpush
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
