@extends('layouts.admin')

@section('title', 'Submissions')
@section('page-title', 'Submissions: ' . $form->name)

@section('content')
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-inbox"></i> Submissions
            <span class="badge bg-secondary ms-2">{{ $submissions->total() }}</span>
        </h5>
        <div class="d-flex gap-2">
            @if($submissions->total() > 0)
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('admin.forms.submissions.export', $form) }}" class="btn btn-success">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <a href="{{ route('admin.forms.submissions.export-excel', $form) }}" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </a>
                </div>
            @endif
            <a href="{{ route('admin.forms.show', $form) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Form
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        @if($submissions->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-3">No submissions yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            @foreach($form->fields as $field)
                                @if(!in_array($field->type, ['section', 'label'], true))
                                <th>{{ $field->label }}</th>
                                @endif
                            @endforeach
                            <th>IP</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $submission)
                        <tr>
                            <td class="text-muted">{{ $submission->id }}</td>
                            @foreach($form->fields as $field)
                                @if(!in_array($field->type, ['section', 'label'], true))
                                <td>
                                    @php $val = $submission->data[$field->name] ?? '-'; @endphp
                                    {{ $field->formatSubmissionValue($val) }}
                                </td>
                                @endif
                            @endforeach
                            <td class="text-muted small">{{ $submission->ip_address }}</td>
                            <td class="text-muted small">{{ $submission->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.forms.submissions.show', [$form, $submission]) }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.forms.submissions.destroy', [$form, $submission]) }}" onsubmit="return confirm('Delete this submission?')">
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
            <div class="p-3">{{ $submissions->links() }}</div>
        @endif
    </div>
</div>
@endsection
