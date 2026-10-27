<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Resmi SEMADIKSI Terbit</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 24px; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1b6d24 0%, #14532d 100%); padding: 28px; color: #ffffff; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }
        .header p { margin: 4px 0 0 0; font-size: 13px; opacity: 0.9; }
        .body { padding: 32px 28px; }
        .cert-box { background: #f0fdf4; border: 2px dashed #16a34a; border-radius: 12px; padding: 16px; text-align: center; margin-bottom: 24px; }
        .cert-label { font-size: 11px; text-transform: uppercase; color: #166534; font-weight: 700; letter-spacing: 1px; }
        .cert-code { font-size: 22px; font-weight: 900; color: #14532d; letter-spacing: 1.5px; margin-top: 4px; font-family: monospace; }
        .details { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .details td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .details td.label { color: #64748b; width: 38%; }
        .details td.value { font-weight: 700; color: #0f172a; width: 62%; }
        .btn-wrapper { text-align: center; margin: 28px 0 12px 0; }
        .btn { display: inline-block; background: #1b6d24; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 10px; font-weight: bold; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(27, 109, 36, 0.3); }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>SERTIFIKAT RESMI TERBIT</h1>
            <p>SEMADIKSI - Universitas Nahdlatul Ulama Surabaya</p>
        </div>
        <div class="body">
            <p style="font-size: 15px; line-height: 1.6; margin-top: 0; color: #334155;">
                Halo <strong>{{ $sertifikat->recipient_name }}</strong>,
            </p>
            <p style="font-size: 14px; line-height: 1.6; color: #475569;">
                Selamat! Sertifikat digital resmi Anda untuk kegiatan <strong>{{ $sertifikat->kegiatan?->nama_kegiatan ?? 'Kegiatan SEMADIKSI' }}</strong> telah resmi diterbitkan oleh panitia.
            </p>

            <div class="cert-box">
                <div class="cert-label">Nomor Seri Sertifikat</div>
                <div class="cert-code">{{ $sertifikat->nomor_sertifikat }}</div>
            </div>

            <table class="details">
                <tr>
                    <td class="label">Nama Penerima</td>
                    <td class="value">{{ $sertifikat->recipient_name }}</td>
                </tr>
                <tr>
                    <td class="label">Peran / Jenis</td>
                    <td class="value">
                        {{ $sertifikat->jenis_label }}
                        @if($sertifikat->predikat)
                            ({{ $sertifikat->predikat }})
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Asal Institusi</td>
                    <td class="value">{{ $sertifikat->institution_name }}</td>
                </tr>
                <tr>
                    <td class="label">Kegiatan</td>
                    <td class="value">{{ $sertifikat->kegiatan?->nama_kegiatan ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Terbit</td>
                    <td class="value">{{ $sertifikat->issued_at ? \App\Support\Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y') : \App\Support\Wib::sekarang()->translatedFormat('d F Y') }}</td>
                </tr>
            </table>

            <div class="btn-wrapper">
                <a href="{{ $sertifikat->verification_url }}" class="btn" target="_blank">
                    Buka Halaman Verifikasi Resmi
                </a>
            </div>

            <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 16px;">
                Anda juga dapat memverifikasi sertifikat ini kapan saja dengan memindai QR code pada dokumen atau mengunjungi tautan verifikasi di atas.
            </p>
        </div>
        <div class="footer">
            <p style="margin: 0;">Sistem Informasi SEMADIKSI &bull; Universitas Nahdlatul Ulama Surabaya</p>
            <p style="margin: 4px 0 0 0;">Email ini dikirim secara otomatis oleh sistem.</p>
        </div>
    </div>
</body>
</html>
