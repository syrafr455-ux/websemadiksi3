{{--
    Kartu unggah CSV untuk impor admin.
    Variabel: $routeUnggah, $routeTemplate, $kolom (list kolom), $kolomWajib (list), $catatan (opsional)
--}}
<div class="bg-slate-950 border border-slate-800 rounded-2xl p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-white">Impor dari CSV</h3>
            <p class="text-[11px] text-slate-400">Unggah file, periksa pratinjau validasi per baris, lalu simpan. Baris bergalat dilewati.</p>
        </div>
        <a href="{{ route($routeTemplate) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold">
            <span class="material-symbols-outlined text-sm">download</span>
            Unduh Template CSV
        </a>
    </div>

    <div class="text-[11px] text-slate-400 space-y-1">
        <div>Kolom:
            @foreach($kolom as $k)
                <code class="px-1.5 py-0.5 rounded bg-slate-900 {{ in_array($k, $kolomWajib, true) ? 'text-amber-300' : 'text-slate-300' }}">{{ $k }}</code>
            @endforeach
            <span class="text-slate-500">(kuning = wajib)</span>
        </div>
        <div>Excel: simpan sebagai <strong>CSV UTF-8</strong>. Pemisah koma, titik koma, atau tab dikenali otomatis. Maks. 5 MB.</div>
        @isset($catatan)<div>{{ $catatan }}</div>@endisset
    </div>

    <form action="{{ route($routeUnggah) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 sm:items-center">
        @csrf
        <input type="file" name="file" accept=".csv,text/csv" required
            class="flex-1 text-xs text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-500">
        <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">Pratinjau Impor</button>
    </form>
    @error('file')<p class="text-[11px] text-rose-400">{{ $message }}</p>@enderror
</div>
