@extends('layouts.admin')

@section('title', 'Presensi & Kehadiran Akhir')
@section('pageTitle', 'Presensi & Kehadiran Akhir')
@section('pageSubtitle', 'Rekapitulasi check-in tiket fase 1, presensi mandiri fase 2, tanda tangan digital, dan sertifikat kegiatan')

@section('content')
<div class="p-6 md:p-8 space-y-6" x-data="{ 
    signatureModal: false, 
    signatureUrl: null, 
    signatureName: '',
    bukaSesiModal: false,
    perpanjangModal: false,
    manualFase2Modal: false,
    selectedFase2RegId: null,
    selectedFase2Name: '',
    fase2Reason: ''
}">
    {{-- Header & Kegiatan Selector --}}
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 bg-slate-950 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                <span class="material-symbols-outlined text-sm">how_to_reg</span>
                Alur Presensi Terpadu Dua Fase
            </div>
            <h2 class="text-2xl font-black text-white">Presensi &amp; Kehadiran Peserta</h2>
            <p class="text-xs text-slate-400">
                Pantau verifikasi e-tiket check-in (fase 1) dan tanda tangan presensi mandiri akhir (fase 2) dalam zona waktu WIB.
            </p>
        </div>

        {{-- Dropdown Pilih Kegiatan --}}
        <div class="w-full lg:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <form action="{{ route('admin.attendances') }}" method="GET" class="flex items-center gap-2">
                <label for="select_kegiatan" class="text-xs font-bold text-slate-400 whitespace-nowrap">Pilih Kegiatan:</label>
                <select 
                    id="select_kegiatan"
                    name="kegiatan_id" 
                    onchange="this.form.submit()" 
                    class="px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs font-medium focus:border-amber-500 focus:outline-none"
                >
                    @forelse($kegiatanList as $k)
                        <option value="{{ $k->id }}" {{ ($selectedKegiatan?->id === $k->id) ? 'selected' : '' }}>
                            {{ $k->nama_kegiatan }} ({{ $k->tanggal_mulai ? \App\Support\Wib::dari($k->tanggal_mulai)->translatedFormat('d M Y') : '-' }})
                        </option>
                    @empty
                        <option value="">-- Belum ada kegiatan --</option>
                    @endforelse
                </select>
            </form>

            @if($selectedKegiatan)
                <a 
                    href="{{ route('admin.kegiatan.scanner', $selectedKegiatan->id) }}"
                    class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 transition-all"
                >
                    <span class="material-symbols-outlined text-base">qr_code_scanner</span>
                    Pindai Tiket
                </a>
                <a 
                    href="{{ route('admin.attendances.export', array_merge(request()->query(), ['kegiatan_id' => $selectedKegiatan->id])) }}"
                    class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-amber-600/20 transition-all"
                >
                    <span class="material-symbols-outlined text-base">download</span>
                    Ekspor Rekap (.csv)
                </a>
            @endif
        </div>
    </div>

    @if($selectedKegiatan)
        {{-- Panel Presensi Akhir (Fase 2) --}}
        <div class="bg-slate-950 border border-slate-800 p-6 rounded-3xl shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Sesi Presensi Akhir (Fase 2)</span>
                        @php
                            $sessionStatus = $selectedKegiatan->statusPresensiAkhir();
                        @endphp
                        @if($sessionStatus === 'open')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Sesi Dibuka
                            </span>
                        @elseif($sessionStatus === 'closed')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">lock</span>
                                Sesi Ditutup
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Belum Dibuka
                            </span>
                        @endif
                    </div>
                    <h3 class="text-lg font-bold text-white">Pengaturan Sesi Presensi Mandiri Akhir &amp; Tanda Tangan</h3>
                    <p class="text-xs text-slate-400">
                        @if($selectedKegiatan->presensi_akhir_mulai && $selectedKegiatan->presensi_akhir_selesai)
                            Jendela aktif: 
                            <strong class="text-slate-200">{{ \App\Support\Wib::dari($selectedKegiatan->presensi_akhir_mulai)->translatedFormat('d M Y, H:i') }} WIB</strong> 
                            s.d. 
                            <strong class="text-slate-200">{{ \App\Support\Wib::dari($selectedKegiatan->presensi_akhir_selesai)->translatedFormat('d M Y, H:i') }} WIB</strong>.
                        @else
                            Jendela waktu default: 15 menit sebelum acara selesai s.d. 60 menit setelah acara selesai.
                        @endif
                    </p>
                </div>

                {{-- Tombol Aksi Sesi Presensi Akhir --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if($sessionStatus !== 'open')
                        <button 
                            type="button" 
                            @click="bukaSesiModal = true"
                            class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-amber-600/20 transition-all"
                        >
                            <span class="material-symbols-outlined text-base">lock_open</span>
                            Buka Presensi Akhir
                        </button>
                    @else
                        <form action="{{ route('admin.kegiatan.presensi-akhir.tutup', $selectedKegiatan->id) }}" method="POST" class="inline">
                            @csrf
                            <button 
                                type="submit" 
                                class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-rose-600/20 transition-all"
                            >
                                <span class="material-symbols-outlined text-base">lock</span>
                                Tutup Sekarang
                            </button>
                        </form>

                        <button 
                            type="button" 
                            @click="perpanjangModal = true"
                            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center gap-2 border border-slate-700 transition-all"
                        >
                            <span class="material-symbols-outlined text-base">more_time</span>
                            Perpanjang
                        </button>
                    @endif

                    <a 
                        href="{{ route('admin.kegiatan.presensi-proyektor', $selectedKegiatan->id) }}" 
                        target="_blank"
                        class="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-cyan-600/20 transition-all"
                    >
                        <span class="material-symbols-outlined text-base">tv</span>
                        Buka Layar Proyektor
                    </a>
                </div>
            </div>
        @if($selectedKegiatan->isDaringOrHybrid())
            {{-- Panel Kontrol Akses Rapat Daring --}}
            <div class="bg-slate-950 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Akses Rapat Daring ({{ ucfirst($selectedKegiatan->platform_daring ?? 'Zoom') }})</span>
                        @php $statusGabung = $selectedKegiatan->statusAksesGabung(); @endphp
                        @if($statusGabung === 'dibuka')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-teal-500/10 text-teal-400 border border-teal-500/20 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                                Akses Terbuka
                            </span>
                        @elseif($statusGabung === 'ditutup')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                Akses Ditutup
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Belum Dibuka
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400">
                        Mode tombol gabung: <strong class="text-slate-200">{{ ucfirst($selectedKegiatan->mode_tombol_gabung ?? 'otomatis') }}</strong> 
                        ({{ $selectedKegiatan->menit_buka_gabung ?? 15 }} menit sebelum mulai).
                        @if($selectedKegiatan->akses_gabung_dibuka_manual === true)
                            <span class="text-teal-400 font-semibold">(Sedang dibuka manual oleh admin)</span>
                        @elseif($selectedKegiatan->akses_gabung_dibuka_manual === false)
                            <span class="text-rose-400 font-semibold">(Sedang ditutup manual oleh admin)</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <form action="{{ route('admin.kegiatan.buka-akses-gabung', $selectedKegiatan->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md transition-all">
                            <span class="material-symbols-outlined text-base">meeting_room</span>
                            Buka Akses Gabung
                        </button>
                    </form>
                    <form action="{{ route('admin.kegiatan.tutup-akses-gabung', $selectedKegiatan->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white font-bold text-xs flex items-center gap-1.5 shadow-md transition-all">
                            <span class="material-symbols-outlined text-base">no_meeting_room</span>
                            Tutup Akses Gabung
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-{{ $selectedKegiatan->isDaringOrHybrid() ? '5' : '4' }} gap-4">
            <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Peserta Terdaftar</div>
                <div class="text-2xl font-black text-white mt-1">{{ number_format($stats['total_registered']) }}</div>
                <div class="text-[10px] text-slate-500 mt-1">Seluruh kuota terisi</div>
            </div>

            @if($selectedKegiatan->isDaringOrHybrid())
                <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Sudah Bergabung Daring</div>
                    <div class="text-2xl font-black text-teal-400 mt-1">{{ number_format($stats['total_gabung'] ?? 0) }}</div>
                    <div class="text-[10px] text-slate-500 mt-1">Peserta klik gabung rapat</div>
                </div>
            @endif

            <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Check-In Fase 1</div>
                <div class="text-2xl font-black text-emerald-400 mt-1">{{ number_format($stats['phase1_checked_in']) }}</div>
                <div class="text-[10px] text-slate-500 mt-1">E-Tiket tervalidasi di pintu</div>
            </div>

            <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Presensi Akhir Fase 2</div>
                <div class="text-2xl font-black text-cyan-400 mt-1">{{ number_format($stats['phase2_present']) }}</div>
                <div class="text-[10px] text-slate-500 mt-1">Bertanda tangan digital</div>
            </div>

            <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Sertifikat Terbit</div>
                <div class="text-2xl font-black text-amber-400 mt-1">{{ number_format($stats['certificates_issued']) }}</div>
                <div class="text-[10px] text-slate-500 mt-1">E-Sertifikat terverifikasi hash</div>
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        <div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
            <form action="{{ route('admin.attendances') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan->id }}">

                {{-- Input Cari --}}
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Cari Peserta</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-500 text-base">search</span>
                        <input 
                            type="text" 
                            name="q" 
                            value="{{ request('q') }}" 
                            placeholder="Cari nama, NIM, email, tiket, kursi..." 
                            class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-amber-500 font-medium"
                        >
                    </div>
                </div>

                {{-- Filter Fase 1 --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Fase 1 (Check-In)</label>
                    <select name="fase1" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500 font-medium">
                        <option value="all">Semua Status</option>
                        <option value="checked_in" {{ request('fase1') === 'checked_in' ? 'selected' : '' }}>Sudah Check-In</option>
                        <option value="not_checked_in" {{ request('fase1') === 'not_checked_in' ? 'selected' : '' }}>Belum Check-In</option>
                    </select>
                </div>

                {{-- Filter Fase 2 --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Fase 2 (Presensi)</label>
                    <select name="fase2" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500 font-medium">
                        <option value="all">Semua Status</option>
                        <option value="present" {{ request('fase2') === 'present' ? 'selected' : '' }}>Sudah Presensi</option>
                        <option value="not_present" {{ request('fase2') === 'not_present' ? 'selected' : '' }}>Belum Presensi</option>
                    </select>
                </div>

                {{-- Tombol Terapkan --}}
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-colors">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>
                        Filter
                    </button>
                    @if(request()->hasAny(['q', 'fase1', 'fase2', 'sertifikat']))
                        <a href="{{ route('admin.attendances', ['kegiatan_id' => $selectedKegiatan->id]) }}" class="py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 text-xs font-bold" title="Reset filter">
                            <span class="material-symbols-outlined text-sm">restart_alt</span>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabel Rekap Peserta --}}
        <div class="bg-slate-950 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white">Daftar Kehadiran: {{ $selectedKegiatan->nama_kegiatan }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Menampilkan {{ number_format($participants->count()) }} catatan peserta (Waktu tercatat dalam WIB).
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-5 py-3.5">No</th>
                            <th class="px-5 py-3.5">Peserta</th>
                            <th class="px-5 py-3.5">Tiket &amp; Kursi</th>
                            <th class="px-5 py-3.5">Check-In Fase 1</th>
                            @if($selectedKegiatan->isDaringOrHybrid())
                                <th class="px-5 py-3.5">Gabung Daring</th>
                            @endif
                            <th class="px-5 py-3.5">Presensi Fase 2</th>
                            <th class="px-5 py-3.5">Tanda Tangan &amp; Bukti</th>
                            <th class="px-5 py-3.5">Status Sertifikat</th>
                            <th class="px-5 py-3.5 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300">
                        @forelse($participants as $idx => $p)
                            <tr class="hover:bg-slate-900/50 transition-colors">
                                <td class="px-5 py-4 text-slate-500 font-mono">{{ $idx + 1 }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-white text-sm">{{ $p->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">NIM: {{ $p->nim }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $p->email }} &bull; {{ $p->prodi }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-mono text-xs text-amber-400 font-bold">{{ $p->ticket_number }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs">event_seat</span>
                                        {{ $p->seat_code }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    @if($p->is_phase1)
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                            Sudah Check-In
                                        </span>
                                        @if($p->phase1_time)
                                            <div class="text-[10px] text-slate-400 mt-1 font-mono">{{ $p->phase1_time }}</div>
                                        @endif
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700 inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                                            Belum Check-In
                                        </span>
                                    @endif
                                </td>
                                @if($selectedKegiatan->isDaringOrHybrid())
                                    <td class="px-5 py-4">
                                        @if($p->is_gabung)
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-teal-500/10 text-teal-400 border border-teal-500/20 inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">videocam</span>
                                                Bergabung ({{ $p->gabung_count }}x)
                                            </span>
                                            @if($p->gabung_time)
                                                <div class="text-[10px] text-slate-400 mt-1 font-mono">{{ $p->gabung_time }}</div>
                                            @endif
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                                Belum Gabung
                                            </span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-5 py-4">
                                    @if($p->is_phase2)
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">verified</span>
                                            Sudah Presensi Akhir
                                        </span>
                                        @if($p->phase2_time)
                                            <div class="text-[10px] text-slate-400 mt-1 font-mono">{{ $p->phase2_time }}</div>
                                        @endif
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700 inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">hourglass_empty</span>
                                            Belum Presensi
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        @if($p->signature_url)
                                            <button 
                                                type="button" 
                                                @click="signatureModal = true; signatureUrl = '{{ $p->signature_url }}'; signatureName = '{{ addslashes($p->name) }}'"
                                                class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-800 font-bold text-[11px] flex items-center gap-1.5 transition-colors"
                                            >
                                                <span class="material-symbols-outlined text-sm">draw</span>
                                                Tanda Tangan
                                            </button>
                                        @endif
                                        @if($p->screenshot_url)
                                            <a 
                                                href="{{ $p->screenshot_url }}" 
                                                target="_blank"
                                                class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-teal-400 border border-slate-800 font-bold text-[11px] inline-flex items-center gap-1.5 transition-colors"
                                            >
                                                <span class="material-symbols-outlined text-sm">screenshot</span>
                                                Bukti Layar
                                            </a>
                                        @endif
                                        @if(!$p->signature_url && !$p->screenshot_url)
                                            <span class="text-slate-600 text-xs italic">-</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    @if($p->is_certificate)
                                        <div class="space-y-1">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">workspace_premium</span>
                                                Sertifikat Terbit
                                            </span>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $p->certificate_number }}</div>
                                            @if($p->certificate_download_url)
                                                <a href="{{ $p->certificate_download_url }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-amber-400 hover:underline font-bold mt-0.5">
                                                    <span class="material-symbols-outlined text-xs">download</span>
                                                    Unduh PDF
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-500 text-[11px]">Belum Diterbitkan</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    @if(!$p->is_phase2 && $p->registration_id)
                                        <button 
                                            type="button" 
                                            @click="
                                                manualFase2Modal = true;
                                                selectedFase2RegId = {{ $p->registration_id }};
                                                selectedFase2Name = '{{ addslashes($p->name) }}';
                                                fase2Reason = '';
                                            "
                                            class="px-2.5 py-1 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 text-cyan-300 border border-cyan-800 font-bold text-[11px] inline-flex items-center gap-1 transition-colors"
                                        >
                                            <span class="material-symbols-outlined text-xs">edit_calendar</span>
                                            Tandai manual
                                        </button>
                                    @else
                                        <span class="text-slate-600 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                    Tidak ada data peserta yang cocok dengan filter pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-slate-950 border border-slate-800 rounded-3xl p-12 text-center text-slate-500">
            <span class="material-symbols-outlined text-4xl text-slate-600 mb-2">event_busy</span>
            <p class="text-sm font-semibold">Belum ada kegiatan yang dipilih atau tersedia di sistem.</p>
        </div>
    @endif

    {{-- Modal Pratinjau Tanda Tangan Digital (Dilindungi Policy Super Admin) --}}
    <div 
        x-show="signatureModal" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <div 
            @click.away="signatureModal = false" 
            class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-white">Pratinjau Tanda Tangan Digital</h3>
                    <p class="text-xs text-slate-400" x-text="'Peserta: ' + signatureName"></p>
                </div>
                <button type="button" @click="signatureModal = false" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-900">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <div class="p-4 bg-slate-900 rounded-2xl border border-slate-800 flex items-center justify-center min-h-[220px]">
                <template x-if="signatureUrl">
                    <img :src="signatureUrl" alt="Tanda Tangan Digital" class="max-h-56 max-w-full object-contain">
                </template>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-500">
                <span>Data pribadi dilindungi &bull; Hanya Super Administrator</span>
                <button type="button" @click="signatureModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-700">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    @if($selectedKegiatan)
        {{-- Modal Buka Presensi Akhir --}}
        <div 
            x-show="bukaSesiModal" 
            x-transition:enter="transition ease-out duration-200" 
            x-transition:enter-start="opacity-0" 
            x-transition:enter-end="opacity-100" 
            x-transition:leave="transition ease-in duration-150" 
            x-transition:leave-start="opacity-100" 
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            style="display: none;"
        >
            <div @click.away="bukaSesiModal = false" class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">lock_open</span>
                        <h3 class="text-sm font-bold text-white">Buka Sesi Presensi Akhir (Fase 2)</h3>
                    </div>
                    <button type="button" @click="bukaSesiModal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-900">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                <form action="{{ route('admin.kegiatan.presensi-akhir.buka', $selectedKegiatan->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @php
                        $selesaiWib = $selectedKegiatan->tanggal_selesai ? \App\Support\Wib::dari($selectedKegiatan->tanggal_selesai) : now('Asia/Jakarta');
                        $defaultMulai = $selesaiWib->copy()->subMinutes(15)->format('Y-m-d\TH:i');
                        $defaultSelesai = $selesaiWib->copy()->addMinutes(60)->format('Y-m-d\TH:i');
                    @endphp

                    <div class="space-y-1">
                        <label for="presensi_akhir_mulai" class="block text-xs font-bold text-slate-300">
                            Waktu Mulai Presensi Akhir (WIB)
                        </label>
                        <input 
                            type="datetime-local" 
                            step="60"
                            id="presensi_akhir_mulai"
                            name="presensi_akhir_mulai" 
                            value="{{ $defaultMulai }}"
                            style="color-scheme: dark;"
                            onclick="try { this.showPicker(); } catch(e) {}"
                            onfocus="try { this.showPicker(); } catch(e) {}"
                            aria-describedby="presensi_akhir_mulai_help"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs cursor-pointer min-h-[38px] focus:outline-none focus:border-amber-500 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-90 [&::-webkit-calendar-picker-indicator]:hover:opacity-100 [&::-webkit-calendar-picker-indicator]:p-1 [&::-webkit-calendar-picker-indicator]:rounded-md [&::-webkit-calendar-picker-indicator]:hover:bg-slate-800 [&::-webkit-calendar-picker-indicator]:transition-all [&::-webkit-calendar-picker-indicator]:scale-110"
                        >
                        <p id="presensi_akhir_mulai_help" class="text-[10px] text-slate-400">Klik kolom atau ikon kalender untuk memilih • Bawaan: 15 menit sebelum kegiatan selesai.</p>
                    </div>

                    <div class="space-y-1">
                        <label for="presensi_akhir_selesai" class="block text-xs font-bold text-slate-300">
                            Waktu Selesai Presensi Akhir (WIB)
                        </label>
                        <input 
                            type="datetime-local" 
                            step="60"
                            id="presensi_akhir_selesai"
                            name="presensi_akhir_selesai" 
                            value="{{ $defaultSelesai }}"
                            style="color-scheme: dark;"
                            onclick="try { this.showPicker(); } catch(e) {}"
                            onfocus="try { this.showPicker(); } catch(e) {}"
                            aria-describedby="presensi_akhir_selesai_help"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs cursor-pointer min-h-[38px] focus:outline-none focus:border-amber-500 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-90 [&::-webkit-calendar-picker-indicator]:hover:opacity-100 [&::-webkit-calendar-picker-indicator]:p-1 [&::-webkit-calendar-picker-indicator]:rounded-md [&::-webkit-calendar-picker-indicator]:hover:bg-slate-800 [&::-webkit-calendar-picker-indicator]:transition-all [&::-webkit-calendar-picker-indicator]:scale-110"
                        >
                        <p id="presensi_akhir_selesai_help" class="text-[10px] text-slate-400">Klik kolom atau ikon kalender untuk memilih • Bawaan: 60 menit setelah kegiatan selesai.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" @click="bukaSesiModal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition-colors shadow-lg shadow-amber-600/20">
                            Buka Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Perpanjang Presensi Akhir --}}
        <div 
            x-show="perpanjangModal" 
            x-transition:enter="transition ease-out duration-200" 
            x-transition:enter-start="opacity-0" 
            x-transition:enter-end="opacity-100" 
            x-transition:leave="transition ease-in duration-150" 
            x-transition:leave-start="opacity-100" 
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            style="display: none;"
        >
            <div @click.away="perpanjangModal = false" class="bg-slate-950 border border-slate-800 rounded-3xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">more_time</span>
                        <h3 class="text-sm font-bold text-white">Perpanjang Waktu Presensi</h3>
                    </div>
                    <button type="button" @click="perpanjangModal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-900">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                <form action="{{ route('admin.kegiatan.presensi-akhir.perpanjang', $selectedKegiatan->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="durasi_menit" class="block text-xs font-bold text-slate-300">
                            Tambahan Durasi (Menit)
                        </label>
                        <input 
                            type="number" 
                            id="durasi_menit"
                            name="durasi_menit" 
                            value="30"
                            min="5"
                            max="240"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500 font-mono"
                        >
                        <p class="text-[10px] text-slate-500">Waktu selesai sesi akan ditambah secara otomatis.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" @click="perpanjangModal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition-colors shadow-lg shadow-amber-600/20">
                            Simpan Perpanjangan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Presensi Akhir Manual per Peserta --}}
        <div 
            x-show="manualFase2Modal" 
            x-transition:enter="transition ease-out duration-200" 
            x-transition:enter-start="opacity-0" 
            x-transition:enter-end="opacity-100" 
            x-transition:leave="transition ease-in duration-150" 
            x-transition:leave-start="opacity-100" 
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            style="display: none;"
        >
            <div @click.away="manualFase2Modal = false" class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-cyan-500">edit_calendar</span>
                        <h3 class="text-sm font-bold text-white">Tandai Presensi Akhir Manual</h3>
                    </div>
                    <button type="button" @click="manualFase2Modal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-900">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                <form action="{{ route('admin.kegiatan.presensi-akhir.manual', $selectedKegiatan->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="registration_id" :value="selectedFase2RegId">

                    <div class="text-xs text-slate-300 space-y-1">
                        <p>Peserta: <strong class="text-white" x-text="selectedFase2Name"></strong></p>
                        <p class="text-slate-400 text-[11px]">
                            Presensi akhir manual akan mencatat kehadiran fase 2 peserta. Sesuai kebijakan audit, Anda wajib menyertakan alasan.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="fase2ReasonInput" class="block text-xs font-bold text-slate-300">
                            Alasan Presensi Akhir Manual <span class="text-rose-400">*</span>
                        </label>
                        <textarea 
                            id="fase2ReasonInput"
                            name="alasan"
                            x-model="fase2Reason" 
                            rows="3" 
                            required
                            placeholder="Contoh: Peserta hadir penuh hingga penutupan, gawai kehabisan daya saat pemindaian QR proyektor."
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-cyan-500 font-medium"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" @click="manualFase2Modal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold text-xs">
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!fase2Reason.trim()"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs transition-colors shadow-lg shadow-cyan-600/20"
                        >
                            Simpan Presensi Akhir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
