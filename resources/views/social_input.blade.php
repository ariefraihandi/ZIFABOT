<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Sosial Media - {{ ucfirst($slug) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white text-center">
                    <h4 class="mb-0">Inputasi Pengikut: {{ ucfirst($slug) }}</h4>
                </div>
                <div class="card-body p-4">

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border mb-4">
                        <div>
                            <small class="text-muted d-block">Klik tombol ini untuk menyedot otomatis ID pengguna yang tersedia di channel:</small>
                        </div>
                        <a href="{{ route('satpam.sync') }}" class="btn btn-outline-dark btn-sm fw-bold">
                            🛡️ Jalankan Satpam Bot
                        </a>
                    </div>

                    <form action="{{ route('social.save', $slug) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Pelanggan (User Bot)</label>
                        <select name="telegram_id" class="form-select" required>
                            <option value="">-- Pilih User --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->telegram_id }}">{{ $user->name }} ({{ $user->telegram_id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Platform Sosial Media</label>
                        <select name="platform" class="form-select" required>
                            <option value="">-- Pilih Platform --</option>
                            <option value="tt">TikTok (TT)</option>
                            <option value="ig">Instagram (IG)</option>
                            <option value="fb">Facebook (FB)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama / Username Akun Sosmed</label>
                        <input type="text" name="username_sosmed" class="form-control" placeholder="Contoh: @zifazalina" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tanggal Masuk Sosial Media</label>
                        <input type="date" name="joined_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                        <div class="form-text text-muted">Masa aktif di Telegram otomatis diset 30 hari sejak tanggal ini.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-block">Simpan Data Pengikut</button>
                    </div>
                </form>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>