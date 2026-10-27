@extends('layouts.admin')

@section('title', 'Pusat Manajemen Sertifikat Digital')
@section('pageTitle', 'Pusat Sertifikat Digital')
@section('pageSubtitle', 'Penerbitan massal, kelayakan bertingkat, arsip resmi, master penandatangan, dan pengaturan verifikasi')

@section('content')
<div class="p-4 sm:p-6 lg:p-8 space-y-6" x-data="certHubManager()">
    {{-- Alerts --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-600">error</span>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1 shadow-sm">
            <div class="flex items-center gap-2 font-bold">
                <span class="material-symbols-outlined text-rose-600">warning</span>
                <span>Terdapat kesalahan pada isian formulir:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-6">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Sub-Navigasi 4 Tab Utama --}}
    <div class="border-b border-slate-200 bg-white rounded-2xl p-2 shadow-sm">
        <nav class="flex flex-wrap gap-2 text-sm font-bold" aria-label="Tabs">
            <a href="{{ request('kegiatan_id') ? route('admin.kegiatan.sertifikat', (int) request('kegiatan_id')) : route('admin.kegiatan') }}"
               class="px-3 py-2.5 rounded-xl flex items-center gap-1.5 text-slate-500 hover:bg-slate-100">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                {{ request('kegiatan_id') ? 'Kembali ke sertifikat kegiatan' : 'Kembali ke Kelola Kegiatan' }}
            </a>
            <a href="{{ route('admin.certificates', ['tab' => 'terbitkan']) }}" 
               class="px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all {{ $activeTab === 'terbitkan' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-lg">workspace_premium</span>
                <span>Terbitkan Sertifikat</span>
            </a>
            <a href="{{ route('admin.certificates', ['tab' => 'arsip']) }}" 
               class="px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all {{ $activeTab === 'arsip' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-lg">inventory_2</span>
                <span>Arsip Sertifikat</span>
            </a>
            <a href="{{ route('admin.certificates', ['tab' => 'penandatangan']) }}" 
               class="px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all {{ $activeTab === 'penandatangan' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-lg">draw</span>
                <span>Master Penandatangan</span>
            </a>
            <a href="{{ route('admin.certificates', ['tab' => 'pengaturan']) }}" 
               class="px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all {{ $activeTab === 'pengaturan' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-lg">tune</span>
                <span>Pengaturan Verifikasi</span>
            </a>
        </nav>
    </div>

    {{-- ======================================================== --}}
    {{-- TAB 1: TERBITKAN SERTIFIKAT                             --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'terbitkan')
        <div class="space-y-6">
            {{-- Header & Seleksi Kegiatan --}}
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-black text-slate-800">Penerbitan Sertifikat</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Pilih kegiatan, periksa kelayakan otomatis, dan terbitkan sertifikat massal per peran.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="openManualModal = true" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-base text-emerald-700">person_add</span>
                            <span>Tambah Manual</span>
                        </button>
                        <button type="button" @click="openImportModal = true" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-base text-emerald-700">upload_file</span>
                            <span>Impor Massal</span>
                        </button>
                        <a href="{{ route('admin.certificates.template') }}" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base text-emerald-700">download</span>
                            <span>Unduh Templat</span>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center pt-2 border-t border-slate-100">
                    <div class="md:col-span-8">
                        <label for="select_kegiatan_id" class="text-xs font-bold text-slate-700 block uppercase tracking-wider mb-1">
                            Pilih Kegiatan / Beasiswa <span class="text-rose-500">*</span>
                        </label>
                        <form method="GET" action="{{ route('admin.certificates') }}" id="kegiatanFilterForm">
                            <input type="hidden" name="tab" value="terbitkan">
                            <input type="hidden" name="jenis" value="{{ $selectedJenis->value }}">
                            <select 
                                name="kegiatan_id" 
                                id="select_kegiatan_id" 
                                onchange="document.getElementById('kegiatanFilterForm').submit()"
                                class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none text-sm text-slate-800 font-medium cursor-pointer"
                            >
                                <option value="" disabled {{ ! $selectedKegiatan ? 'selected' : '' }}>-- Pilih Kegiatan Beasiswa --</option>
                                @foreach($kegiatans as $kg)
                                    <option value="{{ $kg->id }}" {{ $selectedKegiatan?->id === $kg->id ? 'selected' : '' }}>
                                        {{ $kg->nama_kegiatan }} ({{ strtoupper($kg->kategori ?? 'UMUM') }}) - {{ $kg->tanggal_mulai ? \App\Support\Wib::dari($kg->tanggal_mulai)->translatedFormat('d M Y') : 'Jadwal belum ada' }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="md:col-span-4">
                        @if($selectedKegiatan)
                            <div class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <div><span class="font-bold">Kategori:</span> {{ ucfirst($selectedKegiatan->kategori instanceof \BackedEnum ? $selectedKegiatan->kategori->value : (string)($selectedKegiatan->kategori ?? 'Umum')) }}</div>
                                <div><span class="font-bold">Status:</span> {{ ucfirst($selectedKegiatan->status instanceof \BackedEnum ? $selectedKegiatan->status->value : (string)($selectedKegiatan->status ?? 'Aktif')) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($selectedKegiatan)
                {{-- Navigasi Jenis Sertifikat (Peserta, Juri, Pembicara, Juara) --}}
                <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                    @foreach(\App\Enums\JenisSertifikat::cases() as $jns)
                        <a href="{{ route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $selectedKegiatan->id, 'jenis' => $jns->value]) }}"
                           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ $selectedJenis === $jns ? 'bg-emerald-700 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                            <span class="material-symbols-outlined text-sm">
                                @if($jns === \App\Enums\JenisSertifikat::Peserta) groups
                                @elseif($jns === \App\Enums\JenisSertifikat::Juri) gavel
                                @elseif($jns === \App\Enums\JenisSertifikat::Pembicara) record_voice_over
                                @elseif($jns === \App\Enums\JenisSertifikat::Juara) emoji_events
                                @endif
                            </span>
                            <span>{{ $jns->label() }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Status Aturan Waktu Penerbitan --}}
                @if(! $waktuPenerbitan['bisa'])
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600 text-lg">schedule</span>
                        <div>
                            <span class="font-bold">Penerbitan Belum Diizinkan:</span> {{ $waktuPenerbitan['alasan'] }}
                        </div>
                    </div>
                @else
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-600 text-base">check_circle</span>
                            <span>Sesi penerbitan sertifikat <strong>{{ $selectedJenis->label() }}</strong> telah memenuhi syarat waktu kegiatan.</span>
                        </div>
                    </div>
                @endif

                {{-- Penandatangan Ditugaskan --}}
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-700 text-2xl">draw</span>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penandatangan Sertifikat</h3>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @forelse($assignedSignatories as $idx => $sig)
                                    @php
                                        $sigObj = $sig instanceof \App\Models\KegiatanPenandatangan ? $sig->penandatangan : $sig;
                                    @endphp
                                    <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-xs font-medium text-slate-700 border border-slate-200">
                                        {{ $idx + 1 }}. {{ $sigObj?->nama_lengkap }} ({{ $sigObj?->jabatan }})
                                    </span>
                                @empty
                                    <span class="text-xs text-rose-500 italic">Belum ada penandatangan yang ditugaskan.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="openAssignModal = true" class="px-3 py-1.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 self-start sm:self-auto cursor-pointer">
                        Ubah Penandatangan
                    </button>
                </div>

                {{-- TAB KONTEN SESUAI JENIS --}}
                @if($selectedJenis === \App\Enums\JenisSertifikat::Peserta)
                    {{-- 1. Peserta Layak Belum Terbit (Form Penerbitan Massal) --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Peserta Layak (Belum Diterbitkan)</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Memenuhi syarat pembayaran, check-in awal, dan presensi akhir.</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start sm:self-auto">
                                Total Layak: {{ $recipientsData['peserta_layak']->count() }}
                            </span>
                        </div>

                        @if($recipientsData['peserta_layak']->isEmpty())
                            <div class="py-8 text-center text-xs text-slate-400 italic">
                                Tidak ada peserta layak yang belum diterbitkan sertifikat.
                            </div>
                        @else
                            <form action="{{ route('admin.certificates.batch-issue') }}" method="POST" id="batchIssueForm">
                                @csrf
                                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan->id }}">
                                <input type="hidden" name="jenis" value="peserta">

                                <div class="flex items-center justify-between pb-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="selectAllPeserta(true)" class="text-emerald-700 font-bold hover:underline cursor-pointer">Pilih Semua</button>
                                        <span class="text-slate-300">|</span>
                                        <button type="button" @click="selectAllPeserta(false)" class="text-slate-500 font-bold hover:underline cursor-pointer">Hapus Semua</button>
                                    </div>
                                    <div class="font-bold text-slate-700" x-text="selectedPesertaCount + ' terpilih'"></div>
                                </div>

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                                <th class="p-3 w-10 text-center">
                                                    <input type="checkbox" @change="toggleAllPeserta($event)" class="rounded text-emerald-600 cursor-pointer">
                                                </th>
                                                <th class="p-3">Nama Peserta</th>
                                                <th class="p-3">NIM / Identitas</th>
                                                <th class="p-3">Asal Institusi (Dapat Diedit)</th>
                                                <th class="p-3">Predikat (Opsional)</th>
                                                <th class="p-3">Status Syarat</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @foreach($recipientsData['peserta_layak'] as $idx => $p)
                                                <tr class="hover:bg-slate-50/70">
                                                    <td class="p-3 text-center">
                                                        <input 
                                                            type="checkbox" 
                                                            name="recipients[{{ $idx }}][selected]" 
                                                            value="1" 
                                                            {{ $p['default_checked'] ? 'checked' : '' }}
                                                            class="peserta-checkbox rounded text-emerald-600 cursor-pointer"
                                                            @change="updateSelectedCount()"
                                                        >
                                                        <input type="hidden" name="recipients[{{ $idx }}][user_id]" value="{{ $p['user_id'] }}">
                                                        <input type="hidden" name="recipients[{{ $idx }}][registration_id]" value="{{ $p['registration_id'] }}">
                                                        <input type="hidden" name="recipients[{{ $idx }}][nama]" value="{{ $p['nama'] }}">
                                                        <input type="hidden" name="recipients[{{ $idx }}][email]" value="{{ $p['email'] }}">
                                                        <input type="hidden" name="recipients[{{ $idx }}][nim]" value="{{ $p['nim'] }}">
                                                        <input type="hidden" name="recipients[{{ $idx }}][penerima_key]" value="{{ $p['penerima_key'] }}">
                                                    </td>
                                                    <td class="p-3 font-bold text-slate-900">{{ $p['nama'] }}</td>
                                                    <td class="p-3 text-slate-500 font-mono">{{ $p['nim'] ?? '-' }}</td>
                                                    <td class="p-3">
                                                        <input 
                                                            type="text" 
                                                            name="recipients[{{ $idx }}][asal_institusi]" 
                                                            value="{{ $p['asal_institusi'] }}" 
                                                            class="w-full p-1.5 border border-slate-200 rounded-lg text-xs bg-white"
                                                        >
                                                    </td>
                                                    <td class="p-3">
                                                        <input 
                                                            type="text" 
                                                            name="recipients[{{ $idx }}][predikat]" 
                                                            placeholder="Opsional" 
                                                            class="w-full p-1.5 border border-slate-200 rounded-lg text-xs bg-white"
                                                        >
                                                    </td>
                                                    <td class="p-3">
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                            Layak
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pt-4 flex justify-end">
                                    <button 
                                        type="button" 
                                        @click="confirmBatchIssue()" 
                                        {{ ! $waktuPenerbitan['bisa'] ? 'disabled' : '' }}
                                        class="py-2.5 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs shadow-md shadow-emerald-700/20 flex items-center gap-2 cursor-pointer"
                                    >
                                        <span>Terbitkan Sertifikat Terpilih</span>
                                        <span class="material-symbols-outlined text-base">verified</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>

                    {{-- 2. Peserta Sudah Terbit --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-black text-slate-800">Daftar Sertifikat Terbit ({{ $recipientsData['peserta_terbit']->count() }})</h3>
                        </div>
                        @if($recipientsData['peserta_terbit']->isEmpty())
                            <div class="py-4 text-center text-xs text-slate-400 italic">Belum ada sertifikat peserta yang diterbitkan.</div>
                        @else
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                            <th class="p-2.5">Nama</th>
                                            <th class="p-2.5">Nomor Seri</th>
                                            <th class="p-2.5">Status</th>
                                            <th class="p-2.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-700">
                                        @foreach($recipientsData['peserta_terbit'] as $pt)
                                            <tr>
                                                <td class="p-2.5 font-bold">{{ $pt['nama'] }}</td>
                                                <td class="p-2.5 font-mono text-emerald-800">{{ $pt['nomor_sertifikat'] }}</td>
                                                <td class="p-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Terbit</span></td>
                                                <td class="p-2.5 text-right">
                                                    <a href="{{ url('/verifikasi?code='.$pt['nomor_sertifikat']) }}" target="_blank" class="text-emerald-700 hover:underline font-bold">Verifikasi</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- 3. Peserta Belum Layak Beserta Alasan --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-black text-slate-800">Peserta Belum Memenuhi Syarat Kelayakan ({{ $recipientsData['peserta_tidak_layak']->count() }})</h3>
                        </div>
                        @if($recipientsData['peserta_tidak_layak']->isEmpty())
                            <div class="py-4 text-center text-xs text-slate-400 italic">Semua peserta terdaftar telah memenuhi syarat.</div>
                        @else
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                            <th class="p-2.5">Nama</th>
                                            <th class="p-2.5">Check-in Awal</th>
                                            <th class="p-2.5">Presensi Akhir</th>
                                            <th class="p-2.5">Alasan Ketidaklayakan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-700">
                                        @foreach($recipientsData['peserta_tidak_layak'] as $ptl)
                                            <tr>
                                                <td class="p-2.5 font-bold">{{ $ptl['nama'] }}</td>
                                                <td class="p-2.5">
                                                    @if($ptl['detail']['check_in_awal'] ?? false)
                                                        <span class="text-emerald-700 font-bold">Ya</span>
                                                    @else
                                                        <span class="text-rose-600">Belum</span>
                                                    @endif
                                                </td>
                                                <td class="p-2.5">
                                                    @if($ptl['detail']['presensi_akhir'] ?? false)
                                                        <span class="text-emerald-700 font-bold">Ya</span>
                                                    @else
                                                        <span class="text-rose-600">Belum</span>
                                                    @endif
                                                </td>
                                                <td class="p-2.5">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                                        {{ $ptl['alasan'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                @elseif($selectedJenis === \App\Enums\JenisSertifikat::Juri)
                    {{-- Tab Juri --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Daftar Dewan Juri & Penilai</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Bawaan tercentang bagi juri yang telah menilai minimal 1 karya lomba.</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                Total: {{ $recipientsData['juri_list']->count() }}
                            </span>
                        </div>

                        @if($recipientsData['juri_list']->isEmpty())
                            <div class="py-8 text-center text-xs text-slate-400 italic">
                                Belum ada penilai yang ditugaskan pada kegiatan lomba ini.
                            </div>
                        @else
                            <form action="{{ route('admin.certificates.batch-issue') }}" method="POST">
                                @csrf
                                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan->id }}">
                                <input type="hidden" name="jenis" value="juri">

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                                <th class="p-3 w-10 text-center">Pilih</th>
                                                <th class="p-3">Nama Juri</th>
                                                <th class="p-3">Institusi</th>
                                                <th class="p-3">Progres Penilaian</th>
                                                <th class="p-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @foreach($recipientsData['juri_list'] as $idx => $j)
                                                <tr>
                                                    <td class="p-3 text-center">
                                                        @if(! $j['sudah_terbit'])
                                                            <input 
                                                                type="checkbox" 
                                                                name="recipients[{{ $idx }}][selected]" 
                                                                value="1" 
                                                                {{ $j['default_checked'] ? 'checked' : '' }}
                                                                class="rounded text-emerald-600 cursor-pointer"
                                                            >
                                                            <input type="hidden" name="recipients[{{ $idx }}][user_id]" value="{{ $j['user_id'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][nama]" value="{{ $j['nama'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][asal_institusi]" value="{{ $j['asal_institusi'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][predikat]" value="Dewan Juri">
                                                            <input type="hidden" name="recipients[{{ $idx }}][email]" value="{{ $j['email'] }}">
                                                        @else
                                                            <span class="material-symbols-outlined text-emerald-600 text-base">check</span>
                                                        @endif
                                                    </td>
                                                    <td class="p-3 font-bold text-slate-900">{{ $j['nama'] }}</td>
                                                    <td class="p-3 text-slate-600">{{ $j['asal_institusi'] }}</td>
                                                    <td class="p-3">
                                                        <span class="font-bold {{ $j['telah_menilai'] ? 'text-emerald-700' : 'text-amber-600' }}">
                                                            {{ $j['karya_dinilai'] }} / {{ $j['total_karya'] }} karya dinilai
                                                        </span>
                                                    </td>
                                                    <td class="p-3">
                                                        @if($j['sudah_terbit'])
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Terbit: {{ $j['nomor_sertifikat'] }}</span>
                                                        @elseif($j['telah_menilai'])
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Siap Diterbitkan</span>
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Belum Menilai</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pt-4 flex justify-end">
                                    <button 
                                        type="submit" 
                                        {{ ! $waktuPenerbitan['bisa'] ? 'disabled' : '' }}
                                        class="py-2.5 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white font-bold text-xs shadow-md shadow-emerald-700/20 flex items-center gap-2 cursor-pointer"
                                    >
                                        <span>Terbitkan Sertifikat Juri</span>
                                        <span class="material-symbols-outlined text-base">verified</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>

                @elseif($selectedJenis === \App\Enums\JenisSertifikat::Pembicara)
                    {{-- Tab Pembicara --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Daftar Sertifikat Pembicara / Narasumber</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Sertifikat pembicara ditambahkan melalui form tambah manual atau impor berkas.</p>
                            </div>
                            <button type="button" @click="openManualModal = true" class="px-3.5 py-2 bg-emerald-700 text-white rounded-xl text-xs font-bold hover:bg-emerald-800 flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
                                <span class="material-symbols-outlined text-base">person_add</span>
                                <span>Tambah Pembicara</span>
                            </button>
                        </div>

                        @if($recipientsData['pembicara_terbit']->isEmpty())
                            <div class="py-8 text-center text-xs text-slate-400 italic">
                                Belum ada sertifikat pembicara yang diterbitkan untuk kegiatan ini. Gunakan tombol "Tambah Pembicara" di atas.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                            <th class="p-3">Nama Pembicara</th>
                                            <th class="p-3">Institusi</th>
                                            <th class="p-3">Predikat / Keterangan</th>
                                            <th class="p-3">Nomor Seri</th>
                                            <th class="p-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-700">
                                        @foreach($recipientsData['pembicara_terbit'] as $spk)
                                            <tr>
                                                <td class="p-3 font-bold text-slate-900">{{ $spk->recipient_name }}</td>
                                                <td class="p-3 text-slate-600">{{ $spk->institution_name }}</td>
                                                <td class="p-3">{{ $spk->predikat ?: 'Narasumber' }}</td>
                                                <td class="p-3 font-mono font-bold text-emerald-800">{{ $spk->nomor_sertifikat }}</td>
                                                <td class="p-3 text-right">
                                                    <a href="{{ $spk->verification_url }}" target="_blank" class="text-emerald-700 hover:underline font-bold">Verifikasi</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                @elseif($selectedJenis === \App\Enums\JenisSertifikat::Juara)
                    {{-- Tab Juara --}}
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Usulan Peringkat & Juara Lomba</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Dihasilkan dari rekapitulasi nilai akhir karya yang dinilai oleh dewan juri.</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start sm:self-auto">
                                {{ $recipientsData['juara_usulan']->count() }} Karya Dinilai
                            </span>
                        </div>

                        @if($recipientsData['ada_karya_belum_dinilai'])
                            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-base">warning</span>
                                <span>Peringatan: Terdapat <strong>{{ $recipientsData['jumlah_belum_dinilai'] }} karya</strong> yang belum dinilai juri. Pastikan seluruh karya selesai dinilai sebelum penetapan juara.</span>
                            </div>
                        @endif

                        @if($recipientsData['juara_usulan']->isEmpty())
                            <div class="py-8 text-center text-xs text-slate-400 italic">
                                Belum ada karya lomba dengan nilai akhir yang tercatat.
                            </div>
                        @else
                            <form action="{{ route('admin.certificates.batch-issue') }}" method="POST">
                                @csrf
                                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan->id }}">
                                <input type="hidden" name="jenis" value="juara">

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                                <th class="p-3 w-10 text-center">Pilih</th>
                                                <th class="p-3">Rank</th>
                                                <th class="p-3">Penerima / Kelompok</th>
                                                <th class="p-3">Judul Karya</th>
                                                <th class="p-3">Nilai</th>
                                                <th class="p-3">Predikat Juara (Wajib)</th>
                                                <th class="p-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @foreach($recipientsData['juara_usulan'] as $idx => $jr)
                                                <tr>
                                                    <td class="p-3 text-center">
                                                        @if(! $jr['sudah_terbit'])
                                                            <input 
                                                                type="checkbox" 
                                                                name="recipients[{{ $idx }}][selected]" 
                                                                value="1" 
                                                                {{ $jr['default_checked'] ? 'checked' : '' }}
                                                                class="rounded text-emerald-600 cursor-pointer"
                                                            >
                                                            <input type="hidden" name="recipients[{{ $idx }}][user_id]" value="{{ $jr['user_id'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][registration_id]" value="{{ $jr['registration_id'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][nama]" value="{{ $jr['nama'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][asal_institusi]" value="{{ $jr['asal_institusi'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][email]" value="{{ $jr['email'] }}">
                                                            <input type="hidden" name="recipients[{{ $idx }}][nim]" value="{{ $jr['nim'] }}">
                                                        @else
                                                            <span class="material-symbols-outlined text-emerald-600 text-base">check</span>
                                                        @endif
                                                    </td>
                                                    <td class="p-3 font-bold text-slate-900">#{{ $jr['peringkat'] }}</td>
                                                    <td class="p-3 font-bold text-slate-900">{{ $jr['nama'] }}</td>
                                                    <td class="p-3 text-slate-600">{{ $jr['judul_karya'] }}</td>
                                                    <td class="p-3 font-bold text-emerald-800">{{ number_format($jr['nilai'], 2) }}</td>
                                                    <td class="p-3">
                                                        @if(! $jr['sudah_terbit'])
                                                            <input 
                                                                type="text" 
                                                                name="recipients[{{ $idx }}][predikat]" 
                                                                value="{{ $jr['predikat_usulan'] }}" 
                                                                list="preset_predikat_list" 
                                                                class="w-full p-1.5 border border-slate-300 rounded-lg text-xs bg-white font-bold"
                                                                required
                                                            >
                                                        @else
                                                            <span class="font-bold text-slate-800">{{ $jr['predikat_usulan'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="p-3">
                                                        @if($jr['sudah_terbit'])
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Terbit: {{ $jr['nomor_sertifikat'] }}</span>
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Usulan</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <datalist id="preset_predikat_list">
                                    <option value="Juara 1"></option>
                                    <option value="Juara 2"></option>
                                    <option value="Juara 3"></option>
                                    <option value="Harapan 1"></option>
                                    <option value="Harapan 2"></option>
                                    <option value="Harapan 3"></option>
                                    <option value="Karya Terfavorit"></option>
                                </datalist>

                                <div class="pt-4 flex justify-end">
                                    <button 
                                        type="submit" 
                                        {{ ! $waktuPenerbitan['bisa'] ? 'disabled' : '' }}
                                        class="py-2.5 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white font-bold text-xs shadow-md shadow-emerald-700/20 flex items-center gap-2 cursor-pointer"
                                    >
                                        <span>Tetapkan & Terbitkan Sertifikat Juara</span>
                                        <span class="material-symbols-outlined text-base">emoji_events</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif
            @else
                <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-400">
                    <span class="material-symbols-outlined text-5xl mb-2 text-slate-300">event_busy</span>
                    <p class="text-sm font-bold">Pilih kegiatan beasiswa di atas untuk memulai manajemen sertifikat.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 2: ARSIP SERTIFIKAT                                  --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'arsip')
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-800">Daftar Sertifikat Terbit</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Seluruh sertifikat resmi terbitan sistem dengan status QR dan nomor seri unik.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start sm:self-auto">
                    Total: {{ $sertifikats->total() }} Sertifikat
                </span>
            </div>

            {{-- Filter Arsip --}}
            <form method="GET" action="{{ route('admin.certificates') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                <input type="hidden" name="tab" value="arsip">
                <div>
                    <label class="font-bold text-slate-600 block mb-1">Cari Nama/No Seri</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ketik kata kunci..." class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                </div>
                <div>
                    <label class="font-bold text-slate-600 block mb-1">Kegiatan</label>
                    <select name="kegiatan_id" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                        <option value="">Semua Kegiatan</option>
                        @foreach($kegiatans as $kg)
                            <option value="{{ $kg->id }}" {{ ($filters['kegiatan_id'] ?? '') == $kg->id ? 'selected' : '' }}>{{ $kg->nama_kegiatan }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="font-bold text-slate-600 block mb-1">Jenis Sertifikat</label>
                    <select name="jenis" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                        <option value="">Semua Jenis</option>
                        @foreach(\App\Enums\JenisSertifikat::cases() as $j)
                            <option value="{{ $j->value }}" {{ ($filters['jenis'] ?? '') == $j->value ? 'selected' : '' }}>{{ $j->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="font-bold text-slate-600 block mb-1">Status</label>
                    <select name="status" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                        <option value="">Semua Status</option>
                        <option value="aktif" {{ ($filters['status'] ?? '') == 'aktif' ? 'selected' : '' }}>Aktif / Sah</option>
                        <option value="dicabut" {{ ($filters['status'] ?? '') == 'dicabut' ? 'selected' : '' }}>Dicabut</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full p-2 bg-emerald-700 text-white rounded-lg font-bold hover:bg-emerald-800">Filter</button>
                    <a href="{{ route('admin.certificates', ['tab' => 'arsip']) }}" class="p-2 bg-slate-200 text-slate-700 rounded-lg font-bold hover:bg-slate-300">Reset</a>
                </div>
            </form>

            {{-- Tabel Arsip --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                            <th class="p-3">No Seri</th>
                            <th class="p-3">Penerima</th>
                            <th class="p-3">Kegiatan</th>
                            <th class="p-3">Jenis & Predikat</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Tanggal Terbit</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($sertifikats as $st)
                            <tr class="hover:bg-slate-50/70">
                                <td class="p-3 font-mono font-bold text-emerald-800">
                                    {{ $st->nomor_sertifikat }}
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-slate-900">{{ $st->recipient_name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $st->institution_name }}</div>
                                    @if($st->nim_penerima)
                                        <div class="text-[10px] text-slate-400 font-mono">NIM: {{ $st->nim_penerima }}</div>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-slate-800 line-clamp-1">{{ $st->kegiatan?->nama_kegiatan ?? 'Kegiatan Telah Dihapus' }}</div>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold 
                                        @if($st->jenis?->value === 'juara') bg-amber-100 text-amber-800
                                        @elseif($st->jenis?->value === 'juri') bg-purple-100 text-purple-800
                                        @elseif($st->jenis?->value === 'pembicara') bg-blue-100 text-blue-800
                                        @else bg-emerald-100 text-emerald-800 @endif">
                                        {{ $st->jenis_label }}
                                    </span>
                                    @if($st->predikat)
                                        <div class="text-[11px] font-bold text-slate-600 mt-0.5">{{ $st->predikat }}</div>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if($st->isDicabut())
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            Dicabut
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            Sah
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 whitespace-nowrap text-slate-500">
                                    {{ $st->issued_at ? \App\Support\Wib::dari($st->issued_at)->translatedFormat('d M Y') : '-' }}
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ $st->verification_url }}" target="_blank" class="p-1.5 rounded-lg text-emerald-700 hover:bg-emerald-50" title="Buka Halaman Verifikasi">
                                            <span class="material-symbols-outlined text-base">verified</span>
                                        </a>
                                        <span x-data="{ tersalin: false }" class="inline-flex items-center">
                                            <button
                                                type="button"
                                                @click="navigator.clipboard.writeText({{ json_encode($st->verification_url) }}).then(() => { tersalin = true; setTimeout(() => tersalin = false, 2000) })"
                                                class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 cursor-pointer"
                                                title="Salin tautan verifikasi"
                                                aria-label="Salin tautan verifikasi {{ $st->nomor_sertifikat }}"
                                            >
                                                <span class="material-symbols-outlined text-base">link</span>
                                            </button>
                                            <span x-show="tersalin" x-cloak role="status" class="text-[10px] font-bold text-emerald-700">Tersalin</span>
                                        </span>
                                        @if($st->file_url)
                                            <a href="{{ $st->file_url }}" target="_blank" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100" title="Unduh PDF">
                                                <span class="material-symbols-outlined text-base">download</span>
                                            </a>
                                        @endif
                                        <button 
                                            type="button" 
                                            @click="openEditModal({{ json_encode([
                                                'id' => $st->id,
                                                'nomor' => $st->nomor_sertifikat,
                                                'nama' => $st->recipient_name,
                                                'institusi' => $st->institution_name,
                                                'jenis' => $st->jenis?->value ?? 'peserta',
                                                'predikat' => $st->predikat,
                                                'keterangan' => $st->keterangan_publik,
                                                'has_file' => !empty($st->file_pdf_path),
                                            ]) }})"
                                            class="p-1.5 rounded-lg text-blue-700 hover:bg-blue-50 cursor-pointer" 
                                            title="Edit Data Tampilan"
                                        >
                                            <span class="material-symbols-outlined text-base">edit</span>
                                        </button>
                                        @if(! $st->isDicabut())
                                            <button 
                                                type="button" 
                                                @click="openRevokeModal({{ $st->id }}, '{{ $st->nomor_sertifikat }}', '{{ $st->recipient_name }}')"
                                                class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 cursor-pointer" 
                                                title="Cabut Sertifikat"
                                            >
                                                <span class="material-symbols-outlined text-base">cancel</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    Tidak ada data sertifikat yang sesuai kriteria pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2">
                {{ $sertifikats->links() }}
            </div>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 3: MASTER PENANDATANGAN                              --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'penandatangan')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {{-- Form Tambah Penandatangan --}}
            <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <h3 class="text-base font-black text-slate-800">Tambah Master Penandatangan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Pejabat universitas atau panitia yang menandatangani sertifikat kegiatan.</p>
                </div>

                <form action="{{ route('admin.certificates.penandatangan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                    @csrf
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama" required placeholder="Contoh: Prof. Dr. Ir. Wahyu Utomo" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 text-slate-800 font-medium">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Gelar (Opsional)</label>
                            <input type="text" name="gelar" placeholder="Contoh: M.Kom." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Jabatan <span class="text-rose-500">*</span></label>
                            <input type="text" name="jabatan" list="preset_jabatan" required placeholder="Pilih atau ketik..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                            <datalist id="preset_jabatan">
                                <option value="Rektor"></option>
                                <option value="Wakil Rektor I"></option>
                                <option value="Dekan Fakultas"></option>
                                <option value="Pembina SEMADIKSI"></option>
                                <option value="Ketua Umum"></option>
                                <option value="Ketua Panitia"></option>
                                <option value="Sekretaris Panitia"></option>
                            </datalist>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Jenis Identitas</label>
                            <select name="jenis_identitas" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                                <option value="NIP">NIP</option>
                                <option value="NIM">NIM</option>
                                <option value="NIDN">NIDN</option>
                                <option value="NUPTK">NUPTK</option>
                                <option value="tanpa">Tanpa Identitas</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Nomor Identitas</label>
                            <input type="text" name="nomor_identitas" placeholder="Nomor NIP/NIM..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                        </div>
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Institusi / Afiliasi <span class="text-rose-500">*</span></label>
                        <input type="text" name="institusi" required value="Universitas Nahdlatul Ulama Surabaya" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Mode Tampil Identitas di Publik <span class="text-rose-500">*</span></label>
                        <select name="mode_tampil" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                            <option value="sembunyikan" selected>Sembunyikan Identitas (Bawaan Aman)</option>
                            <option value="samarkan">Samarkan Identitas (Contoh: 1975****1001)</option>
                            <option value="penuh">Tampilkan Penuh (Publik Terbuka)</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Gunakan mode "Sembunyikan" atau "Samarkan" untuk melindungi privasi NIP dan NIM pejabat/panitia.</p>
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Berkas Tanda Tangan (Opsional, PNG/WebP max 1MB)</label>
                        <input type="file" name="tanda_tangan" accept="image/png,image/webp" class="w-full p-2 border border-slate-300 rounded-xl bg-slate-50 text-xs">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 bg-emerald-700 text-white font-bold rounded-xl hover:bg-emerald-800 shadow-md shadow-emerald-700/20">
                            Simpan Master Penandatangan
                        </button>
                    </div>
                </form>
            </div>

            {{-- Daftar Master Penandatangan --}}
            <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-800">Daftar Penandatangan Aktif</h3>
                    <span class="text-xs text-slate-500">{{ $masterPenandatangan->count() }} orang</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                <th class="p-3">Nama & Gelar</th>
                                <th class="p-3">Jabatan</th>
                                <th class="p-3">Identitas & Mode</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($masterPenandatangan as $mp)
                                <tr>
                                    <td class="p-3 font-bold text-slate-900">
                                        {{ $mp->nama_lengkap }}
                                        <div class="text-[10px] text-slate-400 font-normal">{{ $mp->institusi }}</div>
                                    </td>
                                    <td class="p-3">{{ $mp->jabatan }}</td>
                                    <td class="p-3">
                                        <div>{{ $mp->identitas_terformat ?: ($mp->nomor_identitas ? $mp->jenis_identitas.': '.$mp->nomor_identitas : '-') }}</div>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold 
                                            @if($mp->mode_tampil === 'sembunyikan') bg-slate-100 text-slate-700
                                            @elseif($mp->mode_tampil === 'samarkan') bg-amber-100 text-amber-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($mp->mode_tampil) }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        @if($mp->is_active)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Nonaktif</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 4: PENGATURAN VERIFIKASI                            --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'pengaturan')
        <div class="max-w-3xl bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
            <div>
                <h3 class="text-base font-black text-slate-800">Pengaturan Tampilan Halaman Verifikasi Publik</h3>
                <p class="text-xs text-slate-500 mt-0.5">Kelola bagian mana saja yang diizinkan tampil di halaman verifikasi sertifikat publik (/verifikasi).</p>
            </div>

            <form action="{{ route('admin.certificates.pengaturan.update') }}" method="POST" class="space-y-5 text-sm">
                @csrf
                <div class="space-y-4 divide-y divide-slate-100">
                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan Asal Institusi</div>
                            <div class="text-xs text-slate-500">Menampilkan asal kampus atau institusi penerima sertifikat.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_asal_institusi" value="1" {{ $pengaturan->tampilkan_asal_institusi ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>

                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan Jenis & Predikat</div>
                            <div class="text-xs text-slate-500">Menampilkan lencana jenis sertifikat (Peserta, Juri, Pembicara, Juara) dan predikat.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_jenis_predikat" value="1" {{ $pengaturan->tampilkan_jenis_predikat ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>

                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan NIM Penerima (Disamarkan)</div>
                            <div class="text-xs text-slate-500">Menampilkan NIM penerima yang disamarkan demi privasi data peserta.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_nim_samar" value="1" {{ $pengaturan->tampilkan_nim_samar ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>

                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan Penyelenggara Kegiatan</div>
                            <div class="text-xs text-slate-500">Menampilkan informasi SEMADIKSI dan Universitas Nahdlatul Ulama Surabaya.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_penyelenggara" value="1" {{ $pengaturan->tampilkan_penyelenggara ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>

                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan Keterangan Publik</div>
                            <div class="text-xs text-slate-500">Menampilkan catatan publik sertifikat jika diisi saat penerbitan.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_keterangan" value="1" {{ $pengaturan->tampilkan_keterangan ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>

                    <label class="flex items-center justify-between pt-3 cursor-pointer">
                        <div>
                            <div class="font-bold text-slate-800">Tampilkan Tabel Penandatangan</div>
                            <div class="text-xs text-slate-500">Menampilkan tabel informasi penandatangan digital (TTE) dan keabsahannya.</div>
                        </div>
                        <input type="checkbox" name="tampilkan_tabel_penandatangan" value="1" {{ $pengaturan->tampilkan_tabel_penandatangan ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5">
                    </label>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <label class="font-bold text-slate-700 block uppercase tracking-wider text-xs mb-1">Teks Pengantar Halaman Verifikasi</label>
                    <textarea name="teks_pengantar" rows="3" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 text-xs text-slate-800 font-medium">{{ $pengaturan->teks_pengantar }}</textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="py-2.5 px-6 bg-emerald-700 text-white font-bold rounded-xl text-xs hover:bg-emerald-800 shadow-md shadow-emerald-700/20">
                        Simpan Pengaturan Verifikasi
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- MODAL TAMBAH PENERIMA MANUAL                             --}}
    {{-- ======================================================== --}}
    <div x-show="openManualModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="openManualModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-800">Tambah Penerima Sertifikat Manual</h3>
                <button type="button" @click="openManualModal = false" class="text-slate-400 hover:text-slate-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form action="{{ route('admin.certificates.manual') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                @csrf
                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan?->id }}">

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Nama Lengkap Penerima <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_penerima" required placeholder="Nama lengkap beserta gelar jika ada..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-medium">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Asal Institusi / Kampus <span class="text-rose-500">*</span></label>
                        <input type="text" name="asal_institusi" required value="Universitas Nahdlatul Ulama Surabaya" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-medium">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Jenis Sertifikat <span class="text-rose-500">*</span></label>
                        <select name="jenis" x-model="manualJenis" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-bold">
                            <option value="peserta">Peserta</option>
                            <option value="juri">Juri</option>
                            <option value="pembicara">Pembicara</option>
                            <option value="juara">Juara</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">
                            Predikat <span x-show="manualJenis === 'juara'" class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="predikat" placeholder="Contoh: Juara 1 / Keynote" :required="manualJenis === 'juara'" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">NIM / NIP (Opsional)</label>
                        <input type="text" name="nim_penerima" placeholder="Nomor identitas..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                </div>

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Email (opsional, hanya untuk arsip; sertifikat tidak dikirim lewat email)</label>
                    <input type="email" name="email_penerima" placeholder="penerima@example.com" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="font-bold text-slate-700 block uppercase tracking-wider">Nomor Seri Sertifikat</label>
                        <button type="button" @click="generateSerialCode()" class="text-xs text-emerald-700 hover:text-emerald-800 font-bold hover:underline cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">refresh</span> Generate Otomatis
                        </button>
                    </div>
                    <input type="text" name="nomor_sertifikat" x-model="manualCertCode" placeholder="Otomatis jika dikosongkan (format: CERT-KODE-TAHUN-NOMOR)" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-mono uppercase">
                </div>

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Keterangan Publik (Opsional)</label>
                    <input type="text" name="keterangan_publik" placeholder="Catatan singkat yang tampil di halaman verifikasi..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="openManualModal = false" class="px-4 py-2 border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-700 text-white font-bold rounded-xl hover:bg-emerald-800 shadow-md shadow-emerald-700/20">Terbitkan Sertifikat</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL IMPOR MASSAL (.XLSX / .CSV)                        --}}
    {{-- ======================================================== --}}
    <div x-show="openImportModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="openImportModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-800">Impor Massal Penerima Sertifikat</h3>
                <button type="button" @click="openImportModal = false" class="text-slate-400 hover:text-slate-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form action="{{ route('admin.certificates.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan?->id }}">

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-600 space-y-1">
                    <div class="font-bold text-slate-800">Ketentuan Berkas:</div>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                        <li>Format berkas: <strong>.xlsx</strong> atau <strong>.csv</strong> (Maksimal 2 MB, 500 baris).</li>
                        <li>Header kolom: <code>nama</code>, <code>asal_institusi</code>, <code>jenis</code>, <code>predikat</code>, <code>email</code>, <code>nim</code>.</li>
                        <li>Dilengkapi penetralan otomatis terhadap serangan formula injection.</li>
                    </ul>
                </div>

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Pilih Berkas XLSX / CSV <span class="text-rose-500">*</span></label>
                    <input type="file" name="file" required accept=".xlsx,.csv,.txt" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                </div>

                <div class="pt-2 flex justify-between items-center">
                    <a href="{{ route('admin.certificates.template') }}" class="text-emerald-700 font-bold hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">download</span> Unduh Templat CSV
                    </a>
                    <div class="flex gap-2">
                        <button type="button" @click="openImportModal = false" class="px-4 py-2 border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-700 text-white font-bold rounded-xl hover:bg-emerald-800 shadow-md shadow-emerald-700/20">Unggah & Terbitkan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL UBAH PENANDATANGAN KEGIATAN                        --}}
    {{-- ======================================================== --}}
    <div x-show="openAssignModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="openAssignModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-800">Penugasan Penandatangan Kegiatan</h3>
                <button type="button" @click="openAssignModal = false" class="text-slate-400 hover:text-slate-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form action="{{ route('admin.certificates.penandatangan.assign') }}" method="POST" class="space-y-3.5 text-xs">
                @csrf
                <input type="hidden" name="kegiatan_id" value="{{ $selectedKegiatan?->id }}">

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Berlaku Untuk Jenis Sertifikat</label>
                    <select name="jenis_sertifikat" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-bold">
                        <option value="semua">Semua Jenis (Bawaan)</option>
                        <option value="peserta">Khusus Peserta</option>
                        <option value="juri">Khusus Juri</option>
                        <option value="pembicara">Khusus Pembicara</option>
                        <option value="juara">Khusus Juara</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="font-bold text-slate-700 block uppercase tracking-wider">Pilih 1 s.d. 4 Penandatangan Berurutan:</label>
                    @for($i = 1; $i <= 4; $i++)
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-center font-bold text-slate-400">{{ $i }}.</span>
                            <select name="penandatangan_ids[]" {{ $i === 1 ? 'required' : '' }} class="w-full p-2 border border-slate-300 rounded-xl bg-slate-50">
                                <option value="" {{ $i > 2 ? 'selected' : '' }}>-- Kosong --</option>
                                @foreach($masterPenandatangan ?? [] as $mp)
                                    <option value="{{ $mp->id }}" {{ $loop->iteration === $i ? 'selected' : '' }}>
                                        {{ $mp->nama_lengkap }} ({{ $mp->jabatan }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="openAssignModal = false" class="px-4 py-2 border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-700 text-white font-bold rounded-xl hover:bg-emerald-800 shadow-md shadow-emerald-700/20">Simpan Penugasan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL EDIT DATA TAMPILAN                                 --}}
    {{-- ======================================================== --}}
    <div x-show="openEditDataModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="openEditDataModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-800">Edit Data Tampilan Sertifikat</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5" x-text="editTarget.nomor"></p>
                </div>
                <button type="button" @click="openEditDataModal = false" class="text-slate-400 hover:text-slate-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div x-show="editTarget.has_file" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs">
                <strong>Catatan Penting:</strong> Berkas PDF belum diperbarui. Perubahan data di sini akan segera tampil di halaman verifikasi tanpa mengubah berkas fisik atau hash dokumen awal.
            </div>

            <form :action="'{{ url('/admin/sertifikat') }}/' + editTarget.id + '/tampilan'" method="POST" class="space-y-3.5 text-xs">
                @csrf
                @method('PUT')

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Nama Penerima <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_penerima" x-model="editTarget.nama" required class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-bold text-slate-800">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Asal Institusi <span class="text-rose-500">*</span></label>
                        <input type="text" name="asal_institusi" x-model="editTarget.institusi" required class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Jenis Sertifikat <span class="text-rose-500">*</span></label>
                        <select name="jenis" x-model="editTarget.jenis" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50 font-bold">
                            <option value="peserta">Peserta</option>
                            <option value="juri">Juri</option>
                            <option value="pembicara">Pembicara</option>
                            <option value="juara">Juara</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Predikat</label>
                        <input type="text" name="predikat" x-model="editTarget.predikat" placeholder="Opsional / Juara" class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Keterangan Publik</label>
                        <input type="text" name="keterangan_publik" x-model="editTarget.keterangan" placeholder="Catatan singkat..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    </div>
                </div>

                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Alasan Perubahan Data <span class="text-rose-500">*</span></label>
                    <textarea name="alasan" required rows="2" placeholder="Wajib mengisi alasan perubahan (dicatat dalam riwayat audit)..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="openEditDataModal = false" class="px-4 py-2 border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-blue-700 text-white font-bold rounded-xl hover:bg-blue-800 shadow-md shadow-blue-700/20">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL CABUT SERTIFIKAT                                   --}}
    {{-- ======================================================== --}}
    <div x-show="openRevokeModalState" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-rose-200" @click.away="openRevokeModalState = false">
            <div class="flex items-center gap-3 text-rose-600">
                <span class="material-symbols-outlined text-3xl">warning</span>
                <div>
                    <h3 class="text-base font-black text-slate-800">Cabut Keabsahan Sertifikat</h3>
                    <p class="text-xs text-rose-600 font-bold" x-text="revokeTarget.nomor"></p>
                </div>
            </div>

            <p class="text-xs text-slate-600">
                Apakah Anda yakin ingin mencabut sertifikat atas nama <strong x-text="revokeTarget.nama"></strong>? 
                Sertifikat ini akan diberi status <strong>Dicabut</strong> pada halaman verifikasi publik.
            </p>

            <form :action="'{{ url('/admin/sertifikat') }}/' + revokeTarget.id + '/cabut'" method="POST" class="space-y-3.5 text-xs">
                @csrf
                <div>
                    <label class="font-bold text-slate-700 block uppercase tracking-wider mb-1">Alasan Pencabutan <span class="text-rose-500">*</span></label>
                    <textarea name="alasan" required rows="3" placeholder="Contoh: Terbukti melakukan pelanggaran plagiarisme atau pembatalan keikutsertaan..." class="w-full p-2.5 border border-slate-300 rounded-xl bg-slate-50"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="openRevokeModalState = false" class="px-4 py-2 border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-rose-600 text-white font-bold rounded-xl hover:bg-rose-700 shadow-md shadow-rose-600/20">Cabut Sertifikat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function certHubManager() {
    return {
        openManualModal: false,
        openImportModal: false,
        openAssignModal: false,
        openEditDataModal: false,
        openRevokeModalState: false,
        manualJenis: 'peserta',
        manualCertCode: '',
        selectedPesertaCount: 0,
        generateSerialCode() {
            const year = new Date().getFullYear();
            const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
            this.manualCertCode = `CERT-EVT-${year}-${rand}`;
        },
        editTarget: {
            id: null,
            nomor: '',
            nama: '',
            institusi: '',
            jenis: 'peserta',
            predikat: '',
            keterangan: '',
            has_file: false,
        },
        revokeTarget: {
            id: null,
            nomor: '',
            nama: '',
        },
        init() {
            this.updateSelectedCount();
        },
        toggleAllPeserta(event) {
            const checked = event.target.checked;
            document.querySelectorAll('.peserta-checkbox').forEach(cb => cb.checked = checked);
            this.updateSelectedCount();
        },
        selectAllPeserta(status) {
            document.querySelectorAll('.peserta-checkbox').forEach(cb => cb.checked = status);
            this.updateSelectedCount();
        },
        updateSelectedCount() {
            this.selectedPesertaCount = document.querySelectorAll('.peserta-checkbox:checked').length;
        },
        confirmBatchIssue() {
            this.updateSelectedCount();
            if (this.selectedPesertaCount === 0) {
                alert('Pilih minimal 1 peserta untuk diterbitkan sertifikat.');
                return;
            }
            if (confirm(`Terbitkan ${this.selectedPesertaCount} sertifikat sekarang? Sertifikat langsung tampil di menu Sertifikat peserta; tidak ada email yang dikirim.`)) {
                document.getElementById('batchIssueForm').submit();
            }
        },
        openEditModal(data) {
            this.editTarget = Object.assign({}, data);
            this.openEditDataModal = true;
        },
        openRevokeModal(id, nomor, nama) {
            this.revokeTarget = { id, nomor, nama };
            this.openRevokeModalState = true;
        }
    };
}
</script>
@endsection
