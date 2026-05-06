@extends('layouts.admin')

@section('title', 'Forms')
@section('page-title', 'Forms')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> All Forms</h5>
        <a href="{{ route('admin.forms.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus"></i> Create Form
        </a>
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
@endsection
