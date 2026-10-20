<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Resmi - SEMADIKSI UNUSA</title>
    <style>
        @page {
            size: 297mm 210mm;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1E293B;
            background-color: #FCFBF7;
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
        }

        /* Vector Corner Ribbons */
        .ribbon-tl {
            position: absolute;
            top: 0;
            left: 0;
            width: 105mm;
            height: 85mm;
            z-index: 1;
        }
        .ribbon-tr {
            position: absolute;
            top: 0;
            right: 0;
            width: 85mm;
            height: 70mm;
            z-index: 1;
        }
        .ribbon-bl {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 85mm;
            height: 70mm;
            z-index: 1;
        }
        .ribbon-br {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 110mm;
            height: 90mm;
            z-index: 1;
        }

        /* Gold Dot Matrix */
        .dots-tr {
            position: absolute;
            top: 13mm;
            right: 28mm;
            z-index: 2;
        }
        .dots-bl {
            position: absolute;
            bottom: 13mm;
            left: 28mm;
            z-index: 2;
        }

        /* Golden Ribbon Medal Badge (Top Right) */
        .medal-badge {
            position: absolute;
            top: 10mm;
            right: 14mm;
            width: 28mm;
            height: 38mm;
            z-index: 5;
            text-align: center;
        }

        /* Double Frame Border */
        .border-outer {
            position: absolute;
            top: 9mm;
            left: 9mm;
            right: 9mm;
            bottom: 9mm;
            border: 1.5pt solid #D4AF37;
            pointer-events: none;
            z-index: 3;
        }
        .border-inner {
            position: absolute;
            top: 11.5mm;
            left: 11.5mm;
            right: 11.5mm;
            bottom: 11.5mm;
            border: 0.75pt solid #0B2545;
            pointer-events: none;
            z-index: 3;
        }

        /* Content Container */
        .main-content {
            position: absolute;
            top: 14mm;
            left: 20mm;
            right: 20mm;
            bottom: 14mm;
            text-align: center;
            z-index: 10;
        }

        /* Header Kop */
        .header-emblem {
            margin: 0 auto 1.5mm auto;
            width: 18mm;
            height: 14mm;
        }
        .inst-title {
            font-size: 10pt;
            font-weight: 800;
            letter-spacing: 2px;
            color: #0B2545;
            text-transform: uppercase;
        }
        .org-title {
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #8C6D23;
            text-transform: uppercase;
            margin-top: 0.5mm;
        }

        /* Certificate Heading */
        .cert-heading {
            font-size: 30pt;
            font-weight: 900;
            letter-spacing: 6px;
            color: #0B2545;
            text-transform: uppercase;
            margin-top: 3mm;
            line-height: 1;
        }
        .cert-subheading {
            font-size: 11.5pt;
            font-weight: 800;
            letter-spacing: 4px;
            color: #D4AF37;
            text-transform: uppercase;
            margin-top: 1mm;
        }

        /* Recipient Section */
        .cert-intro {
            font-size: 10pt;
            font-style: italic;
            color: #475569;
            margin-top: 3mm;
        }
        .recipient-name {
            font-family: 'Playfair Display', 'Cinzel', 'Georgia', 'Times New Roman', serif;
            font-size: 30pt;
            font-weight: 700;
            color: #9A7B38;
            margin-top: 1.5mm;
            line-height: 1.1;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Diamond Divider */
        .divider-table {
            width: 130mm;
            margin: 1.5mm auto 1.5mm auto;
            border-collapse: collapse;
        }
        .divider-line {
            border-bottom: 1.2pt solid #D4AF37;
            width: 48%;
            vertical-align: middle;
        }
        .divider-diamond {
            width: 4%;
            text-align: center;
            color: #D4AF37;
            font-size: 10pt;
            vertical-align: middle;
            line-height: 1;
        }

        .recipient-meta {
            font-size: 9pt;
            color: #475569;
            margin-top: 0.5mm;
        }

        /* Role & Statement */
        .role-label {
            font-size: 8.5pt;
            color: #64748B;
            margin-top: 1.5mm;
        }
        .role-badge {
            display: inline-block;
            font-size: 9.5pt;
            font-weight: 800;
            letter-spacing: 2px;
            color: #0B2545;
            background: #FFFFFF;
            border: 1pt solid #D4AF37;
            border-radius: 12px;
            padding: 2px 16px;
            text-transform: uppercase;
            margin-top: 1mm;
        }
        .cert-reason {
            font-size: 9.5pt;
            line-height: 1.45;
            color: #334155;
            max-width: 195mm;
            margin: 2mm auto 0 auto;
        }
        .event-title {
            font-weight: 800;
            color: #0B2545;
        }

        /* Dual Signatures Table */
        .footer-table {
            width: 80%;
            margin: 4mm auto 0 auto;
            border-collapse: collapse;
        }
        .sign-cell {
            text-align: center;
            width: 42%;
            vertical-align: bottom;
        }
        .sign-date {
            font-size: 8pt;
            color: #475569;
            margin-bottom: 1mm;
        }
        .sign-title {
            font-size: 8.5pt;
            font-weight: 700;
            color: #0B2545;
        }
        .sign-space {
            height: 13mm;
        }
        .sign-name {
            font-size: 9.5pt;
            font-weight: 700;
            color: #0B2545;
            border-bottom: 1pt solid #0B2545;
            padding-bottom: 0.5mm;
            display: inline-block;
        }
        .sign-role {
            font-size: 7.5pt;
            color: #64748B;
            margin-top: 0.5mm;
        }

        /* QR Code Widget (Bottom Right) */
        .qr-widget {
            position: absolute;
            bottom: 12mm;
            right: 14mm;
            background: #FFFFFF;
            border: 1pt solid #D4AF37;
            border-radius: 6px;
            padding: 2mm;
            text-align: center;
            width: 25mm;
            z-index: 20;
            box-shadow: 0 2px 5px rgba(11,37,69,0.08);
        }
        .qr-image {
            width: 20mm;
            height: 20mm;
            display: block;
            margin: 0 auto;
        }
        .qr-label {
            font-size: 5.5pt;
            font-weight: 800;
            color: #0B2545;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1mm;
            line-height: 1.1;
        }

        /* Document Hash Footer (Bottom Left) */
        .doc-hash {
            position: absolute;
            bottom: 10mm;
            left: 14mm;
            font-size: 5.5pt;
            font-family: 'Courier New', Courier, monospace;
            color: #94A3B8;
            z-index: 15;
        }
    </style>
</head>
<body>
    @if(empty($backgroundBase64))
    <!-- Top-Left Navy-Gold Ribbons -->
    <svg class="ribbon-tl" viewBox="0 0 380 300" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 0 L260 0 C200 45 150 90 120 170 C95 230 45 270 0 290 Z" fill="#0B2545" />
        <path d="M0 0 L210 0 C160 40 120 80 95 150 C75 205 35 240 0 255 Z" fill="#133B68" />
        <path d="M0 0 L165 0 C125 35 90 70 70 130 C52 180 25 210 0 225 Z" fill="#D4AF37" />
        <path d="M0 0 L130 0 C95 30 70 60 52 110 C38 150 18 180 0 195 Z" fill="#F3E5AB" />
        <path d="M0 225 C25 210 52 180 70 130 C90 70 125 35 165 0 L178 0 C138 37 100 73 80 135 C60 186 30 218 0 238 Z" fill="#9A7B38" />
    </svg>

    <!-- Top-Right Ribbon Wave -->
    <svg class="ribbon-tr" viewBox="0 0 300 240" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M300 0 L100 0 C150 35 190 70 210 130 C230 180 270 215 300 230 Z" fill="#0B2545" />
        <path d="M300 0 L145 0 C185 32 218 64 235 115 C250 155 280 185 300 200 Z" fill="#D4AF37" />
        <path d="M300 0 L180 0 C210 28 235 55 250 95 C262 130 285 155 300 170 Z" fill="#F3E5AB" />
    </svg>

    <!-- Bottom-Left Ribbon Wave -->
    <svg class="ribbon-bl" viewBox="0 0 300 240" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 240 L200 240 C150 205 110 170 90 110 C70 60 30 25 0 10 Z" fill="#0B2545" />
        <path d="M0 240 L155 240 C115 208 82 176 65 125 C50 85 20 55 0 40 Z" fill="#D4AF37" />
        <path d="M0 240 L120 240 C90 212 65 185 50 145 C38 110 15 85 0 70 Z" fill="#F3E5AB" />
    </svg>

    <!-- Bottom-Right Navy-Gold Ribbons -->
    <svg class="ribbon-br" viewBox="0 0 400 320" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M400 320 L140 320 C200 275 250 230 280 150 C305 90 355 50 400 30 Z" fill="#0B2545" />
        <path d="M400 320 L190 320 C240 280 280 240 305 170 C325 115 365 80 400 65 Z" fill="#133B68" />
        <path d="M400 320 L235 320 C275 285 310 250 330 190 C348 140 375 110 400 95 Z" fill="#D4AF37" />
        <path d="M400 320 L270 320 C305 290 330 260 348 210 C362 170 382 140 400 125 Z" fill="#F3E5AB" />
    </svg>

    <!-- Gold Dot Matrix 6x2 (Top Right) -->
    <svg class="dots-tr" width="70" height="24" viewBox="0 0 70 24" xmlns="http://www.w3.org/2000/svg">
        <circle cx="5" cy="5" r="2" fill="#D4AF37" />
        <circle cx="17" cy="5" r="2" fill="#D4AF37" />
        <circle cx="29" cy="5" r="2" fill="#D4AF37" />
        <circle cx="41" cy="5" r="2" fill="#D4AF37" />
        <circle cx="53" cy="5" r="2" fill="#D4AF37" />
        <circle cx="65" cy="5" r="2" fill="#D4AF37" />
        <circle cx="5" cy="17" r="2" fill="#D4AF37" />
        <circle cx="17" cy="17" r="2" fill="#D4AF37" />
        <circle cx="29" cy="17" r="2" fill="#D4AF37" />
        <circle cx="41" cy="17" r="2" fill="#D4AF37" />
        <circle cx="53" cy="17" r="2" fill="#D4AF37" />
        <circle cx="65" cy="17" r="2" fill="#D4AF37" />
    </svg>

    <!-- Gold Dot Matrix 6x2 (Bottom Left) -->
    <svg class="dots-bl" width="70" height="24" viewBox="0 0 70 24" xmlns="http://www.w3.org/2000/svg">
        <circle cx="5" cy="5" r="2" fill="#D4AF37" />
        <circle cx="17" cy="5" r="2" fill="#D4AF37" />
        <circle cx="29" cy="5" r="2" fill="#D4AF37" />
        <circle cx="41" cy="5" r="2" fill="#D4AF37" />
        <circle cx="53" cy="5" r="2" fill="#D4AF37" />
        <circle cx="65" cy="5" r="2" fill="#D4AF37" />
        <circle cx="5" cy="17" r="2" fill="#D4AF37" />
        <circle cx="17" cy="17" r="2" fill="#D4AF37" />
        <circle cx="29" cy="17" r="2" fill="#D4AF37" />
        <circle cx="41" cy="17" r="2" fill="#D4AF37" />
        <circle cx="53" cy="17" r="2" fill="#D4AF37" />
        <circle cx="65" cy="17" r="2" fill="#D4AF37" />
    </svg>

    <!-- Golden Ribbon Medal Badge (Top Right) -->
    <div class="medal-badge">
        <svg width="80" height="110" viewBox="0 0 80 110" xmlns="http://www.w3.org/2000/svg">
            <!-- Ribbon Tails -->
            <polygon points="26,45 15,100 28,90 38,100 34,45" fill="#0B2545" />
            <polygon points="46,45 42,100 52,90 65,100 54,45" fill="#D4AF37" />
            <!-- Outer Rosette / Starburst -->
            <circle cx="40" cy="40" r="32" fill="#0B2545" />
            <circle cx="40" cy="40" r="30" fill="#D4AF37" />
            <circle cx="40" cy="40" r="27" fill="#F3E5AB" />
            <circle cx="40" cy="40" r="25" fill="#0B2545" />
            <!-- Star Center -->
            <polygon points="40,24 43,33 52,33 45,39 47,48 40,43 33,48 35,39 28,33 37,33" fill="#D4AF37" />
            <text x="40" y="55" font-family="Helvetica, Arial, sans-serif" font-size="6.5" font-weight="900" fill="#F3E5AB" text-anchor="middle" letter-spacing="1">RESMI</text>
        </svg>
    </div>

    <!-- Double Border Frame -->
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    @endif

    <!-- Main Content -->
    <div class="main-content" style="{{ !empty($contentStyle) ? $contentStyle : '' }}">
        @if(empty($backgroundBase64))
        <!-- Top Institutional Emblem -->
        <svg class="header-emblem" viewBox="0 0 60 46" xmlns="http://www.w3.org/2000/svg">
            <!-- Laurel Wreath Left -->
            <path d="M14 36 C8 30 7 18 18 10 C16 15 17 25 24 32 Z" fill="#D4AF37" />
            <!-- Laurel Wreath Right -->
            <path d="M46 36 C52 30 53 18 42 10 C44 15 43 25 36 32 Z" fill="#D4AF37" />
            <!-- Graduation Cap / Diamond Emblem -->
            <polygon points="30,8 48,16 30,24 12,16" fill="#0B2545" />
            <polygon points="30,10 44,16 30,22 16,16" fill="#D4AF37" />
            <!-- Shield & 9 Stars Motif -->
            <path d="M22 22 L38 22 L38 34 C38 40 30 44 30 44 C30 44 22 40 22 34 Z" fill="#0B2545" />
            <polygon points="30,25 32,30 37,30 33,33 34,38 30,35 26,38 27,33 23,30 28,30" fill="#D4AF37" />
        </svg>

        <div class="inst-title">UNIVERSITAS NAHDLATUL ULAMA SURABAYA</div>
        <div class="org-title">FORUM MAHASISWA BIDIKMISI &amp; KIP KULIAH (SEMADIKSI)</div>

        <div class="cert-heading">SERTIFIKAT</div>
        <div class="cert-subheading">PENGHARGAAN</div>
        @else
        <div style="height: 48mm;"></div>
        @endif

        {{-- PENGHAPUSAN NOMOR CETAK (KRITIS): Teks Nomor Seri Kasat Mata TIDAK DITAMPILKAN --}}

        <div class="cert-intro">Dengan bangga dipersembahkan kepada:</div>

        <div class="recipient-name" style="{{ mb_strlen($namaLengkap) > 30 ? 'font-size: 24pt;' : 'font-size: 30pt;' }}">
            {{ $namaLengkap }}
        </div>

        <!-- Diamond Divider -->
        <table class="divider-table">
            <tr>
                <td class="divider-line"></td>
                <td class="divider-diamond">&#9670;</td>
                <td class="divider-line"></td>
            </tr>
        </table>

        @if(!empty($nim) || !empty($prodi))
        <div class="recipient-meta">
            {{ $nim ? 'NIM. ' . $nim : '' }}{{ $nim && $prodi ? ' • ' : '' }}{{ $prodi ? 'Program Studi ' . $prodi : '' }}
        </div>
        @endif

        <div class="role-label">sebagai</div>
        <div class="role-badge">
            {{ $sertifikat->peran_sertifikat ? $sertifikat->peran_sertifikat->label() : 'PESERTA' }}
        </div>

        <div class="cert-reason">
            Dalam agenda <strong class="event-title">"{{ $kegiatan->nama_kegiatan }}"</strong>@if(!empty($kegiatan->deskripsi)) dengan tema <em>"{{ $kegiatan->deskripsi }}"</em>@endif yang diselenggarakan oleh SEMADIKSI Universitas Nahdlatul Ulama Surabaya pada tanggal {{ $tanggalKegiatan }}.
        </div>

        <!-- Dual Signatures Table -->
        <table class="footer-table">
            <tr>
                <td class="sign-cell">
                    <div class="sign-date">Surabaya, {{ $tanggalTerbit }}</div>
                    <div class="sign-title">Ketua Umum SEMADIKSI</div>
                    <div class="sign-space">
                        <span style="font-family: 'Times New Roman', 'Georgia', cursive; font-size: 16pt; color: #0B2545; font-style: italic; letter-spacing: 1px;">M. Fatih</span>
                    </div>
                    <div class="sign-name">Muhammad Fatih</div>
                    <div class="sign-role">Ketua Umum Pengurus Pusat</div>
                </td>
                <td style="width: 16%;"></td>
                <td class="sign-cell">
                    <div class="sign-date">Surabaya, {{ $tanggalTerbit }}</div>
                    <div class="sign-title">Ketua Pelaksana</div>
                    <div class="sign-space">
                        <span style="font-family: 'Times New Roman', 'Georgia', cursive; font-size: 16pt; color: #0B2545; font-style: italic; letter-spacing: 1px;">Ferdyant R.</span>
                    </div>
                    <div class="sign-name">Ferdyant Rahmansyah</div>
                    <div class="sign-role">Ketua Panitia Pelaksana</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- QR Code Widget (Bottom Right) -->
    <div class="qr-widget">
        <img class="qr-image" src="{{ $qrCodeBase64 }}" alt="QR Code Validasi">
        <div class="qr-label">Pindai untuk Validasi</div>
    </div>

    <!-- SHA-256 Document Hash (Bottom Left) -->
    <div class="doc-hash">
        SHA-256 DOC HASH: {{ $sertifikat->document_hash ?? 'VALIDATED' }} &bull; PORTAL VERIFIKASI RESMI SEMADIKSI UNUSA
    </div>
</body>
</html>
