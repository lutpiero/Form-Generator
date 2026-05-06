@extends('layouts.public')

@section('title', 'Submitted!')

@section('content')
<div class="form-card p-4 p-md-5 text-center">
    <div class="display-1 text-success mb-3">
        <i class="bi bi-check-circle-fill"></i>
    </div>
    <h2 class="form-title mb-3">{{ $form->name }}</h2>
    <p class="lead text-muted">
        {{ session('success') ?: ($form->success_message ?: 'Thank you for your submission!') }}
    </p>
    <hr>
    <a href="{{ route('forms.show', $form) }}" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left"></i> Submit Another Response
    </a>
</div>
@endsection
