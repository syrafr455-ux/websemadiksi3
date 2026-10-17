<x-layouts.app :title="$title">
    <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        {{-- Breadcrumb & Badge --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('kegiatan.detail', $kegiatan->id) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                Kembali ke Detail Kegiatan
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-sm">draw</span>
                Presensi Mandiri & Tanda Tangan Digital
            </span>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-sm">
                <span class="material-symbols-outlined text-emerald-600 text-xl flex-shrink-0">check_circle</span>
                <div>
                    <strong class="font-bold block">Berhasil!</strong>
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-sm">
                <span class="material-symbols-outlined text-rose-600 text-xl flex-shrink-0">error</span>
                <div>
                    <strong class="font-bold block">Gagal</strong>
                    {{ session('error') }}
                </div>
            </div>
        @endif

        {{-- Event Header Card --}}
        <div class="bg-surface border border-surface-variant/40 rounded-3xl p-6 sm:p-8 shadow-sm mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-surface-variant/30 pb-6 mb-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-outline block mb-1">Kegiatan Resmi</span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-on-surface">{{ $kegiatan->nama_kegiatan }}</h1>
                    <p class="text-sm text-outline mt-1">
                        @if($kegiatan->tanggal_mulai){{ \App\Support\Wib::dari($kegiatan->tanggal_mulai)->translatedFormat('d F Y, H:i') }} WIB @else Tanggal pelaksanaan @endif
                        • {{ $kegiatan->lokasi_nama ?: 'Universitas Nahdlatul Ulama Surabaya' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1.5 rounded-xl bg-teal-50 text-teal-800 border border-teal-200 font-bold text-xs">
                        Tahap 2: Presensi Akhir
                    </span>
                </div>
            </div>

            {{-- Existing Attendance Alert if already attended --}}
            @if($existingPresensi)
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-500/40 rounded-2xl p-6 mb-8 text-emerald-950">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-600/30">
                            <span class="material-symbols-outlined text-2xl">verified_user</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold">Anda Sudah Melakukan Presensi Mandiri</h3>
                            <p class="text-xs text-emerald-800 mt-1">
                                Dicatat pada {{ $existingPresensi->check_in_at ? \App\Support\Wib::dari($existingPresensi->check_in_at)->translatedFormat('d F Y, H:i').' WIB' : '-' }}
                                atas nama <strong>{{ $existingPresensi->nama_lengkap }}</strong> (NIM: {{ $existingPresensi->nim }}).
                            </p>

                            @if($existingPresensi->sertifikat)
                                <div class="mt-4 pt-4 border-t border-emerald-200 flex flex-wrap items-center gap-3">
                                    <span class="text-xs font-mono font-bold bg-white/80 px-2.5 py-1 rounded-lg border border-emerald-300">
                                        {{ $existingPresensi->sertifikat->nomor_sertifikat }}
                                    </span>
                                    <a 
                                        href="{{ $existingPresensi->sertifikat->file_url }}" 
                                        target="_blank" 
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-sm"
                                    >
                                        <span class="material-symbols-outlined text-sm">download</span>
                                        Unduh E-Sertifikat Resmi (PDF)
                                    </a>
                                    <a 
                                        href="{{ $existingPresensi->sertifikat->verification_url }}" 
                                        target="_blank" 
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-emerald-100 text-emerald-900 border border-emerald-300 text-xs font-semibold transition"
                                    >
                                        <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                                        Cek Verifikasi Publik
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Presensi Form --}}
            <form action="{{ route('kegiatan.presensi.submit', $kegiatan->id) }}" method="POST" id="attendanceForm" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Nama Lengkap --}}
                    <div>
                        <label for="nama_lengkap" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nama Lengkap (Sesuai Sertifikat) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nama_lengkap" 
                            id="nama_lengkap" 
                            required 
                            value="{{ old('nama_lengkap', $user?->name) }}"
                            placeholder="Contoh: Muhammad Ali Ridho"
                            class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-medium"
                        >
                    </div>

                    {{-- NIM / Identitas --}}
                    <div>
                        <label for="nim" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            NIM / Nomor Identitas <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nim" 
                            id="nim" 
                            required 
                            value="{{ old('nim', $user?->nim) }}"
                            placeholder="Contoh: 2240021009"
                            class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-medium font-mono"
                        >
                    </div>

                    {{-- Program Studi --}}
                    <div>
                        <label for="prodi" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Program Studi / Instansi <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="prodi" 
                            id="prodi" 
                            required 
                            value="{{ old('prodi', $user?->prodi ?? 'S1 Sistem Informasi') }}"
                            placeholder="Contoh: S1 Sistem Informasi"
                            class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-medium"
                        >
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Alamat Email Aktif <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            required 
                            value="{{ old('email', $user?->email) }}"
                            placeholder="nama@student.unusa.ac.id"
                            class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-medium"
                        >
                    </div>

                    {{-- Nomor Telepon / WA --}}
                    <div class="md:col-span-2">
                        <label for="nomor_telepon" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nomor WhatsApp / Telepon Aktif <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="tel" 
                            name="nomor_telepon" 
                            id="nomor_telepon" 
                            required 
                            value="{{ old('nomor_telepon', $user?->phone ?? '081234567890') }}"
                            placeholder="08123456789"
                            class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-medium font-mono"
                        >
                    </div>
                </div>

                {{-- Touchscreen Signature Canvas Section --}}
                <div class="pt-4 border-t border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">
                            Tanda Tangan Digital (Touchscreen / Mouse) <span class="text-rose-500">*</span>
                        </label>
                        <button 
                            type="button" 
                            id="clearCanvasBtn" 
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200 transition"
                        >
                            <span class="material-symbols-outlined text-sm">ink_eraser</span>
                            Hapus / Ulangi
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mb-3">
                        Gunakan jari Anda pada layar smartphone atau kursor mouse pada desktop untuk membubuhkan tanda tangan resmi di bawah ini.
                    </p>

                    <div class="relative w-full border-2 border-dashed border-teal-600/40 hover:border-teal-600 rounded-2xl bg-white overflow-hidden shadow-inner touch-none">
                        <canvas 
                            id="signatureCanvas" 
                            width="750" 
                            height="240" 
                            class="w-full h-48 sm:h-56 cursor-crosshair block"
                        ></canvas>
                        
                        <div id="canvasPlaceholder" class="pointer-events-none absolute inset-0 flex items-center justify-center text-slate-300 text-xs font-bold tracking-widest uppercase">
                            Bubuhkan Tanda Tangan Anda di Area Ini
                        </div>
                    </div>

                    {{-- Hidden input for base64 signature image --}}
                    <input type="hidden" name="signature" id="signatureInput" required>
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full py-4 rounded-2xl bg-teal-700 hover:bg-teal-800 text-white font-extrabold text-sm shadow-lg shadow-teal-700/20 flex items-center justify-center gap-2 transition hover:scale-[1.01] active:scale-[0.99]"
                    >
                        <span class="material-symbols-outlined text-lg">fact_check</span>
                        Kirim Presensi & Terbitkan E-Sertifikat Resmi
                    </button>
                    <p class="text-center text-xs text-slate-400 mt-2">
                        Dengan menekan tombol di atas, Anda menyatakan bahwa data yang diisi adalah benar dan menyetujui penerbitan sertifikat digital.
                    </p>
                </div>
            </form>
        </div>
    </div>

    {{-- Script Signature Canvas Touch & Pointer Handling --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('signatureCanvas');
            const placeholder = document.getElementById('canvasPlaceholder');
            const clearBtn = document.getElementById('clearCanvasBtn');
            const hiddenInput = document.getElementById('signatureInput');
            const form = document.getElementById('attendanceForm');
            const ctx = canvas.getContext('2d');

            let isDrawing = false;
            let hasDrawn = false;

            // Setup canvas context styling
            ctx.strokeStyle = '#0f172a'; // Deep slate ink
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            function getCoordinates(e) {
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;

                let clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }

                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            }

            function startDrawing(e) {
                e.preventDefault();
                isDrawing = true;
                const coords = getCoordinates(e);
                ctx.beginPath();
                ctx.moveTo(coords.x, coords.y);

                if (!hasDrawn) {
                    hasDrawn = true;
                    if (placeholder) placeholder.style.display = 'none';
                }
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const coords = getCoordinates(e);
                ctx.lineTo(coords.x, coords.y);
                ctx.stroke();
            }

            function stopDrawing(e) {
                if (!isDrawing) return;
                e.preventDefault();
                isDrawing = false;
                ctx.closePath();
            }

            function clearCanvas() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
                hiddenInput.value = '';
                if (placeholder) placeholder.style.display = 'flex';
            }

            // Pointer Events (Mouse, Pen, Touch modern standard)
            canvas.addEventListener('pointerdown', startDrawing);
            canvas.addEventListener('pointermove', draw);
            canvas.addEventListener('pointerup', stopDrawing);
            canvas.addEventListener('pointercancel', stopDrawing);

            // Fallback Touch Events for older mobile WebViews
            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing, { passive: false });

            clearBtn.addEventListener('click', clearCanvas);

            // Form Submit Interception to export base64
            form.addEventListener('submit', function (e) {
                if (!hasDrawn) {
                    e.preventDefault();
                    alert('Silakan bubuhkan tanda tangan digital Anda pada canvas terlebih dahulu!');
                    return;
                }

                // Export to PNG data URL
                const dataUrl = canvas.toDataURL('image/png');
                hiddenInput.value = dataUrl;
            });
        });
    </script>
</x-layouts.app>
