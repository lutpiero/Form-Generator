@extends('layouts.admin')

@section('title', 'Edit Form')
@section('page-title', 'Edit Form')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Edit Form: {{ $form->name }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.forms.update', $form) }}">
                    @csrf @method('PUT')
                    @include('admin.forms._form')
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Update Form
                        </button>
                        <a href="{{ route('admin.forms.show', $form) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
