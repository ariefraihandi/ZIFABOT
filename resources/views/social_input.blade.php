<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Pengikut - {{ ucfirst($slug) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Mengamankan keterbacaan teks saat baris diberi warna background soft */
        .table-success-soft {
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
        }
        .table-warning-soft {
            background-color: #fff3cd !important;
            color: #664d03 !important;
        }
        .table-success-soft code, .table-warning-soft code {
            color: inherit !important;
            background-color: rgba(0,0,0,0.05);
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <span class="navbar-brand mb-0 h1 fw-bold">🛡️ {{ strtoupper($slug) }} OPR PANEL</span>
        <form action="{{ route('logout') }}" method="POST" onsubmit="return confirm('Yakin ingin logout?');">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm fw-bold">Logout 🚪</button>
        </form>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        
        <div class="col-lg-4 col-md-5 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white fw-bold">
                    📌 Form Input / Tambah Manual
                </div>
                <div class="card-body p-3">
                    @if(session('success'))
                        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('social.save', $slug) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-bold">User Telegram</label>
                            <select name="telegram_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih User --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->telegram_id }}">{{ $user->name }} ({{ $user->telegram_id }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Platform</label>
                            <select name="platform" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Platform --</option>
                                <option value="tt">TikTok (TT)</option>
                                <option value="ig">Instagram (IG)</option>
                                <option value="fb">Facebook (FB)</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nama Akun Sosmed</label>
                            <input type="text" name="username_sosmed" class="form-control form-select-sm" placeholder="Contoh: @username" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tanggal Masuk</label>
                            <input type="date" name="joined_at" class="form-control form-select-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold">Simpan Data Pengikut</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    📊 Daftar Antrean Masuk Sosial Media Pelanggan (Terbaru)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle small text-center">
                            <thead class="table-light text-uppercase font-monospace" style="font-size: 11px;">
                                <tr>
                                    <th class="text-start px-3">Nama Tele</th>
                                    <th>ID Tele</th>
                                    <th>Platform</th>
                                    <th class="text-start">Nama Akun Sosmed</th>
                                    <th>Tgl Masuk</th>
                                    <th>Aksi Kendali</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($socialAccounts as $account)
                                    @php
                                        // 🌟 LOGIKA PEWARNAAN BARIS TABEL
                                        $rowClass = '';
                                        if (!empty($account->expired_at)) {
                                            // Jika status database 'active' atau flag is_join bernilai true/1 -> HIJAU
                                            if ($account->status === 'active' || $account->is_join == 1) {
                                                $rowClass = 'table-success-soft';
                                            } else {
                                                // Jika sudah divalidasi (expired_at terisi) tapi belum masuk channel -> KUNING
                                                $rowClass = 'table-warning-soft';
                                            }
                                        }
                                    @endphp
                                    
                                    <tr class="{{ $rowClass }}">
                                        <td class="fw-bold text-start px-3">{{ $account->telegram_name }}</td>
                                        <td><code>{{ $account->telegram_id }}</code></td>
                                        <td>
                                            @if($account->platform == 'instagram' || $account->platform == 'ig') 
                                                <span class="badge bg-danger">IG</span>
                                            @elseif($account->platform == 'tiktok' || $account->platform == 'tt') 
                                                <span class="badge bg-dark">TT</span>
                                            @else 
                                                <span class="badge bg-primary">FB</span> 
                                            @endif
                                        </td>
                                        <td class="fw-bold text-start"><code>{{ $account->username_sosmed }}</code></td>
                                        <td>{{ $account->joined_at ? \Carbon\Carbon::parse($account->joined_at)->format('Y-m-d') : '-' }}</td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-success btn-xs py-0 px-2 fw-bold text-white" style="font-size:11px;" data-bs-toggle="modal" data-bs-target="#validModal{{ $account->id }}">Valid ✅</button>

                                                <button type="button" class="btn btn-warning btn-xs py-0 px-2 fw-bold text-dark" style="font-size:11px;" data-bs-toggle="modal" data-bs-target="#editModal{{ $account->id }}">Edit 📝</button>

                                                <form action="{{ route('social.reject', ['slug' => $slug, 'id' => $account->id]) }}" method="POST" onsubmit="return confirm('Yakin ingin menolak pengajuan ini?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-xs py-0 px-2 fw-bold" style="font-size:11px;">Tolak ❌</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="validModal{{ $account->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <div class="modal-content text-dark">                                            
                                                <form action="{{ route('social.validate', ['slug' => $slug, 'id' => $account->id]) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header bg-success text-white py-2">
                                                        <h6 class="modal-title fw-bold">Konfirmasi Tanggal Masuk</h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body py-3 text-start">
                                                        <p class="small text-muted mb-2">Tentukan tanggal masuk untuk menghitung otomatis tanggal expired (+30 hari) user ini.</p>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-bold">Tanggal Masuk</label>
                                                            <input type="date" name="joined_at" class="form-control form-control-sm" value="{{ $account->joined_at ? \Carbon\Carbon::parse($account->joined_at)->format('Y-m-d') : date('Y-m-d') }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-1">
                                                        <button type="submit" class="btn btn-success btn-sm fw-bold w-100">Validasikan & Kirim Link</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="editModal{{ $account->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <div class="modal-content text-dark">
                                                <form action="{{ route('social.update', ['slug' => $slug, 'id' => $account->id]) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header bg-warning text-dark py-2">
                                                        <h6 class="modal-title fw-bold">Koreksi Data Pengikut</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body py-3 text-start">
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-bold">Nama Akun Sosmed</label>
                                                            <input type="text" name="username_sosmed" class="form-control form-control-sm" value="{{ $account->username_sosmed }}" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-bold">Tanggal Masuk</label>
                                                            <input type="date" name="joined_at" class="form-control form-control-sm" value="{{ $account->joined_at ? \Carbon\Carbon::parse($account->joined_at)->format('Y-m-d') : date('Y-m-d') }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-1">
                                                        <button type="submit" class="btn btn-warning btn-sm fw-bold w-100">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted py-4 text-center">Belum ada pengajuan masuk akun sosial media dari pengguna bot.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>