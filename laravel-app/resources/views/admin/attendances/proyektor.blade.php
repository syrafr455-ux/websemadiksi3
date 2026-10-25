<!DOCTYPE html>
<html lang="id" class="h-full antialiased font-sans bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Layar Proyektor Presensi Akhir' }} - SEMADIKSI UNUSA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col justify-between p-6 md:p-10 select-none overflow-x-hidden">
    {{-- Header Layar Proyektor --}}
    <header class="flex items-center justify-between border-b border-slate-800/80 pb-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-amber-600 flex items-center justify-center text-white shadow-lg shadow-amber-600/30">
                <span class="material-symbols-outlined text-3xl">verified</span>
            </div>
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Sesi Presensi Akhir &bull; Fase 2
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white mt-1 tracking-tight">
                    {{ $kegiatan->nama_kegiatan }}
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            {{-- Tombol Layar Penuh --}}
            <button 
                id="fullscreenBtn" 
                type="button" 
                class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs flex items-center gap-2 border border-slate-800 transition-colors"
            >
                <span id="fullscreenIcon" class="material-symbols-outlined text-base">fullscreen</span>
                <span id="fullscreenText">Layar Penuh</span>
            </button>

            {{-- Tombol Kembali --}}
            <a 
                href="{{ route('admin.attendances', ['kegiatan_id' => $kegiatan->id]) }}" 
                class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white font-bold text-xs flex items-center gap-1.5 border border-slate-800 transition-colors"
            >
                <span class="material-symbols-outlined text-base">close</span>
                Tutup Layar
            </a>
        </div>
    </header>

    {{-- Konten Utama: QR Code Besar & Live Counter --}}
    <main class="flex-1 flex flex-col lg:flex-row items-center justify-center gap-10 lg:gap-16 py-8">
        {{-- Sisi Kiri: Kotak QR Code Dinamis --}}
        <div class="flex flex-col items-center space-y-5">
            <div class="relative p-6 rounded-3xl bg-white shadow-[0_0_50px_rgba(245,158,11,0.15)] border-4 border-amber-500/40">
                {{-- Kontainer SVG QR Code --}}
                <div id="qrContainer" class="w-[300px] h-[300px] sm:w-[380px] sm:h-[380px] flex items-center justify-center">
                    <div class="animate-spin text-slate-400">
                        <span class="material-symbols-outlined text-5xl">progress_activity</span>
                    </div>
                </div>

                {{-- Status Watermark Dinamis --}}
                <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-slate-900 text-amber-400 text-[11px] font-mono font-bold border border-slate-800 whitespace-nowrap shadow-lg flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-xs text-emerald-400">lock</span>
                    HMAC Terverifikasi
                </div>
            </div>

            {{-- Progress Bar Hitung Mundur 30 Detik --}}
            <div class="w-full max-w-sm space-y-1.5 text-center">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-400 px-1">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm text-amber-400">update</span>
                        QR diperbarui otomatis
                    </span>
                    <span id="countdownText" class="font-mono text-amber-400 font-bold">30s</span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-900 border border-slate-800 overflow-hidden">
                    <div id="progressBar" class="h-full bg-gradient-to-r from-amber-500 to-emerald-400 transition-all duration-1000 ease-linear" style="width: 100%;"></div>
                </div>
            </div>
        </div>

        {{-- Sisi Kanan: Panduan Peserta & Live Counter Presensi Masuk --}}
        <div class="max-w-md w-full space-y-6">
            {{-- Kartu Live Counter --}}
            <div class="p-6 rounded-3xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Kehadiran Akhir Masuk</span>
                    <span id="sessionBadge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Sesi Dibuka
                    </span>
                </div>

                <div class="flex items-baseline gap-2">
                    <div id="counterFase2" class="text-5xl md:text-6xl font-black text-amber-400 tracking-tight font-mono">
                        {{ $totalPresensiFase2 }}
                    </div>
                    <div class="text-slate-400 font-semibold text-base">
                        dari <span id="counterFase1" class="text-white font-bold">{{ $totalCheckedInFase1 }}</span> peserta check-in
                    </div>
                </div>

                <p class="text-xs text-slate-400">
                    Presensi akhir masuk <strong id="progressSentence" class="text-white">{{ $totalPresensiFase2 }} dari {{ $totalCheckedInFase1 }}</strong> peserta yang hadir pada check-in fase 1.
                </p>
            </div>

            {{-- 3 Langkah Mudah untuk Peserta --}}
            <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800 space-y-3">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-base">info</span>
                    Petunjuk untuk Peserta:
                </h3>

                <ul class="space-y-2.5 text-xs text-slate-300">
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">1</span>
                        <span>Buka kamera ponsel Anda dan arahkan ke kode QR di layar proyektor.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">2</span>
                        <span>Masuk ke akun Anda jika belum login, periksa nama dan data kehadiran.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">3</span>
                        <span>Bubuhkan tanda tangan digital pada canvas untuk menerbitkan e-sertifikat Anda.</span>
                    </li>
                </ul>
            </div>

            {{-- Peringatan Check-in Awal --}}
            <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-800/60 text-amber-200 text-xs flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-400 text-lg shrink-0">report</span>
                <span>
                    Syarat presensi akhir: Anda wajib telah tercatat check-in awal di pintu masuk. Jika belum, segera hubungi panitia.
                </span>
            </div>
        </div>
    </main>

    {{-- Footer Info --}}
    <footer class="border-t border-slate-800/80 pt-4 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
        <span>Sistem Informasi SEMADIKSI &bull; Universitas Nahdlatul Ulama Surabaya</span>
        <span>Kode akses manual tidak digunakan demi validitas tanda tangan digital &bull; Waktu server dalam WIB</span>
    </footer>

    {{-- Script Polling Token & Rotasi QR 30 Detik --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tokenApiUrl = "{{ route('admin.kegiatan.presensi-proyektor.token', $kegiatan->id) }}";
        const qrContainer = document.getElementById('qrContainer');
        const countdownText = document.getElementById('countdownText');
        const progressBar = document.getElementById('progressBar');
        const counterFase2 = document.getElementById('counterFase2');
        const counterFase1 = document.getElementById('counterFase1');
        const progressSentence = document.getElementById('progressSentence');
        const sessionBadge = document.getElementById('sessionBadge');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const fullscreenIcon = document.getElementById('fullscreenIcon');
        const fullscreenText = document.getElementById('fullscreenText');

        let secondsRemaining = {{ (int) $initialSeconds }};
        const SLOT_DURATION = 30;

        // Fetch token & QR dari server
        async function fetchTokenData() {
            try {
                const response = await fetch(tokenApiUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) return;

                const data = await response.json();

                // Render QR SVG
                if (data.qr_svg) {
                    qrContainer.innerHTML = data.qr_svg;
                }

                // Update counts
                counterFase1.textContent = data.fase1_count;
                counterFase2.textContent = data.fase2_count;
                progressSentence.textContent = `${data.fase2_count} dari ${data.fase1_count}`;

                // Status badge
                if (data.session_status === 'open') {
                    sessionBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                    sessionBadge.textContent = 'Sesi Dibuka';
                } else if (data.session_status === 'closed') {
                    sessionBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20';
                    sessionBadge.textContent = 'Sesi Ditutup';
                } else {
                    sessionBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700';
                    sessionBadge.textContent = 'Belum Dibuka';
                }

                secondsRemaining = data.seconds_remaining;
                updateProgressBar();
            } catch (err) {
                console.warn('Gagal memuat token proyektor:', err);
            }
        }

        function updateProgressBar() {
            const pct = Math.max(0, Math.min(100, (secondsRemaining / SLOT_DURATION) * 100));
            progressBar.style.width = pct + '%';
            countdownText.textContent = secondsRemaining + 's';
        }

        // Timer hitung mundur tiap 1 detik
        setInterval(() => {
            secondsRemaining--;
            if (secondsRemaining <= 0) {
                secondsRemaining = SLOT_DURATION;
                fetchTokenData();
            } else {
                updateProgressBar();
            }
        }, 1000);

        // Polling data tiap 5 detik untuk memperbarui jumlah kehadiran live
        setInterval(() => {
            if (secondsRemaining > 3) {
                fetch(tokenApiUrl, { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        counterFase1.textContent = data.fase1_count;
                        counterFase2.textContent = data.fase2_count;
                        progressSentence.textContent = `${data.fase2_count} dari ${data.fase1_count}`;
                    })
                    .catch(() => {});
            }
        }, 5000);

        // Fullscreen toggle
        fullscreenBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
                fullscreenIcon.textContent = 'fullscreen_exit';
                fullscreenText.textContent = 'Keluar Layar Penuh';
            } else {
                document.exitFullscreen().catch(() => {});
                fullscreenIcon.textContent = 'fullscreen';
                fullscreenText.textContent = 'Layar Penuh';
            }
        });

        document.addEventListener('fullscreenchange', function () {
            if (!document.fullscreenElement) {
                fullscreenIcon.textContent = 'fullscreen';
                fullscreenText.textContent = 'Layar Penuh';
            } else {
                fullscreenIcon.textContent = 'fullscreen_exit';
                fullscreenText.textContent = 'Keluar Layar Penuh';
            }
        });

        // Load awal
        fetchTokenData();
    });
    </script>
</body>
</html>
