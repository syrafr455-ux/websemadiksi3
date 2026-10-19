@extends('layouts.admin')

@section('content')
<div class="p-6 md:p-8 space-y-6">
    <div>
        <h2 class="text-2xl font-black text-white">Daftar Penerima KIP-K</h2>
        <p class="text-xs text-slate-400 mt-1">
            Daftar resmi dari UNUSA berbasis nomor pendaftaran SIM KIP Kuliah (data sensitif: tersimpan terenkripsi, tampil tersamar).
            Total: <strong class="text-slate-200">{{ number_format($total) }}</strong> penerima.
        </p>
    </div>

    @if(session('info_sinkron'))
        <div class="p-4 rounded-2xl bg-sky-950 border border-sky-800 text-sky-200 text-xs" role="status">{{ session('info_sinkron') }}</div>
    @endif

    @include('admin.impor._unggah', [
        'routeUnggah' => 'admin.penerima-kipk.unggah',
        'routeTemplate' => 'admin.penerima-kipk.template',
        'kolom' => \App\Services\Impor\ImporPenerimaKipk::KOLOM,
        'kolomWajib' => \App\Services\Impor\ImporPenerimaKipk::KOLOM_WAJIB,
        'catatan' => 'Nomor dengan atau tanpa titik/spasi dianggap sama. Data disimpan per nomor pendaftaran (upsert). NIM opsional.',
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
                        <th class="px-4 py-3">No. Pendaftaran</th>
                        <th class="px-4 py-3">Nama / NIM</th>
                        <th class="px-4 py-3">Prodi / Angkatan</th>
                        <th class="px-4 py-3">Penetapan / Sumber</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($penerima as $p)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $p->nomor_tersamar }}</td>
                            <td class="px-4 py-3"><div class="font-bold text-white">{{ $p->nama_lengkap }}</div><div class="text-[11px] text-slate-500 font-mono">{{ $p->nim ?? 'NIM belum ada' }}</div></td>
                            <td class="px-4 py-3">{{ $p->program_studi_teks ?? '-' }}<div class="text-[11px] text-slate-500">{{ $p->angkatan }}</div></td>
                            <td class="px-4 py-3">{{ $p->status_penetapan ?? '-' }}<div class="text-[11px] text-slate-500">{{ $p->sumber }}</div></td>
                            <td class="px-4 py-3">
                                @if($p->user)
                                    <span class="text-emerald-300 font-bold">Terhubung</span>
                                    <div class="text-[11px] text-slate-400">{{ $p->user->name }}</div>
                                @else
                                    <span class="text-slate-400">Belum terhubung</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada data penerima. Impor dari CSV.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $penerima->links() }}
</div>
@endsection
