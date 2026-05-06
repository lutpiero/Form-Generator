@extends('layouts.admin')

@section('title', 'Edit Field')
@section('page-title', 'Edit Field')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Edit Field: {{ $field->label }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.forms.fields.update', [$form, $field]) }}">
                    @csrf @method('PUT')
                    @include('admin.fields._form')
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Update Field
                        </button>
                        <a href="{{ route('admin.forms.show', $form) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
