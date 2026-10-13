<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembaruan Kegiatan SEMADIKSI</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #059669; padding: 28px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
        .body { padding: 32px 28px; font-size: 14px; line-height: 1.6; }
        .badge { display: inline-block; background: #ecfdf5; color: #047857; font-weight: bold; font-size: 12px; padding: 4px 12px; border-radius: 9999px; border: 1px solid #a7f3d0; margin-bottom: 12px; }
        .details-box { background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 18px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; }
        .details-row:last-child { border-bottom: none; }
        .label { color: #64748b; font-size: 13px; }
        .value { font-weight: bold; color: #0f172a; font-size: 13px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>SEMADIKSI UNUSA</h1>
            <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;">Pemberitahuan Resmi Pembaruan Kegiatan</p>
        </div>
        <div class="body">
            <span class="badge">Informasi Penting</span>
            <p>Halo, <strong>{{ $user->name }}</strong>,</p>
            <p>Panitia menginformasikan adanya pembaruan data terkait kegiatan <strong>{{ $kegiatan->nama_kegiatan }}</strong> yang Anda ikuti.</p>

            <div class="details-box">
                <div class="details-row">
                    <span class="label">Jadwal Mulai</span>
                    <span class="value">{{ $kegiatan->waktu_mulai ? $kegiatan->waktu_mulai->translatedFormat('l, d F Y H:i') : '-' }} WIB</span>
                </div>
                <div class="details-row">
                    <span class="label">Lokasi</span>
                    <span class="value">{{ $kegiatan->lokasi_nama }}</span>
                </div>
                @if($kegiatan->lokasi_detail)
                <div class="details-row">
                    <span class="label">Ruangan / Detail</span>
                    <span class="value">{{ $kegiatan->lokasi_detail }}</span>
                </div>
                @endif
            </div>

            <p>Silakan periksa kembali dashboard kegiatan Anda untuk memastikan detail tiket dan kehadiran Anda.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} SEMADIKSI - Universitas Nahdlatul Ulama Surabaya.
        </div>
    </div>
</body>
</html>
