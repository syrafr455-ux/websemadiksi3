<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Atur Ulang Kata Sandi SEMADIKSI</title></head>
<body style="font-family: Arial, sans-serif; background:#f1f5f9; padding:24px; color:#0f172a;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:16px; padding:28px;">
        <h2 style="margin-top:0;">Atur Ulang Kata Sandi</h2>
        <p>Halo, {{ $nama }}.</p>
        <p>Kami menerima permintaan untuk mengatur ulang kata sandi akun SEMADIKSI Anda. Klik tombol di bawah untuk membuat kata sandi baru.</p>
        <p style="text-align:center; margin:28px 0;">
            <a href="{{ $url }}" style="background:#0d9488; color:#ffffff; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:bold;">Atur Ulang Kata Sandi</a>
        </p>
        <p style="font-size:13px; color:#475569;">Link ini berlaku {{ $berlakuMenit }} menit dan hanya dapat dipakai sekali. Bila Anda tidak meminta pengaturan ulang, abaikan email ini; kata sandi Anda tidak berubah.</p>
        <p style="font-size:12px; color:#64748b; word-break:break-all;">{{ $url }}</p>
    </div>
</body>
</html>
