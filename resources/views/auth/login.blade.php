<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Form Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .btn-login { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .brand-icon { font-size: 3rem; color: #764ba2; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="brand-icon">📋</div>
                            <h3 class="fw-bold mt-2">Form Generator</h3>
                            <p class="text-muted">Admin Panel</p>
                        </div>
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control form-control-lg @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" required autofocus placeholder="admin@example.com">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg @error('password') is-invalid @enderror"
                                    required placeholder="••••••••">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login btn-lg text-white">
                                    Sign In →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <p class="text-center text-white mt-3 small opacity-75">
                    Default: admin@example.com / password
                </p>
            </div>
        </div>
    </div>
</body>
</html>
