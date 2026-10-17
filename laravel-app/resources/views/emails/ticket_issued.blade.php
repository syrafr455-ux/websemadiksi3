<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Tiket Resmi SEMADIKSI</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 24px; }
        .ticket-wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
        .ticket-header { background: linear-gradient(135deg, #059669 0%, #047857 100%); padding: 28px; color: #ffffff; text-align: center; }
        .ticket-header h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .ticket-header p { margin: 4px 0 0 0; font-size: 13px; opacity: 0.9; }
        .ticket-body { padding: 32px 28px; }
        .ticket-code-box { background: #ecfdf5; border: 2px dashed #059669; border-radius: 12px; padding: 16px; text-align: center; margin-bottom: 24px; }
        .ticket-code-label { font-size: 11px; text-transform: uppercase; color: #047857; font-weight: 700; letter-spacing: 1px; }
        .ticket-code { font-size: 24px; font-weight: 900; color: #065f46; letter-spacing: 2px; margin-top: 4px; }
        .ticket-details { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .ticket-details td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .ticket-details td.label { color: #64748b; width: 40%; }
        .ticket-details td.value { font-weight: 700; color: #0f172a; width: 60%; }
        .ticket-footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="ticket-wrapper">
        <div class="ticket-header">
            <h1>E-TIKET RESMI</h1>
            <p>SEMADIKSI - Universitas Nahdlatul Ulama Surabaya</p>
        </div>
        <div class="ticket-body">
            <div class="ticket-code-box">
                <div class="ticket-code-label">Nomor Tiket Peserta</div>
                <div class="ticket-code">{{ $registration->nomor_tiket }}</div>
            </div>

            <table class="ticket-details">
                <tr>
                    <td class="label">Nama Kegiatan</td>
                    <td class="value">{{ $registration->kegiatan->nama_kegiatan }}</td>
                </tr>
                <tr>
                    <td class="label">Nama Peserta</td>
                    <td class="value">{{ $registration->participant_name }}</td>
                </tr>
                <tr>
                    <td class="label">Identitas / NIM</td>
                    <td class="value">{{ $registration->participant_identity ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Nomor Kursi</td>
                    <td class="value">
                        @if($registration->seat)
                            <strong style="color: #059669; font-size: 16px;">{{ $registration->seat->seat_code }}</strong> (Baris {{ $registration->seat->row_label }}, Kolom {{ $registration->seat->col_number }})
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Waktu Pelaksanaan</td>
                    <td class="value">{{ $registration->kegiatan->tanggal_mulai ? \App\Support\Wib::dari($registration->kegiatan->tanggal_mulai)->translatedFormat('d F Y, H:i').' WIB' : '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Lokasi / Gedung</td>
                    <td class="value">{{ $registration->kegiatan->lokasi_nama }} {{ $registration->kegiatan->lokasi_detail ? '('.$registration->kegiatan->lokasi_detail.')' : '' }}</td>
                </tr>
                <tr>
                    <td class="label">Status Pembayaran</td>
                    <td class="value"><span style="color: #059669; font-weight: bold;">LUNAS ({{ strtoupper($registration->payment_status->value) }})</span></td>
                </tr>
            </table>

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
                Tunjukkan nomor tiket ini atau QR Code di dashboard saat proses check-in di lokasi kegiatan.
            </p>
        </div>
        <div class="ticket-footer">
            &copy; {{ date('Y') }} SEMADIKSI UNUSA. Simpan email ini sebagai bukti pendaftaran sah.
        </div>
    </div>
</body>
</html>
