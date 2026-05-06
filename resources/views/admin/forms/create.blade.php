@extends('layouts.admin')

@section('title', 'Create Form')
@section('page-title', 'Create Form')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Form</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.forms.store') }}">
                    @csrf
                    @include('admin.forms._form')
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Create Form
                        </button>
                        <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
