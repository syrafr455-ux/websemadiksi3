<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pemilihan Ulang Kursi: {{ $kegiatan->nama_kegiatan }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #0d9488; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Pemberitahuan Pemindahan Ruangan Kegiatan</h2>
        <p style="margin: 5px 0 0 0; font-size: 14px;">Sistem Informasi SEMADIKSI UNUSA</p>
    </div>

    <div style="background-color: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p>Halo <strong>{{ $recipientName }}</strong>,</p>

        <p>Kami memberitahukan bahwa terdapat perubahan ruangan dan tata letak denah kursi pada kegiatan yang Anda ikuti:</p>

        <div style="background-color: white; border-left: 4px solid #0d9488; padding: 14px 18px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0 0 8px 0;"><strong>Kegiatan:</strong> {{ $kegiatan->nama_kegiatan }}</p>
            <p style="margin: 0 0 8px 0;"><strong>Ruangan Baru:</strong> {{ $kegiatan->lokasi_nama }}</p>
            @if($deadlineWib)
                <p style="margin: 0;"><strong>Batas Waktu Pemilihan Ulang Kursi:</strong> {{ $deadlineWib }}</p>
            @endif
        </div>

        <p>Kursi Anda sebelumnya telah diatur ulang untuk menyesuaikan tata letak ruangan baru. <strong>Nomor E-Tiket dan status pendaftaran Anda tetap valid dan aman.</strong></p>

        @if(!empty($isBooker) && !empty($reselectionUrl))
            <p>Silakan segera memilih ulang kursi yang Anda inginkan (termasuk untuk delegasi Anda jika ada). Tautan berikut langsung membuka halaman pemilihan ulang kursi; bila belum masuk, Anda akan diminta masuk dulu ke akun SEMADIKSI.</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $reselectionUrl }}" style="background-color: #0d9488; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                    Pilih Ulang Kursi Sekarang
                </a>
            </div>

            <p style="font-size: 13px; color: #6b7280;">Jika tombol di atas tidak dapat diklik, salin dan buka tautan berikut di browser Anda:<br>
                <a href="{{ $reselectionUrl }}" style="color: #0d9488;">{{ $reselectionUrl }}</a>
            </p>
        @else
            <p style="background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 16px; border-radius: 6px; margin-top: 16px;">
                <strong>Informasi Pemilihan Kursi:</strong> Pemilihan ulang kursi untuk tiket Anda akan dilakukan oleh pemesan tiket (booker). Anda tidak perlu melakukan pemilihan kursi secara mandiri.
            </p>
        @endif

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

        <p style="font-size: 12px; color: #9ca3af; margin: 0;">
            Pesan ini dikirimkan secara otomatis oleh Sistem Informasi SEMADIKSI Universitas Nahdlatul Ulama Surabaya.
        </p>
    </div>
</body>
</html>
