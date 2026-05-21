<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Zifabot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .login-container { max-width: 400px; margin-top: 10%; }
    </style>
</head>
<body>

<div class="container login-container">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white text-center py-3">
            <h5 class="mb-0">🔑 ZIFABOT ADMIN LOGIN</h5>
        </div>
        <div class="card-body p-4">

            @if ($errors->any())
                <div class="alert alert-danger py-2">
                    <ul class="mb-0" style="padding-left: 15px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login.perform') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-dark btn-block py-2">Masuk ke Panel</button>
                </div>
            </form>

        </div>
    </div>
</div>

</body>
</html>