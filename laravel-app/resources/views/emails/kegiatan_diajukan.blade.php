<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Permohonan Persetujuan Kegiatan</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #334155; margin: 0; padding: 24px; background-color: #f8fafc;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="margin-bottom: 24px;">
            <span style="font-size: 11px; font-weight: 700; color: #047857; text-transform: uppercase; letter-spacing: 0.05em; background-color: #ecfdf5; padding: 4px 10px; rounded-full: 9999px; border-radius: 9999px;">
                SEMADIKSI UNUSA
            </span>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 12px 0 6px 0;">
                Permohonan Persetujuan Kegiatan
            </h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">
                Yth. {{ $penyetuju->name }},
            </p>
        </div>

        <p style="font-size: 14px; color: #334155; margin-bottom: 20px;">
            Sebuah kegiatan baru telah diajukan untuk ditinjau dan disetujui bersama sebelum diterbitkan untuk peserta:
        </p>

        <div style="background-color: #f1f5f9; border-radius: 8px; padding: 16px; margin-bottom: 24px; font-size: 13px;">
            <p style="margin: 0 0 8px 0;">
                <strong style="color: #475569;">Nama Kegiatan:</strong><br>
                <span style="font-size: 15px; font-weight: 700; color: #0f172a;">{{ $kegiatan->nama_kegiatan }}</span>
            </p>
            <p style="margin: 0 0 8px 0;">
                <strong style="color: #475569;">Diajukan Oleh:</strong><br>
                {{ $kegiatan->createdBy?->name ?? 'Super Admin' }}
            </p>
            <p style="margin: 0 0 8px 0;">
                <strong style="color: #475569;">Waktu Pelaksanaan (WIB):</strong><br>
                {{ $kegiatan->tanggal_mulai ? \App\Support\Wib::dari($kegiatan->tanggal_mulai)->translatedFormat('d F Y, H:i') . ' WIB' : '-' }}
                s.d.
                {{ $kegiatan->tanggal_selesai ? \App\Support\Wib::dari($kegiatan->tanggal_selesai)->translatedFormat('d F Y, H:i') . ' WIB' : '-' }}
            </p>
            <p style="margin: 0;">
                <strong style="color: #475569;">Lokasi:</strong><br>
                {{ $kegiatan->lokasi_nama }}
            </p>
        </div>

        <p style="font-size: 14px; color: #334155; margin-bottom: 24px;">
            Sesuai tata kelola SEMADIKSI, kegiatan enterprise memerlukan persetujuan dari seluruh Super Admin aktif sebelum dapat terbit ke publik. Silakan tinjau detail kegiatan melalui tautan di bawah ini:
        </p>

        <div style="text-align: center; margin-bottom: 32px;">
            <a href="{{ route('admin.kegiatan.show', $kegiatan->id) }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 8px;">
                Tinjau Kegiatan
            </a>
        </div>

        <div style="border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 12px; color: #94a3b8; text-align: center;">
            <p style="margin: 0;">Email ini dikirim secara otomatis oleh Sistem Enterprise SEMADIKSI UNUSA.</p>
        </div>
    </div>
</body>
</html>
