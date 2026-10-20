@extends('layouts.admin')

@section('title', 'Penerbitan & Arsip Sertifikat')
@section('pageTitle', 'Penerbitan Sertifikat')
@section('pageSubtitle', 'Terbitkan e-sertifikat resmi ber-ID unik yang terhubung ke sistem verifikasi QR')

@section('content')
@php
    $participantsMap = [];
    foreach ($kegiatans as $kg) {
        $pList = [];
        if (!empty($kg->presensis)) {
            foreach ($kg->presensis as $p) {
                $pList[] = [
                    'name' => $p->nama_lengkap,
                    'user_id' => $p->user_id,
                    'email' => $p->email,
                    'badge' => 'Presensi/Hadir',
                ];
            }
        }
        if (!empty($kg->registrations)) {
            foreach ($kg->registrations as $r) {
                $rName = $r->participant_name ?: ($r->participantUser?->name ?? null);
                if ($rName && ! collect($pList)->contains('name', $rName)) {
                    $pList[] = [
                        'name' => $rName,
                        'user_id' => $r->participant_user_id,
                        'email' => $r->participant_identity,
                        'badge' => 'Peserta Terdaftar',
                    ];
                }
            }
        }
        $participantsMap[$kg->id] = $pList;
    }
@endphp
<div class="p-4 sm:p-6 lg:p-8 space-y-6" x-data="certificateManager()">
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

    {{-- Layout 2 Kolom Persis Desain Asli --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- KOLOM KIRI (Form "Penerbitan Sertifikat") --}}
        <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-700 text-2xl">workspace_premium</span>
                    <h2 class="text-lg font-black text-slate-800 tracking-tight">Penerbitan Sertifikat</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Unggah template sertifikat resmi kegiatan untuk ditempeli identitas penerima dan QR code verifikasi.
                </p>
            </div>

            <form action="{{ route('admin.certificates.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                
                {{-- Dropdown "Pilih Kegiatan / Beasiswa" --}}
                <div class="space-y-1.5">
                    <label for="kegiatan_id" class="text-xs font-bold text-slate-700 block uppercase tracking-wider">
                        Pilih Kegiatan / Beasiswa <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="kegiatan_id" 
                        id="kegiatan_id" 
                        required 
                        x-model="selectedKegiatanId" 
                        @change="onKegiatanChange($event)"
                        class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none text-sm text-slate-800 font-medium cursor-pointer"
                    >
                        <option value="" disabled selected>-- Pilih Kegiatan Beasiswa --</option>
                        @foreach($kegiatans as $kg)
                            @php
                                $title = $kg->nama_kegiatan;
                                $codeInitials = '';
                                if (preg_match('/\(([A-Z0-9]+)\)/', $title, $matches)) {
                                    $codeInitials = $matches[1];
                                } elseif (preg_match('/\b([A-Z]{2,6})\b/', $title, $matches)) {
                                    $codeInitials = $matches[1];
                                } else {
                                    $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\s]/', '', $title));
                                    if (count($words) >= 4) {
                                        $codeInitials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1) . substr($words[3], 0, 1));
                                    } elseif (count($words) >= 2) {
                                        $codeInitials = strtoupper(substr($words[0], 0, 2) . substr($words[1], 0, 2));
                                    } else {
                                        $codeInitials = strtoupper(substr($title, 0, 4));
                                    }
                                }
                                $codeInitials = $codeInitials ?: 'EVT';
                            @endphp
                            <option value="{{ $kg->id }}" data-title="{{ $kg->nama_kegiatan }}" data-code="{{ $codeInitials }}">
                                {{ $kg->nama_kegiatan }} ({{ strtoupper($kg->kategori ?? 'UMUM') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Field "Penerima Sertifikat (Mahasiswa)" --}}
                <div class="space-y-1.5">
                    <label for="nama_lengkap" class="text-xs font-bold text-slate-700 block uppercase tracking-wider">
                        Penerima Sertifikat (Mahasiswa) <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="nama_lengkap" 
                        id="nama_lengkap" 
                        required 
                        list="students_datalist" 
                        x-model="recipientName"
                        @input="onRecipientInput($event)"
                        placeholder="Ketik Nama Lengkap Mahasiswa..." 
                        class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none text-sm text-slate-800 font-medium"
                    >
                    <datalist id="students_datalist">
                        <template x-for="p in activeParticipants" :key="'part_' + p.name">
                            <option :value="p.name" :data-user-id="p.user_id" :data-email="p.email" x-text="p.name + ' (' + p.badge + ')'"></option>
                        </template>
                        @foreach($students as $st)
                            <option value="{{ $st->name }}" data-user-id="{{ $st->id }}" data-email="{{ $st->email }}">
                                {{ $st->name }} ({{ $st->nim ?: $st->email }})
                            </option>
                        @endforeach
                    </datalist>
                    <input type="hidden" name="user_id" :value="selectedUserId">
                    <p class="text-[11px] text-slate-400">
                        Pilih dari daftar rekomendasi peserta/mahasiswa atau ketik bebas untuk penerima dari luar sistem.
                    </p>
                </div>

                {{-- Field "Nomor Seri Sertifikat" --}}
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center">
                        <label for="nomor_sertifikat" class="text-xs font-bold text-slate-700 block uppercase tracking-wider">
                            Nomor Seri Sertifikat
                        </label>
                        <button 
                            type="button" 
                            @click="generateSerialCode()" 
                            class="text-xs text-emerald-700 hover:text-emerald-800 font-bold hover:underline cursor-pointer flex items-center gap-1"
                        >
                            <span class="material-symbols-outlined text-sm">refresh</span> Generate Otomatis
                        </button>
                    </div>
                    <input 
                        type="text" 
                        name="nomor_sertifikat" 
                        id="nomor_sertifikat" 
                        x-model="certCode"
                        placeholder="Contoh: CERT-LKMB-2024-0891" 
                        class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none text-sm font-mono font-bold text-slate-800 uppercase"
                    >
                    <p class="text-[11px] text-slate-400">Format wajib: <span class="font-mono text-emerald-700 font-semibold">CERT-{KODE_KEGIATAN}-{TAHUN}-{NOMOR}</span></p>
                </div>

                {{-- Area "Lampiran Berkas Sertifikat (PDF)" Drag-and-Drop --}}
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 block uppercase tracking-wider">
                        Lampiran Berkas Sertifikat (PDF) <span class="text-rose-500">*</span>
                    </label>
                    <div 
                        class="border-2 border-dashed border-emerald-500/40 bg-emerald-50/40 rounded-2xl p-5 text-center cursor-pointer hover:bg-emerald-50/70 hover:border-emerald-600 transition-all relative"
                        @click="$refs.pdfInput.click()"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="handleFileDrop($event)"
                        :class="{ 'border-emerald-600 bg-emerald-100/50': dragOver }"
                    >
                        <input 
                            type="file" 
                            name="certificate_file" 
                            x-ref="pdfInput" 
                            required 
                            accept="application/pdf" 
                            class="hidden"
                            @change="handleFileChange($event)"
                        >
                        <span class="material-symbols-outlined text-emerald-700 text-4xl block mb-1">upload_file</span>
                        <p class="text-xs text-slate-700 font-bold" x-text="fileName || 'Seret berkas PDF di sini atau klik untuk memilih file'"></p>
                        <p class="text-[11px] text-slate-500 mt-1">Template sertifikat otomatis terhubung ke sistem verifikasi QR</p>
                        <p class="text-[10px] text-slate-400 mt-0.5" x-show="fileSize" x-text="'Ukuran berkas: ' + fileSize"></p>
                    </div>
                </div>

                {{-- Tombol Aksi "Terbitkan Sertifikat" --}}
                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-3.5 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-emerald-700/20 flex items-center justify-center gap-2 cursor-pointer transition-all"
                    >
                        <span>Terbitkan Sertifikat</span>
                        <span class="material-symbols-outlined text-lg">verified</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- KOLOM KANAN (Tabel "Daftar Sertifikat Terbit") --}}
        <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-800 tracking-tight">Daftar Sertifikat Terbit</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Sertifikat resmi terbitan sistem dengan status QR dan nomor seri unik.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start sm:self-auto">
                    Total: {{ $sertifikats->total() }} Sertifikat
                </span>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                            <th class="p-3">Mahasiswa</th>
                            <th class="p-3">Kegiatan</th>
                            <th class="p-3">No Seri</th>
                            <th class="p-3">Tanggal Terbit</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($sertifikats as $st)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-3">
                                    <div class="font-bold text-slate-900">{{ $st->recipient_name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $st->recipient_email }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold text-slate-800">{{ $st->kegiatan->nama_kegiatan ?? 'Kegiatan' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ strtoupper($st->kegiatan->kategori ?? 'SEMADIKSI') }}</div>
                                </td>
                                <td class="p-3">
                                    <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 text-[11px] block w-fit">
                                        {{ $st->nomor_sertifikat }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-500 font-medium whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($st->issued_at)->translatedFormat('d M Y') }}
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a 
                                            href="{{ route('verifikasi.sertifikat', ['code' => $st->nomor_sertifikat]) }}" 
                                            target="_blank" 
                                            class="p-1.5 rounded-lg text-emerald-700 hover:bg-emerald-50 transition-colors" 
                                            title="Pratinjau / Validasi Publik"
                                        >
                                            <span class="material-symbols-outlined text-base">verified</span>
                                        </a>
                                        <a 
                                            href="{{ route('sertifikat.download', $st->id) }}" 
                                            class="p-1.5 rounded-lg text-slate-700 hover:bg-slate-100 transition-colors" 
                                            title="Unduh Berkas PDF Resmi"
                                        >
                                            <span class="material-symbols-outlined text-base">download</span>
                                        </a>
                                        <form 
                                            action="{{ route('admin.certificates.delete', $st->id) }}" 
                                            method="POST" 
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus sertifikat {{ $st->nomor_sertifikat }}?');"
                                            class="inline-block"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="submit" 
                                                class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors" 
                                                title="Hapus Sertifikat"
                                            >
                                                <span class="material-symbols-outlined text-base">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    <span class="material-symbols-outlined text-3xl text-slate-300 block mb-1">history_edu</span>
                                    Belum ada sertifikat terbit di pangkalan data.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($sertifikats->hasPages())
                <div class="pt-3">
                    {{ $sertifikats->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function certificateManager() {
    return {
        participantsMap: @json($participantsMap),
        activeParticipants: [],
        selectedKegiatanId: '',
        selectedKegiatanCode: 'LKMB',
        recipientName: '',
        selectedUserId: '',
        certCode: '',
        dragOver: false,
        fileName: '',
        fileSize: '',

        onKegiatanChange(e) {
            const opt = e.target.selectedOptions[0];
            if (opt) {
                this.selectedKegiatanCode = opt.getAttribute('data-code') || 'EVT';
                this.activeParticipants = this.participantsMap[this.selectedKegiatanId] || [];
                this.generateSerialCode();
            }
        },

        onRecipientInput(e) {
            const val = e.target.value;
            const datalist = document.getElementById('students_datalist');
            let foundUserId = '';
            if (datalist) {
                for (let i = 0; i < datalist.options.length; i++) {
                    if (datalist.options[i].value === val) {
                        foundUserId = datalist.options[i].getAttribute('data-user-id') || '';
                        break;
                    }
                }
            }
            this.selectedUserId = foundUserId;
        },

        generateSerialCode() {
            const year = new Date().getFullYear();
            const code = this.selectedKegiatanCode || 'LKMB';
            const randomNum = String(Math.floor(1000 + Math.random() * 9000)).padStart(4, '0');
            this.certCode = `CERT-${code}-${year}-${randomNum}`;
        },

        handleFileChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.fileName = file.name;
                this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            }
        },

        handleFileDrop(e) {
            this.dragOver = false;
            const file = e.dataTransfer.files[0];
            if (file && file.type === 'application/pdf') {
                this.$refs.pdfInput.files = e.dataTransfer.files;
                this.fileName = file.name;
                this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            } else {
                alert('Silakan pilih berkas PDF yang valid.');
            }
        },

        init() {
            this.generateSerialCode();
        }
    };
}
</script>
@endsection
