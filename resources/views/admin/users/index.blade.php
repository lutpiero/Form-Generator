@extends('layouts.admin')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Users</h4>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Create New User
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="align-middle">{{ $user->name }}</td>
                        <td class="align-middle">{{ $user->email }}</td>
                        <td class="align-middle">
                            @if($user->role === 'admin')
                                <span class="badge bg-primary">Admin</span>
                            @else
                                <span class="badge bg-secondary">User</span>
                            @endif
                        </td>
                        <td class="align-middle text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="align-middle text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary me-1">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            @if(auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                                      onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($users->hasPages())
    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endif
@endsection
