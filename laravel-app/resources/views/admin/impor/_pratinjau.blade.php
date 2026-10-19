{{--
    Tabel pratinjau impor CSV.
    Variabel: $periksa (hasil periksa/pratinjau), $kolom (kunci => label), $namaFile, $routeSimpan, $routeBatal, $judul
--}}
<div class="p-6 md:p-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">{{ $judul }}</h2>
            <p class="text-xs text-slate-400 mt-1">File: <span class="font-mono text-slate-300">{{ $namaFile }}</span></p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route($routeBatal) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold">Batalkan</button>
            </form>
            @if(! $periksa['kolom_hilang'] && ($periksa['ringkasan']['baru'] + $periksa['ringkasan']['perbarui']) > 0)
                <form action="{{ route($routeSimpan) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                        Simpan {{ $periksa['ringkasan']['baru'] + $periksa['ringkasan']['perbarui'] }} Baris Valid
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($periksa['kolom_hilang'])
        <div class="p-4 rounded-2xl bg-rose-950 border border-rose-800 text-rose-200 text-xs">
            Kolom wajib tidak ditemukan di header file: <strong>{{ implode(', ', $periksa['kolom_hilang']) }}</strong>. Gunakan template.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            @foreach(['total' => 'Total baris', 'baru' => 'Baru', 'perbarui' => 'Diperbarui', 'galat' => 'Galat (dilewati)'] as $k => $label)
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800">
                    <div class="text-slate-400 font-bold">{{ $label }}</div>
                    <div class="text-xl font-black {{ $k === 'galat' && $periksa['ringkasan'][$k] ? 'text-rose-400' : 'text-white' }}" data-ringkasan="{{ $k }}">{{ $periksa['ringkasan'][$k] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-4 py-3">Baris</th>
                            @foreach($kolom as $label)<th class="px-4 py-3">{{ $label }}</th>@endforeach
                            <th class="px-4 py-3">Hasil Validasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300">
                        @foreach($periksa['baris'] as $baris)
                            <tr class="{{ $baris['status'] === 'galat' ? 'bg-rose-950/30' : '' }}">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ $baris['baris'] }}</td>
                                @foreach(array_keys($kolom) as $k)<td class="px-4 py-3 {{ $k === 'nomor_tersamar' ? 'font-mono' : '' }}">{{ $baris[$k] ?? '-' }}</td>@endforeach
                                <td class="px-4 py-3">
                                    @if($baris['status'] === 'galat')
                                        <ul class="text-rose-300 list-disc list-inside">@foreach($baris['pesan'] as $p)<li>{{ $p }}</li>@endforeach</ul>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md font-bold {{ $baris['status'] === 'baru' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-sky-500/15 text-sky-300' }}">{{ $baris['status'] === 'baru' ? 'Baru' : 'Perbarui' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
