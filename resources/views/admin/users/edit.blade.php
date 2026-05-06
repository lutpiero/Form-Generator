@extends('layouts.admin')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Users
    </a>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h5 class="mb-0">Edit User: {{ $user->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $user->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" id="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $user->email) }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">New Password <span class="text-muted small">(leave blank to keep current)</span></label>
                <input type="password" name="password" id="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                       class="form-control" autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                @if(auth()->id() === $user->id)
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <input type="text" class="form-control" value="{{ ucfirst($user->role) }}" disabled>
                    <div class="form-text text-muted">You cannot change your own role.</div>
                @else
                    <select name="role" id="role"
                            class="form-select @error('role') is-invalid @enderror" required>
                        <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>User</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                @endif
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Save Changes
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
