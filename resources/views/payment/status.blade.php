<!DOCTYPE html>
<html>
<head>
    <title>Status Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card p-4 text-center shadow" style="width: 350px;">
        @if($status == 'success')
            <h1 class="text-success">✅</h1>
            <h3>Pembayaran Sukses!</h3>
            <p>Silakan kembali ke Telegram untuk mendapatkan akses grup.</p>
        @else
            <h1 class="text-danger">❌</h1>
            <h3>Transaksi Dibatalkan</h3>
            <p>Silakan ulangi pesanan melalui bot jika ingin melanjutkan.</p>
        @endif
        <a href="https://t.me/zifazalina_bot" class="btn btn-primary mt-3">Kembali ke Bot</a>
    </div>
</body>
</html>