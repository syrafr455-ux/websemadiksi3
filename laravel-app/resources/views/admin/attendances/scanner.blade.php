@extends('layouts.admin')

@section('title', 'Pemindai QR Tiket: ' . $kegiatan->nama_kegiatan)
@section('pageTitle', 'Pemindai QR Tiket Presensi Awal')
@section('pageSubtitle', 'Validasi e-tiket masuk kegiatan ' . $kegiatan->nama_kegiatan)

@section('content')
<div class="p-6 md:p-8 space-y-6" x-data="{
    manualModal: false,
    cancelModal: false,
    selectedRegId: null,
    selectedParticipantName: '',
    selectedTicketCode: '',
    manualReason: '',
    cancelReason: '',
    autoRefocus: true
}">
    {{-- Header Navigasi & Statistik Cepat --}}
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 bg-slate-950 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.attendances', ['kegiatan_id' => $kegiatan->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-semibold border border-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Kembali ke Presensi
                </a>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold border border-emerald-500/20">
                    <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                    Fase 1: Presensi Awal
                </span>
            </div>
            <h2 class="text-2xl font-black text-white mt-1">{{ $kegiatan->nama_kegiatan }}</h2>
            <p class="text-xs text-slate-400">
                Pindai QR e-tiket menggunakan kamera, pemindai barcode USB/Bluetooth, atau masukkan kode tiket secara manual.
            </p>
        </div>

        {{-- Counter Live --}}
        <div class="flex items-center gap-4 bg-slate-900 border border-slate-800 px-5 py-3 rounded-2xl">
            <div class="text-right">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Progres Kehadiran</div>
                <div class="text-xl font-black text-emerald-400">
                    <span id="counterCheckedIn">{{ $totalCheckedIn }}</span> <span class="text-slate-500 text-sm font-semibold">/ {{ $totalRegistered }}</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-2xl">how_to_reg</span>
            </div>
        </div>
    </div>

    {{-- Peringatan HTTPS jika diakses non-localhost --}}
    <div id="httpsWarning" class="hidden p-4 rounded-2xl bg-amber-950/80 border border-amber-800 text-amber-200 text-xs flex items-center gap-3">
        <span class="material-symbols-outlined text-amber-400 text-xl">warning</span>
        <div>
            <strong>Perhatian:</strong> Halaman ini diakses melalui protokol HTTP biasa. Peramban modern pada perangkat seluler membatasi izin kamera hanya untuk koneksi aman HTTPS atau localhost. Jika kamera tidak dapat dibuka, gunakan pemindai USB/keyboard atau pastikan HTTPS aktif.
        </div>
    </div>

    {{-- Error Banner Izin Kamera --}}
    <div id="cameraErrorBanner" class="hidden p-4 rounded-2xl bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-center gap-3">
        <span class="material-symbols-outlined text-rose-400 text-xl">videocam_off</span>
        <div id="cameraErrorMessage">
            Izin kamera belum diberikan atau perangkat tidak mendukung. Anda tetap dapat menggunakan input kode tiket di bawah.
        </div>
    </div>

    {{-- Layout Utama: Kamera & Kartu Hasil Pindai --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Sisi Kiri: Viewport Kamera & Pemindai Keyboard (7 Kolom) --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-400">photo_camera</span>
                        <h3 class="text-sm font-bold text-white">Kamera Pemindai QR</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <select id="cameraSelect" class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-amber-500">
                            <option value="environment">Kamera Belakang</option>
                            <option value="user">Kamera Depan</option>
                        </select>
                        <button id="toggleCameraBtn" type="button" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs flex items-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-sm">pause</span>
                            <span id="cameraBtnText">Jeda</span>
                        </button>
                    </div>
                </div>

                {{-- Viewport Kamera --}}
                <div class="relative bg-black rounded-2xl overflow-hidden aspect-video max-h-[380px] flex items-center justify-center border border-slate-800">
                    <video id="scannerVideo" class="w-full h-full object-cover" playsinline autoplay muted></video>
                    <canvas id="scannerCanvas" class="hidden"></canvas>

                    {{-- Panduan Visual Kotak Pindai --}}
                    <div id="scanOverlay" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="w-56 h-56 border-2 border-emerald-400/80 rounded-2xl relative shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                            <div class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-emerald-400 -mt-1 -ml-1"></div>
                            <div class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-emerald-400 -mt-1 -mr-1"></div>
                            <div class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-emerald-400 -mb-1 -ml-1"></div>
                            <div class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-emerald-400 -mb-1 -mr-1"></div>
                            <div id="scanLaser" class="w-full h-0.5 bg-emerald-400 absolute top-0 shadow-[0_0_8px_#34d399] animate-pulse"></div>
                        </div>
                    </div>

                    {{-- Loading / State Overlay --}}
                    <div id="cameraLoading" class="absolute inset-0 bg-slate-950 flex flex-col items-center justify-center text-slate-400 space-y-2">
                        <span class="material-symbols-outlined text-3xl animate-spin text-amber-500">progress_activity</span>
                        <span class="text-xs font-semibold">Menghubungkan kamera...</span>
                    </div>
                </div>

                {{-- Cadangan Input Kode Tiket (Keyboard / USB Scanner Barcode) --}}
                <div class="space-y-2 pt-2 border-t border-slate-800">
                    <div class="flex items-center justify-between text-xs">
                        <label for="ticketInput" class="font-bold text-slate-300 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-amber-400 text-base">keyboard</span>
                            Input Kode Tiket / Pemindai USB
                        </label>
                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-400 cursor-pointer">
                            <input type="checkbox" id="autoRefocusToggle" checked @change="autoRefocus = $event.target.checked" class="rounded bg-slate-900 border-slate-700 text-amber-600 focus:ring-0">
                            Fokus otomatis
                        </label>
                    </div>
                    <form id="ticketInputForm" class="flex items-center gap-2" onsubmit="event.preventDefault(); submitTicketInput();">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-500 text-base">qr_code</span>
                            <input 
                                type="text" 
                                id="ticketInput" 
                                placeholder="Pindai barcode USB atau ketik format TKT-... lalu Enter" 
                                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-amber-500 font-mono font-medium"
                                autocomplete="off"
                                autofocus
                            >
                        </div>
                        <button type="submit" id="btnSubmitInput" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-lg shadow-amber-600/20">
                            <span class="material-symbols-outlined text-sm">check</span>
                            Kirim
                        </button>
                    </form>
                    <p class="text-[11px] text-slate-500">
                        Mendukung pemindai barcode fisik USB/Bluetooth (mode keyboard wedge). Kursor otomatis siap menerima input setiap saat.
                    </p>
                </div>
            </div>
        </div>

        {{-- Sisi Kanan: Kartu Hasil Pemindaian Besar (5 Kolom) --}}
        <div class="lg:col-span-5 space-y-4">
            <div id="resultCard" class="bg-slate-950 border border-slate-800 rounded-3xl p-6 shadow-xl min-h-[460px] flex flex-col justify-between transition-all duration-300">
                {{-- State Default (Menunggu Pindai) --}}
                <div id="resultIdle" class="flex-1 flex flex-col items-center justify-center text-center p-8 space-y-3">
                    <div class="w-20 h-20 rounded-3xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500">
                        <span class="material-symbols-outlined text-4xl">document_scanner</span>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-white">Siap Memindai E-Tiket</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-xs">
                            Arahkan kode QR tiket ke kamera atau tempelkan pemindai barcode USB ke kode tiket peserta.
                        </p>
                    </div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-slate-800 text-[11px] text-slate-400 font-mono">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        Menunggu pembacaan...
                    </div>
                </div>

                {{-- State Hasil Pemindaian (DITERIMA, SUDAH CHECK-IN, DITOLAK) --}}
                <div id="resultContent" class="hidden flex-1 flex flex-col justify-between space-y-4">
                    <div class="space-y-4">
                        {{-- Badge Header Status --}}
                        <div id="resultBadgeWrapper" class="flex items-center justify-between pb-3 border-b border-slate-800">
                            <div id="resultBadge" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider">
                                <span id="resultIcon" class="material-symbols-outlined text-base"></span>
                                <span id="resultStatusText"></span>
                            </div>
                            <span id="resultTime" class="text-[11px] font-mono text-slate-400"></span>
                        </div>

                        {{-- Pesan Status Bahasa Indonesia --}}
                        <div id="resultMessage" class="text-sm font-semibold text-white"></div>

                        {{-- Rincian Data Peserta --}}
                        <div id="resultDetails" class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Nama Peserta</span>
                                <span id="detailName" class="font-bold text-white text-right"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">NIM / Identitas</span>
                                <span id="detailNim" class="font-mono text-amber-400 text-right"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Nomor Kursi</span>
                                <span id="detailSeat" class="font-bold text-cyan-400 text-right"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Kode Tiket</span>
                                <span id="detailTicket" class="font-mono text-slate-300 text-right"></span>
                            </div>
                            <div id="detailExtraWrapper" class="hidden pt-2 border-t border-slate-800 flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Petugas / Waktu Check-In</span>
                                <span id="detailExtra" class="text-slate-300 font-mono"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Countdown Otomatis Siap Memindai Lagi --}}
                    <div class="pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                        <span class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-amber-500 animate-spin">refresh</span>
                            Siap pindai berikutnya dalam 2 detik
                        </span>
                        <button type="button" onclick="resetResultCard()" class="px-3 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 font-semibold text-[11px] border border-slate-800 transition-colors">
                            Pindai Sekarang
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat 10 Pemindaian Terakhir --}}
    <div class="bg-slate-950 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
        <div class="p-6 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white">10 Riwayat Pemindaian Terakhir</h3>
                <p class="text-xs text-slate-400 mt-0.5">Catatan check-in awal dan audit pembatalan untuk kegiatan ini.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-5 py-3.5">Waktu (WIB)</th>
                        <th class="px-5 py-3.5">Aksi / Status</th>
                        <th class="px-5 py-3.5">Kode Tiket</th>
                        <th class="px-5 py-3.5">Keterangan</th>
                        <th class="px-5 py-3.5">Metode</th>
                        <th class="px-5 py-3.5">Petugas</th>
                        <th class="px-5 py-3.5 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody id="recentLogsTbody" class="divide-y divide-slate-800 text-slate-300">
                    @forelse($recentLogs as $log)
                        @php
                            $meta = (array) ($log->metadata ?? []);
                            $ticketCode = $meta['ticket_code'] ?? '-';
                            $regId = $log->subject_id;
                            $method = $meta['method'] ?? '-';
                            $isCheckin = in_array($log->action, ['ticket_checkin', 'ticket_checkin_manual'], true);
                        @endphp
                        <tr class="hover:bg-slate-900/50 transition-colors" id="log-row-{{ $log->id }}">
                            <td class="px-5 py-4 font-mono text-slate-400">
                                {{ \App\Support\Wib::dari($log->created_at)->translatedFormat('d M Y, H:i:s') }}
                            </td>
                            <td class="px-5 py-4">
                                @if($log->action === 'ticket_checkin')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                        Check-In Kamera/Scan
                                    </span>
                                @elseif($log->action === 'ticket_checkin_manual')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">person_check</span>
                                        Check-In Manual
                                    </span>
                                @elseif($log->action === 'ticket_checkin_cancelled')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">cancel</span>
                                        Dibatalkan
                                    </span>
                                @else
                                    <span class="text-slate-400">{{ $log->action }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-mono text-amber-400 font-bold">
                                {{ $ticketCode }}
                            </td>
                            <td class="px-5 py-4 text-slate-300 max-w-xs truncate" title="{{ $log->description }}">
                                {{ $log->description }}
                            </td>
                            <td class="px-5 py-4 uppercase text-[11px] font-mono text-slate-400">
                                {{ $method }}
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                {{ $log->user?->name ?? 'Sistem' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if($isCheckin && $regId)
                                    <button 
                                        type="button" 
                                        @click="
                                            cancelModal = true;
                                            selectedRegId = {{ $regId }};
                                            selectedTicketCode = '{{ addslashes($ticketCode) }}';
                                            cancelReason = '';
                                        "
                                        class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-800 text-[11px] font-bold transition-colors inline-flex items-center gap-1"
                                    >
                                        <span class="material-symbols-outlined text-xs">undo</span>
                                        Batalkan
                                    </button>
                                @else
                                    <span class="text-slate-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr id="noLogsRow">
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                Belum ada riwayat pemindaian tiket pada kegiatan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabel Cadangan Check-in Manual per Peserta --}}
    <div class="bg-slate-950 border border-slate-800 rounded-3xl overflow-hidden shadow-xl space-y-4 p-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-white">Daftar Peserta &amp; Check-In Manual</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    Gunakan tombol check-in manual apabila peserta mengalami kendala tiket fisik atau gawai dengan mencantumkan alasan wajib.
                </p>
            </div>

            {{-- Form Pencarian Peserta --}}
            <form action="{{ route('admin.kegiatan.scanner', $kegiatan->id) }}" method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-500 text-base">search</span>
                    <input 
                        type="text" 
                        name="q" 
                        value="{{ request('q') }}" 
                        placeholder="Cari nama, NIM, atau tiket..." 
                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-amber-500"
                    >
                </div>
                <button type="submit" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-colors">
                    Cari
                </button>
                @if(request('q'))
                    <a href="{{ route('admin.kegiatan.scanner', $kegiatan->id) }}" class="p-2 rounded-xl bg-slate-900 text-slate-400 hover:bg-slate-800 text-xs font-bold" title="Reset">
                        <span class="material-symbols-outlined text-sm">restart_alt</span>
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto -mx-6">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-5 py-3.5">Peserta</th>
                        <th class="px-5 py-3.5">Tiket &amp; Kursi</th>
                        <th class="px-5 py-3.5">Status Kehadiran</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($participants as $reg)
                        @php
                            $isCheckedIn = ($reg->status_kehadiran === \App\Enums\AttendanceStatus::CheckedIn || $reg->status_kehadiran?->value === 'checked_in');
                            $name = $reg->participant_name ?: ($reg->participantUser?->name ?? 'Peserta');
                            $nim = $reg->participant_identity ?: ($reg->participantUser?->nim ?? '-');
                            $maskedNim = strlen($nim) > 4 ? substr($nim, 0, 2) . str_repeat('*', strlen($nim) - 4) . substr($nim, -2) : $nim;
                            $seat = $reg->seat?->seat_code ?? 'Tanpa Kursi';
                        @endphp
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-white">{{ $name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">NIM: {{ $maskedNim }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-mono text-amber-400 font-bold">{{ $reg->nomor_tiket }}</div>
                                <div class="text-[11px] text-slate-500">{{ $seat }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($isCheckedIn)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                        Sudah Check-In
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span>
                                        Belum Check-In
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if(! $isCheckedIn)
                                    <button 
                                        type="button" 
                                        @click="
                                            manualModal = true;
                                            selectedRegId = {{ $reg->id }};
                                            selectedParticipantName = '{{ addslashes($name) }}';
                                            selectedTicketCode = '{{ addslashes($reg->nomor_tiket) }}';
                                            manualReason = '';
                                        "
                                        class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs inline-flex items-center gap-1 transition-colors shadow-md shadow-amber-600/20"
                                    >
                                        <span class="material-symbols-outlined text-sm">how_to_reg</span>
                                        Check-in manual
                                    </button>
                                @else
                                    <button 
                                        type="button" 
                                        @click="
                                            cancelModal = true;
                                            selectedRegId = {{ $reg->id }};
                                            selectedTicketCode = '{{ addslashes($reg->nomor_tiket) }}';
                                            cancelReason = '';
                                        "
                                        class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-800 text-xs font-bold transition-colors inline-flex items-center gap-1"
                                    >
                                        <span class="material-symbols-outlined text-sm">undo</span>
                                        Batalkan
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-center text-slate-500">
                                Tidak ada data pendaftar yang cocok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4 border-t border-slate-800">
            {{ $participants->links() }}
        </div>
    </div>

    {{-- Modal Check-In Manual dengan Alasan Wajib --}}
    <div 
        x-show="manualModal" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <div @click.away="manualModal = false" class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500">how_to_reg</span>
                    <h3 class="text-sm font-bold text-white">Check-In Manual Peserta</h3>
                </div>
                <button type="button" @click="manualModal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-900">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="text-xs text-slate-300 space-y-2">
                <p>Peserta: <strong class="text-white" x-text="selectedParticipantName"></strong></p>
                <p>Nomor Tiket: <code class="text-amber-400 font-mono" x-text="selectedTicketCode"></code></p>
                <p class="text-slate-400 text-[11px]">
                    Sesuai kebijakan audit, tindakan check-in manual mewajibkan alasan jelas berbahasa Indonesia.
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="manualReasonInput" class="block text-xs font-bold text-slate-300">
                    Alasan Check-In Manual <span class="text-rose-400">*</span>
                </label>
                <textarea 
                    id="manualReasonInput"
                    x-model="manualReason" 
                    rows="3" 
                    placeholder="Contoh: Peserta mengalami kendala gawai kehabisan daya, identitas KTM fisik terverifikasi oleh panitia."
                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500 font-medium"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" @click="manualModal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs">
                    Batal
                </button>
                <button 
                    type="button" 
                    @click="executeManualCheckIn(selectedRegId, manualReason); manualModal = false;" 
                    :disabled="!manualReason.trim()"
                    class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs transition-colors shadow-lg shadow-amber-600/20"
                >
                    Konfirmasi Check-In
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Pembatalan Check-In dengan Alasan Wajib --}}
    <div 
        x-show="cancelModal" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <div @click.away="cancelModal = false" class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-rose-500">undo</span>
                    <h3 class="text-sm font-bold text-white">Batalkan Check-In Tiket</h3>
                </div>
                <button type="button" @click="cancelModal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-900">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="text-xs text-slate-300 space-y-2">
                <p>Nomor Tiket: <code class="text-amber-400 font-mono" x-text="selectedTicketCode"></code></p>
                <p class="text-rose-300 text-[11px]">
                    Status kehadiran akan dikembalikan ke 'Terdaftar'. Tindakan ini tercatat di audit log.
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="cancelReasonInput" class="block text-xs font-bold text-slate-300">
                    Alasan Pembatalan <span class="text-rose-400">*</span>
                </label>
                <textarea 
                    id="cancelReasonInput"
                    x-model="cancelReason" 
                    rows="3" 
                    placeholder="Contoh: Salah pemindaian e-tiket oleh petugas pintu gerbang, tiket dibatalkan atas permintaan panitia."
                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-rose-500 font-medium"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" @click="cancelModal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs">
                    Tutup
                </button>
                <button 
                    type="button" 
                    @click="executeCancelCheckIn(selectedRegId, cancelReason); cancelModal = false;" 
                    :disabled="!cancelReason.trim()"
                    class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs transition-colors shadow-lg shadow-rose-600/20"
                >
                    Batalkan Check-In
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Skrip Interaktif Pemindai Kamera & Audio Feedback --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scanEndpoint = "{{ route('admin.kegiatan.scanner.scan', $kegiatan->id) }}";
    const manualEndpoint = "{{ route('admin.kegiatan.scanner.manual', $kegiatan->id) }}";
    const cancelEndpoint = "{{ route('admin.kegiatan.scanner.cancel', $kegiatan->id) }}";
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const video = document.getElementById('scannerVideo');
    const canvas = document.getElementById('scannerCanvas');
    const cameraSelect = document.getElementById('cameraSelect');
    const toggleCameraBtn = document.getElementById('toggleCameraBtn');
    const cameraBtnText = document.getElementById('cameraBtnText');
    const cameraLoading = document.getElementById('cameraLoading');
    const cameraErrorBanner = document.getElementById('cameraErrorBanner');
    const cameraErrorMessage = document.getElementById('cameraErrorMessage');
    const httpsWarning = document.getElementById('httpsWarning');
    const ticketInput = document.getElementById('ticketInput');
    const autoRefocusToggle = document.getElementById('autoRefocusToggle');

    let stream = null;
    let cameraRunning = false;
    let isProcessing = false;
    let autoResetTimer = null;
    let audioCtx = null;
    let barcodeDetector = null;

    // 1. Cek HTTPS
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        httpsWarning.classList.remove('hidden');
    }

    // 2. Inisialisasi Audio Synthesizer (Web Audio API)
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playBeep(type) {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            const now = ctx.currentTime;

            if (type === 'success') {
                // Suara nada tinggi ceria
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, now);
                osc.frequency.exponentialRampToValueAtTime(1200, now + 0.15);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
                osc.start(now);
                osc.stop(now + 0.2);
            } else if (type === 'warning') {
                // Suara nada ganda
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(600, now);
                osc.frequency.setValueAtTime(450, now + 0.1);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
                osc.start(now);
                osc.stop(now + 0.25);
            } else {
                // Suara nada rendah galat
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(250, now);
                osc.frequency.exponentialRampToValueAtTime(150, now + 0.25);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.3);
                osc.start(now);
                osc.stop(now + 0.3);
            }
        } catch (e) {
            console.warn('Audio feedback tidak tersedia:', e);
        }

        // Haptic feedback (getaran gawai)
        if (navigator.vibrate) {
            try {
                if (type === 'success') {
                    navigator.vibrate([80]);
                } else if (type === 'warning') {
                    navigator.vibrate([60, 40, 60]);
                } else {
                    navigator.vibrate([150, 50, 150]);
                }
            } catch (e) {}
        }
    }

    // 3. Inisialisasi Detektor Barcode (BarcodeDetector jika ada, jika tidak jsQR)
    if ('BarcodeDetector' in window) {
        try {
            barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch (e) {
            barcodeDetector = null;
        }
    }

    // 4. Pengelolaan Kamera
    async function startCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }

        cameraLoading.classList.remove('hidden');
        cameraErrorBanner.classList.add('hidden');

        const facingMode = cameraSelect.value || 'environment';
        const constraints = {
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;
            await video.play();
            cameraRunning = true;
            cameraLoading.classList.add('hidden');
            cameraBtnText.textContent = 'Jeda';
            requestAnimationFrame(scanFrame);
        } catch (err) {
            cameraRunning = false;
            cameraLoading.classList.add('hidden');
            cameraErrorBanner.classList.remove('hidden');
            cameraBtnText.textContent = 'Mulai';

            let msg = 'Tidak dapat membuka kamera. ';
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                msg += 'Izin akses kamera ditolak. Silakan izinkan akses kamera di pengaturan peramban Anda, atau gunakan kolom input kode tiket di bawah.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                msg += 'Perangkat kamera tidak ditemukan pada sistem ini.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                msg += 'Kamera sedang digunakan oleh aplikasi lain.';
            } else {
                msg += (err.message || 'Pastikan peramban memiliki izin mengakses kamera.');
            }
            cameraErrorMessage.textContent = msg;
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        cameraRunning = false;
        cameraBtnText.textContent = 'Mulai';
    }

    toggleCameraBtn.addEventListener('click', function () {
        if (cameraRunning) {
            stopCamera();
        } else {
            startCamera();
        }
    });

    cameraSelect.addEventListener('change', function () {
        if (cameraRunning) {
            startCamera();
        }
    });

    // 5. Loop Pemindaian Frame Kamera
    async function scanFrame() {
        if (!cameraRunning || isProcessing) {
            if (cameraRunning) {
                setTimeout(() => requestAnimationFrame(scanFrame), 100);
            }
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            let detectedText = null;

            // Prioritas A: BarcodeDetector bawaan browser jika ada
            if (barcodeDetector) {
                try {
                    const barcodes = await barcodeDetector.detect(video);
                    if (barcodes.length > 0 && barcodes[0].rawValue) {
                        detectedText = barcodes[0].rawValue;
                    }
                } catch (e) {
                    // Fallback ke jsQR jika BarcodeDetector gagal
                }
            }

            // Prioritas B: jsQR via HTML5 Canvas jika BarcodeDetector tidak tersedia
            if (!detectedText && window.jsQR) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert'
                });
                if (code && code.data) {
                    detectedText = code.data;
                }
            }

            if (detectedText) {
                handleDetectedCode(detectedText, 'kamera');
                return;
            }
        }

        requestAnimationFrame(scanFrame);
    }

    // 6. Pengiriman Kode ke Server
    async function handleDetectedCode(rawInput, method) {
        if (isProcessing) return;
        isProcessing = true;

        clearTimeout(autoResetTimer);

        try {
            const response = await fetch(scanEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    ticket_code: rawInput,
                    method: method
                })
            });

            const data = await response.json();
            renderScanResult(data);

            if (data.status === 'DITERIMA') {
                playBeep('success');
                updateCounter(1);
                prependLogEntry(data, method);
            } else if (data.status === 'SUDAH_CHECK_IN') {
                playBeep('warning');
            } else {
                playBeep('error');
            }
        } catch (err) {
            renderScanResult({
                status: 'DITOLAK',
                success: false,
                message: 'Gagal terhubung ke peladen: ' + (err.message || 'Periksa koneksi jaringan Anda.')
            });
            playBeep('error');
        } finally {
            // Auto reset setelah 2 detik
            autoResetTimer = setTimeout(() => {
                resetResultCard();
                isProcessing = false;
                if (cameraRunning) {
                    requestAnimationFrame(scanFrame);
                }
                refocusTicketInput();
            }, 2000);
        }
    }

    // 7. Render Tampilan Kartu Hasil
    function renderScanResult(data) {
        const resultIdle = document.getElementById('resultIdle');
        const resultContent = document.getElementById('resultContent');
        const resultBadge = document.getElementById('resultBadge');
        const resultIcon = document.getElementById('resultIcon');
        const resultStatusText = document.getElementById('resultStatusText');
        const resultMessage = document.getElementById('resultMessage');
        const resultTime = document.getElementById('resultTime');
        const detailName = document.getElementById('detailName');
        const detailNim = document.getElementById('detailNim');
        const detailSeat = document.getElementById('detailSeat');
        const detailTicket = document.getElementById('detailTicket');
        const detailExtraWrapper = document.getElementById('detailExtraWrapper');
        const detailExtra = document.getElementById('detailExtra');
        const resultCard = document.getElementById('resultCard');

        resultIdle.classList.add('hidden');
        resultContent.classList.remove('hidden');

        const now = new Date();
        resultTime.textContent = now.toLocaleTimeString('id-ID') + ' WIB';
        resultMessage.textContent = data.message || '';

        // Reset badge styles
        resultBadge.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider';
        resultCard.classList.remove('border-emerald-500', 'border-amber-500', 'border-rose-500');

        if (data.status === 'DITERIMA') {
            resultBadge.classList.add('bg-emerald-500/20', 'text-emerald-400', 'border', 'border-emerald-500/40');
            resultCard.classList.add('border-emerald-500');
            resultIcon.textContent = 'check_circle';
            resultStatusText.textContent = 'DITERIMA';
        } else if (data.status === 'SUDAH_CHECK_IN') {
            resultBadge.classList.add('bg-amber-500/20', 'text-amber-400', 'border', 'border-amber-500/40');
            resultCard.classList.add('border-amber-500');
            resultIcon.textContent = 'history';
            resultStatusText.textContent = 'SUDAH CHECK-IN';
        } else {
            resultBadge.classList.add('bg-rose-500/20', 'text-rose-400', 'border', 'border-rose-500/40');
            resultCard.classList.add('border-rose-500');
            resultIcon.textContent = 'cancel';
            resultStatusText.textContent = 'DITOLAK';
        }

        detailName.textContent = data.participant_name || '-';
        detailNim.textContent = data.participant_identity_masked || '-';
        detailSeat.textContent = data.seat_code || 'Tanpa Kursi';
        detailTicket.textContent = data.ticket_code || '-';

        if (data.officer_name || data.checked_in_at) {
            detailExtraWrapper.classList.remove('hidden');
            detailExtra.textContent = (data.officer_name || 'Petugas') + ' • ' + (data.checked_in_at ? new Date(data.checked_in_at).toLocaleTimeString('id-ID') : '');
        } else {
            detailExtraWrapper.classList.add('hidden');
        }
    }

    window.resetResultCard = function () {
        document.getElementById('resultIdle').classList.remove('hidden');
        document.getElementById('resultContent').classList.add('hidden');
        const resultCard = document.getElementById('resultCard');
        resultCard.classList.remove('border-emerald-500', 'border-amber-500', 'border-rose-500');
    };

    function updateCounter(delta) {
        const counter = document.getElementById('counterCheckedIn');
        if (counter) {
            const current = parseInt(counter.textContent.trim(), 10) || 0;
            counter.textContent = current + delta;
        }
    }

    function prependLogEntry(data, method) {
        const tbody = document.getElementById('recentLogsTbody');
        const noLogsRow = document.getElementById('noLogsRow');
        if (noLogsRow) noLogsRow.remove();

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-900/50 transition-colors bg-emerald-950/20';

        const now = new Date();
        const timeStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + now.toLocaleTimeString('id-ID');

        tr.innerHTML = `
            <td class="px-5 py-4 font-mono text-slate-400">${timeStr}</td>
            <td class="px-5 py-4">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">check_circle</span>
                    Check-In Baru
                </span>
            </td>
            <td class="px-5 py-4 font-mono text-amber-400 font-bold">${data.ticket_code || '-'}</td>
            <td class="px-5 py-4 text-slate-300 max-w-xs truncate">${data.participant_name || ''} (${data.seat_code || ''})</td>
            <td class="px-5 py-4 uppercase text-[11px] font-mono text-slate-400">${method}</td>
            <td class="px-5 py-4 text-slate-300">${data.officer_name || 'Petugas'}</td>
            <td class="px-5 py-4 text-right">
                <button type="button" onclick="openCancelModalFromLog(${data.registration_id}, '${data.ticket_code}')" class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-800 text-[11px] font-bold transition-colors inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">undo</span>
                    Batalkan
                </button>
            </td>
        `;

        if (tbody.firstChild) {
            tbody.insertBefore(tr, tbody.firstChild);
        } else {
            tbody.appendChild(tr);
        }
    }

    // 8. Penanganan Input Keyboard / USB Barcode Scanner
    window.submitTicketInput = function () {
        const val = ticketInput.value.trim();
        if (!val) return;
        ticketInput.value = '';
        handleDetectedCode(val, 'keyboard');
    };

    function refocusTicketInput() {
        if (!autoRefocusToggle.checked) return;
        const active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active !== ticketInput))) {
            return;
        }
        ticketInput.focus();
    }

    document.addEventListener('click', function (e) {
        if (!autoRefocusToggle.checked) return;
        if (e.target.closest('input, textarea, select, button, a')) return;
        refocusTicketInput();
    });

    // 9. Tindakan Check-In Manual & Pembatalan Check-In
    window.executeManualCheckIn = async function (regId, reason) {
        try {
            const response = await fetch(manualEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    registration_id: regId,
                    alasan: reason
                })
            });

            const data = await response.json();
            renderScanResult(data);

            if (data.status === 'DITERIMA') {
                playBeep('success');
                updateCounter(1);
                prependLogEntry(data, 'manual');
            } else {
                playBeep('error');
            }
        } catch (err) {
            alert('Gagal memproses check-in manual: ' + err.message);
        }
    };

    window.executeCancelCheckIn = async function (regId, reason) {
        try {
            const response = await fetch(cancelEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    registration_id: regId,
                    alasan: reason
                })
            });

            const data = await response.json();
            if (data.success) {
                alert(data.message || 'Check-in berhasil dibatalkan.');
                window.location.reload();
            } else {
                alert('Gagal membatalkan check-in: ' + (data.message || 'Terjadi galat.'));
            }
        } catch (err) {
            alert('Gagal membatalkan check-in: ' + err.message);
        }
    };

    window.openCancelModalFromLog = function (regId, ticketCode) {
        const alpineRoot = document.querySelector('[x-data]');
        if (alpineRoot && alpineRoot._x_dataStack) {
            const d = alpineRoot._x_dataStack[0];
            d.selectedRegId = regId;
            d.selectedTicketCode = ticketCode;
            d.cancelReason = '';
            d.cancelModal = true;
        }
    };

    // Mulai kamera otomatis
    startCamera();
});
</script>
@endsection
