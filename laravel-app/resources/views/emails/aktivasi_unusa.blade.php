<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Aktivasi Akun SEMADIKSI</title></head>
<body style="font-family: Arial, sans-serif; background:#f1f5f9; padding:24px; color:#0f172a;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:16px; padding:28px;">
        <h2 style="margin-top:0;">Aktivasi Akun SEMADIKSI</h2>
        <p>Halo, {{ $nama }}.</p>
        <p>Kami menerima permintaan pendaftaran akun SEMADIKSI memakai NIM Anda. Klik tombol di bawah untuk melihat data akademik Anda dan menyelesaikan pendaftaran.</p>
        <p style="text-align:center; margin:28px 0;">
            <a href="{{ $url }}" style="background:#0d9488; color:#ffffff; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:bold;">Aktifkan Akun</a>
        </p>
        <p style="font-size:13px; color:#475569;">Link ini berlaku {{ $berlakuMenit }} menit dan hanya untuk Anda. Bila Anda tidak merasa mendaftar, abaikan email ini.</p>
        <p style="font-size:12px; color:#64748b; word-break:break-all;">{{ $url }}</p>
    </div>
</body>
</html>
