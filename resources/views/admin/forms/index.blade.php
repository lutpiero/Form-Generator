@extends('layouts.admin')

@section('title', 'Forms')
@section('page-title', 'Forms')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> All Forms</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cognitoImportModal">
                <i class="bi bi-cloud-download"></i> Import from Cognito Forms
            </button>
            <a href="{{ route('admin.forms.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus"></i> Create Form
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        @if($forms->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark display-4"></i>
                <p class="mt-3">No forms yet. <a href="{{ route('admin.forms.create') }}">Create your first form</a>.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>CAPTCHA</th>
                            <th>Submissions</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($forms as $form)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($form->header_image)
                                        <img src="{{ Storage::url($form->header_image) }}" alt=""
                                             class="rounded" style="width:40px;height:40px;object-fit:cover;flex-shrink:0;">
                                    @endif
                                    <a href="{{ route('admin.forms.show', $form) }}" class="text-decoration-none fw-semibold">{{ $form->name }}</a>
                                </div>
                            </td>
                            <td><code>{{ $form->slug }}</code></td>
                            <td>
                                @if($form->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($form->captcha_enabled)
                                    <span class="badge bg-info">{{ ucfirst($form->captcha_type) }}</span>
                                @else
                                    <span class="text-muted small">Off</span>
                                @endif
                            </td>
                            <td><a href="{{ route('admin.forms.submissions.index', $form) }}">{{ $form->submissions_count }}</a></td>
                            <td class="text-muted small">{{ $form->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.forms.show', $form) }}" class="btn btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.forms.destroy', $form) }}" onsubmit="return confirm('Delete this form?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $forms->links() }}</div>
        @endif
    </div>
</div>

<div class="modal fade" id="cognitoImportModal" tabindex="-1" aria-labelledby="cognitoImportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.forms.import.cognito') }}" id="cognito-import-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="cognitoImportModalLabel">Import from Cognito Forms</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="cognito-url" class="form-label">Paste your public Cognito Forms URL</label>
                    <input
                        type="url"
                        class="form-control @error('cognito_url') is-invalid @enderror"
                        id="cognito-url"
                        name="cognito_url"
                        value="{{ old('cognito_url') }}"
                        placeholder="https://www.cognitoforms.com/YourOrg/YourFormName"
                        required
                    >
                    @error('cognito_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="cognito-import-submit">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="cognito-import-spinner" role="status" aria-hidden="true"></span>
                        <span id="cognito-import-submit-text">Import</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('cognitoImportModal');
    var form = document.getElementById('cognito-import-form');
    var submitButton = document.getElementById('cognito-import-submit');
    var spinner = document.getElementById('cognito-import-spinner');
    var submitText = document.getElementById('cognito-import-submit-text');

    if (!modalElement || !form || !submitButton || !spinner || !submitText) {
        return;
    }

    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        spinner.classList.remove('d-none');
        submitText.textContent = 'Importing...';
    });

    @if($errors->has('cognito_url'))
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
    @endif
});
</script>
@endpush
