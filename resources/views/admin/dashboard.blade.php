@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card text-center p-4">
            <div class="display-4 fw-bold text-primary">{{ $totalForms }}</div>
            <div class="text-muted mt-1"><i class="bi bi-file-earmark-text"></i> Total Forms</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-4">
            <div class="display-4 fw-bold text-success">{{ $activeForms }}</div>
            <div class="text-muted mt-1"><i class="bi bi-check-circle"></i> Active Forms</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-4">
            <div class="display-4 fw-bold text-info">{{ $totalSubmissions }}</div>
            <div class="text-muted mt-1"><i class="bi bi-inbox"></i> Total Submissions</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Submissions</h5>
        <a href="{{ route('admin.forms.index') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus"></i> New Form
        </a>
    </div>
    <div class="card-body p-0">
        @if($recentSubmissions->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-3">No submissions yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Form</th>
                            <th>Submitted</th>
                            <th>IP Address</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentSubmissions as $submission)
                        <tr>
                            <td>{{ $submission->form->name }}</td>
                            <td>{{ $submission->created_at->diffForHumans() }}</td>
                            <td>{{ $submission->ip_address }}</td>
                            <td>
                                <a href="{{ route('admin.forms.submissions.show', [$submission->form, $submission]) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
