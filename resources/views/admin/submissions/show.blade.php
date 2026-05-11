@extends('layouts.admin')

@section('title', 'Submission #' . $submission->id)
@section('page-title', 'Submission #' . $submission->id)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> Submission #{{ $submission->id }}</h5>
                <a href="{{ route('admin.forms.submissions.index', $form) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="mb-3 text-muted small">
                    <i class="bi bi-clock"></i> Submitted {{ $submission->created_at->format('M d, Y H:i:s') }}
                    &nbsp;|&nbsp;
                    <i class="bi bi-geo"></i> {{ $submission->ip_address }}
                </div>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr><th style="width:30%">Field</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        @foreach($form->fields as $field)
                        @if(!in_array($field->type, ['section', 'label'], true))
                        <tr>
                            <td class="fw-semibold">{{ $field->label }}</td>
                            <td>
                                @php $val = $submission->data[$field->name] ?? null; @endphp
                                @if($field->type === 'table')
                                    @include('admin.submissions.partials.table-value', ['field' => $field, 'value' => $val])
                                @else
                                    {{ $field->formatSubmissionValue($val) }}
                                @endif
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('admin.forms.submissions.destroy', [$form, $submission]) }}" onsubmit="return confirm('Delete this submission?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i> Delete Submission
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
