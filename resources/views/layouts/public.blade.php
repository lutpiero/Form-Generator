<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Form Generator')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .form-card { background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .form-title { color: #764ba2; font-weight: 700; }
        .btn-submit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 0.75rem 2rem; font-size: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .table-cell-invalid { background-color: rgba(220, 53, 69, 0.08); }
    </style>
    @stack('styles')
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="{{ trim($__env->yieldContent('container-width', 'col-12 col-xl-10')) }}">
            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
