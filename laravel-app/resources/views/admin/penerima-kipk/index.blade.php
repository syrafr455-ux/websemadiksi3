@extends('layouts.admin')

@section('title', 'Daftar Penerima KIP-K')
@section('pageTitle', 'Penerima KIP-K')
@section('pageSubtitle', 'Daftar resmi penerima beasiswa KIP-Kuliah UNUSA')

@section('content')
<div class="p-6 md:p-8 space-y-6" x-data="{
    nomor: {},
    async buka(id) {
        const r = await fetch(`{{ url('/admin/penerima-kipk') }}/${id}/nomor`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        });
        if (r.ok) { this.nomor[id] = (await r.json()).nomor; }
    },
}">
    <div>
        <h2 class="text-2xl font-black text-white">Daftar Penerima KIP-K</h2>
        <p class="text-xs text-slate-400 mt-1">
            Daftar resmi dari UNUSA berbasis nomor pendaftaran SIM KIP Kuliah (data sensitif: tersimpan terenkripsi, tampil tersamar;
            membuka nomor lengkap tercatat di audit log).
            Total: <strong class="text-slate-200">{{ number_format($total) }}</strong> penerima,
            <strong class="{{ $jumlahDitinjau ? 'text-amber-300' : 'text-slate-200' }}">{{ number_format($jumlahDitinjau) }}</strong> perlu ditinjau.
        </p>
    </div>

    @if(session('info_sinkron'))
        <div class="p-4 rounded-2xl bg-sky-950 border border-sky-800 text-sky-200 text-xs" role="status">{{ session('info_sinkron') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-950 border border-rose-800 text-rose-200 text-xs">{{ $errors->first() }}</div>
    @endif

    @include('admin.impor._unggah', [
        'routeUnggah' => 'admin.penerima-kipk.unggah',
        'routeTemplate' => 'admin.penerima-kipk.template',
        'kolom' => \App\Services\Impor\ImporPenerimaKipk::KOLOM,
        'kolomWajib' => \App\Services\Impor\ImporPenerimaKipk::KOLOM_WAJIB,
        'catatan' => 'Nomor dengan atau tanpa titik/spasi dianggap sama. Data disimpan per nomor pendaftaran (upsert). NIM opsional; bila ada dan cocok dengan akun user_umum UNUSA, akun itu otomatis dinaikkan menjadi mahasiswa_kipk.',
    ])

    <form method="GET" class="flex flex-col sm:flex-row gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari NIM atau nama..."
            class="flex-1 px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs">
        <select name="status" class="px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs" aria-label="Filter status">
            <option value="">Semua status</option>
            @foreach($filterStatus as $nilai => $label)
                <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-xl bg-slate-800 text-slate-200 text-xs font-bold">Terapkan</button>
    </form>

    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-4 py-3">No. Pendaftaran</th>
                        <th class="px-4 py-3">Nama / NIM</th>
                        <th class="px-4 py-3">Prodi / Penetapan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tinjauan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($penerima as $p)
                        <tr data-penerima="{{ $p->id }}" class="{{ $p->status_tinjau === 'perlu_ditinjau' ? 'bg-amber-950/20' : '' }}">
                            <td class="px-4 py-3 align-top">
                                <div class="font-mono" x-text="nomor[{{ $p->id }}] ?? @js($p->nomor_tersamar)">{{ $p->nomor_tersamar }}</div>
                                <button type="button" x-show="!nomor[{{ $p->id }}]" @click="buka({{ $p->id }})"
                                    class="mt-1 text-[11px] font-bold text-amber-400 hover:underline">Buka nomor lengkap</button>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="font-bold text-white">{{ $p->nama_lengkap }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $p->nim ?? 'NIM belum ada' }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                {{ $p->program_studi_teks ?? '-' }} <span class="text-slate-500">{{ $p->angkatan }}</span>
                                <div class="text-[11px] text-slate-500">{{ $p->status_penetapan ?? '-' }} &bull; {{ $p->sumber ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if($p->user)
                                    <span class="px-2 py-0.5 rounded-md font-bold bg-emerald-500/15 text-emerald-300">Terhubung</span>
                                    <div class="mt-1 text-white">{{ $p->user->name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $p->user->email }} &bull; {{ $p->cara_terhubung }}
                                        @if($p->dicocokkan_pada) &bull; {{ \App\Support\Wib::dari($p->dicocokkan_pada)->translatedFormat('d M Y H:i') }} WIB @endif
                                    </div>
                                @else
                                    <span class="px-2 py-0.5 rounded-md font-bold bg-slate-800 text-slate-300">Belum terhubung</span>
                                @endif
                                @if($p->status_tinjau === 'perlu_ditinjau')
                                    <div class="mt-2">
                                        <span class="px-2 py-0.5 rounded-md font-bold bg-amber-500/15 text-amber-300">Perlu ditinjau</span>
                                        <div class="text-[11px] text-amber-200 mt-1">
                                            @if($p->alasan_tinjau === 'klaim_nama_tidak_mirip')
                                                Klaim oleh {{ $p->pengklaim?->name ?? '-' }} ({{ $p->pengklaim?->nim }}): nama kurang mirip dengan "{{ $p->nama_lengkap }}".
                                            @elseif($p->alasan_tinjau === 'hilang_dari_impor')
                                                Tidak ada di impor daftar terbaru (status akun belum diubah).
                                            @else
                                                {{ $p->alasan_tinjau }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                @if($p->catatan_tinjau)
                                    <div class="text-[11px] text-slate-500 mt-1">Catatan: {{ $p->catatan_tinjau }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top min-w-[16rem]">
                                @if($p->status_tinjau === 'perlu_ditinjau')
                                    <form action="{{ route('admin.penerima-kipk.tinjau', $p->id) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <select name="keputusan" required class="w-full px-2 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-white">
                                            @if($p->alasan_tinjau === 'klaim_nama_tidak_mirip')
                                                <option value="setujui">Setujui klaim (tautkan &amp; naikkan role)</option>
                                                <option value="tolak">Tolak klaim</option>
                                            @else
                                                <option value="pertahankan">Pertahankan sebagai penerima</option>
                                                @if($p->user)<option value="cabut">Cabut status KIP-K akun</option>@endif
                                            @endif
                                        </select>
                                        <textarea name="alasan" required minlength="5" rows="2" placeholder="Alasan keputusan (wajib, tercatat di audit log)"
                                            class="w-full px-2 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-white"></textarea>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold">Simpan Keputusan</button>
                                    </form>
                                @else
                                    <span class="text-slate-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Tidak ada data penerima untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $penerima->links() }}
</div>
@endsection
