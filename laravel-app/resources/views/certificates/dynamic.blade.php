<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Resmi - {{ $sertifikat->nomor_sertifikat }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
        }

        @if(!empty($backgroundBase64))
        .certificate-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
            background-image: url('{{ $backgroundBase64 }}');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
        }
        @else
        .certificate-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 50%, #f1f5f9 100%);
            padding: 14mm;
        }

        .border-outer {
            border: 4px solid #0f766e;
            height: 100%;
            padding: 4mm;
            position: relative;
            background-color: #ffffff;
        }

        .border-inner {
            border: 1.5px solid #d97706;
            height: 100%;
            padding: 8mm 12mm;
            position: relative;
        }

        .corner-ornament {
            position: absolute;
            width: 24px;
            height: 24px;
            border: 2px solid #d97706;
        }
        .corner-tl { top: 2px; left: 2px; border-right: none; border-bottom: none; }
        .corner-tr { top: 2px; right: 2px; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 2px; left: 2px; border-right: none; border-top: none; }
        .corner-br { bottom: 2px; right: 2px; border-left: none; border-top: none; }
        @endif

        .content {
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .header-logo {
            margin-bottom: 4mm;
        }
        .inst-title {
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 2px;
            color: #0f766e;
            text-transform: uppercase;
        }
        .org-title {
            font-size: 9pt;
            letter-spacing: 1.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1mm;
        }

        .cert-heading {
            font-size: 28pt;
            font-weight: 900;
            letter-spacing: 4px;
            color: #042f2e;
            text-transform: uppercase;
            margin-top: 5mm;
            margin-bottom: 1mm;
        }

        .cert-number {
            font-size: 9pt;
            font-family: 'Courier New', Courier, monospace;
            color: #475569;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 4mm;
        }

        .cert-statement {
            font-size: 11pt;
            color: #475569;
            font-style: italic;
            margin-bottom: 3mm;
        }

        .recipient-name {
            font-size: 23pt;
            font-weight: 800;
            color: #0f766e;
            text-transform: uppercase;
            border-bottom: 2px solid #d97706;
            display: inline-block;
            padding: 0 15mm 2mm 15mm;
            margin-bottom: 3mm;
            letter-spacing: 1px;
        }

        .recipient-meta {
            font-size: 10pt;
            color: #334155;
            margin-bottom: 4mm;
        }

        .cert-reason {
            font-size: 11pt;
            color: #334155;
            max-width: 220mm;
            margin: 0 auto 5mm auto;
            line-height: 1.4;
        }

        .event-title {
            font-weight: 800;
            color: #0f172a;
        }

        /* Signatures and QR Code Footer */
        .footer-table {
            width: 100%;
            margin-top: 6mm;
            border-collapse: collapse;
        }

        .sign-box {
            text-align: center;
            width: 33%;
            vertical-align: bottom;
        }

        .sign-title {
            font-size: 9pt;
            color: #475569;
            margin-bottom: 14mm;
        }

        .sign-name {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 1mm;
            display: inline-block;
        }

        .sign-role {
            font-size: 8pt;
            color: #64748b;
            margin-top: 1mm;
        }

        /* QR Code verification widget */
        .qr-widget {
            position: absolute;
            bottom: 18mm;
            right: 18mm;
            width: 48mm;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2.5mm;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            z-index: 20;
        }

        .qr-image {
            width: 22mm;
            height: 22mm;
            margin: 0 auto 1.5mm auto;
            display: block;
        }

        .qr-caption {
            font-size: 6.5pt;
            color: #475569;
            line-height: 1.2;
            font-weight: 600;
            text-transform: uppercase;
        }

        .qr-code-text {
            font-size: 6pt;
            font-family: 'Courier New', Courier, monospace;
            color: #0f766e;
            font-weight: bold;
            margin-top: 1mm;
        }

        .hash-info {
            position: absolute;
            bottom: 4mm;
            left: 18mm;
            font-size: 6pt;
            font-family: 'Courier New', Courier, monospace;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        @if(empty($backgroundBase64))
        <div class="border-outer">
            <div class="border-inner">
                <div class="corner-ornament corner-tl"></div>
                <div class="corner-ornament corner-tr"></div>
                <div class="corner-ornament corner-bl"></div>
                <div class="corner-ornament corner-br"></div>
        @endif

                <div class="content" style="{{ !empty($contentStyle) ? $contentStyle : '' }}">
                    @if(empty($backgroundBase64))
                    <div class="header-logo">
                        <div class="inst-title">UNIVERSITAS NAHDLATUL ULAMA SURABAYA</div>
                        <div class="org-title">FORUM MAHASISWA BIDIKMISI & KIP KULIAH (SEMADIKSI)</div>
                    </div>

                    <div class="cert-heading">SERTIFIKAT</div>
                    <div class="cert-number">Nomor: {{ $sertifikat->nomor_sertifikat }}</div>

                    <div class="cert-statement">Diberikan dengan penuh kehormatan dan apresiasi kepada:</div>
                    @else
                    <div style="height: 48mm;"></div>
                    <div class="cert-number">Nomor: {{ $sertifikat->nomor_sertifikat }}</div>
                    @endif

                    <div class="recipient-name">
                        {{ $namaLengkap }}
                    </div>

                    @if(!empty($nim) || !empty($prodi))
                    <div class="recipient-meta">
                        {{ $nim ? 'NIM. ' . $nim : '' }} {{ $nim && $prodi ? ' • ' : '' }} {{ $prodi ? 'Program Studi ' . $prodi : '' }}
                    </div>
                    @endif

                    <div class="cert-reason">
                        Atas partisipasi dan dedikasinya sebagai 
                        <strong style="color: #0f766e; text-transform: uppercase;">
                            {{ $sertifikat->peran_sertifikat ? $sertifikat->peran_sertifikat->label() : 'Peserta' }}
                        </strong> 
                        pada agenda:
                        <br>
                        <span class="event-title" style="font-size: 13pt;">"{{ $kegiatan->nama_kegiatan }}"</span>
                        <br>
                        <span style="font-size: 9.5pt; color: #64748b;">
                            Diselenggarakan pada tanggal {{ $tanggalKegiatan }}
                        </span>
                    </div>

                    @if(empty($backgroundBase64))
                    <table class="footer-table">
                        <tr>
                            <td class="sign-box">
                                <div class="sign-title">Surabaya, {{ $tanggalTerbit }}<br>Ketua Pelaksana</div>
                                <div class="sign-name">Ferdyant Rahmansyah</div>
                                <div class="sign-role">Ketua Panitia Pelaksana</div>
                            </td>
                            <td class="sign-box" style="width: 34%;">
                                <!-- Spacer for middle -->
                            </td>
                            <td class="sign-box">
                                <div class="sign-title">Surabaya, {{ $tanggalTerbit }}<br>Ketua Umum SEMADIKSI</div>
                                <div class="sign-name">Achmad Fathur R.</div>
                                <div class="sign-role">NIM. 2240021001</div>
                            </td>
                        </tr>
                    </table>
                    @endif
                </div>

        @if(empty($backgroundBase64))
            </div>
        </div>
        @endif

        {{-- Dynamic QR Code Verification Badge --}}
        <div class="qr-widget">
            <img class="qr-image" src="{{ $qrCodeBase64 }}" alt="QR Code Verifikasi">
            <div class="qr-caption">Dokumen Resmi Ber-TTE</div>
            <div class="qr-caption" style="font-size: 5pt; color: #64748b;">Pindai untuk validasi</div>
            <div class="qr-code-text">{{ $sertifikat->nomor_sertifikat }}</div>
        </div>

        <div class="hash-info">
            SHA-256 Doc Hash: {{ $sertifikat->document_hash ?? 'GENERATING' }} • UNUSA SEMADIKSI Verifier
        </div>
    </div>
</body>
</html>
