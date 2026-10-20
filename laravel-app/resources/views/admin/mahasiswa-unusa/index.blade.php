@extends('layouts.admin')

@section('title', 'Data Mahasiswa UNUSA')
@section('pageTitle', 'Mahasiswa UNUSA')
@section('pageSubtitle', 'Master mahasiswa untuk pendaftaran jalur Mahasiswa UNUSA')

@section('content')
<div class="p-6 md:p-8 space-y-6">
    <div>
        <h2 class="text-2xl font-black text-white">Data Mahasiswa UNUSA</h2>
        <p class="text-xs text-slate-400 mt-1">
            Master mahasiswa untuk pendaftaran jalur "Mahasiswa UNUSA" (isi otomatis dari NIM). Sementara diisi lewat impor CSV;
            nanti diganti API database UNUSA. Total: <strong class="text-slate-200">{{ number_format($total) }}</strong> mahasiswa.
        </p>
    </div>

    @include('admin.impor._unggah', [
        'routeUnggah' => 'admin.mahasiswa-unusa.unggah',
        'routeTemplate' => 'admin.mahasiswa-unusa.template',
        'kolom' => \App\Services\Impor\ImporMasterMahasiswa::KOLOM,
        'kolomWajib' => \App\Services\Impor\ImporMasterMahasiswa::KOLOM_WAJIB,
        'catatan' => 'program_studi diisi kode (mis. FEBTD-S1-SI) atau nama prodi persis dari master. status_aktif: '.implode(', ', \App\Models\MasterMahasiswaUnusa::STATUS_AKTIF).' (kosong = aktif). Data disimpan per NIM (upsert).',
    ])

    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari NIM atau nama..."
            class="flex-1 px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs">
        <button class="px-4 py-2 rounded-xl bg-slate-800 text-slate-200 text-xs font-bold">Cari</button>
    </form>

    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama Lengkap</th>
                        <th class="px-4 py-3">Email Kampus</th>
                        <th class="px-4 py-3">Program Studi / Fakultas</th>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Diperbarui</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($mahasiswa as $m)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $m->nim }}</td>
                            <td class="px-4 py-3 font-bold text-white">{{ $m->nama_lengkap }}</td>
                            <td class="px-4 py-3">{{ $m->email_kampus ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $m->programStudi?->nama ?? '-' }}<div class="text-[11px] text-slate-500">{{ $m->programStudi?->fakultas?->nama }}</div></td>
                            <td class="px-4 py-3">{{ $m->angkatan ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $m->status_aktif }}</td>
                            <td class="px-4 py-3">{{ $m->diperbarui_pada ? \App\Support\Wib::dari($m->diperbarui_pada)->translatedFormat('d M Y H:i').' WIB' : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada data mahasiswa. Impor dari CSV.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $mahasiswa->links() }}
</div>
@endsection
