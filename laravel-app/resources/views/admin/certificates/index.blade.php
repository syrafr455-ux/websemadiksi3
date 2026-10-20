@extends('layouts.admin')

@section('content')
<div class="p-6 md:p-8 space-y-8" x-data="{ modalOpen: false }">
    {{-- Header Actions --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Penerbitan &amp; Arsip Sertifikat</h2>
            <p class="text-xs text-slate-400 mt-1">Terbitkan e-sertifikat resmi ber-ID unik yang dapat diverifikasi publik.</p>
        </div>
        <button 
            @click="modalOpen = true" 
            class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-amber-600/20 active:scale-95 transition-all"
        >
            <span class="material-symbols-outlined text-base">workspace_premium</span>
            Terbitkan Sertifikat Baru
        </button>
    </div>

    {{-- Certificates Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Kode Unik</th>
                        <th class="px-6 py-4">Nama Mahasiswa</th>
                        <th class="px-6 py-4">Kegiatan Acara</th>
                        <th class="px-6 py-4">Tanggal Terbit</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($certificates as $cert)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-mono font-bold text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                                    {{ $cert->code }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-white">{{ $cert->user_name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $cert->user->email ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-200">{{ $cert->activity_title }}</div>
                                <div class="text-[11px] text-slate-500">{{ $cert->activity->category ?? 'Kegiatan SEMADIKSI' }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-300 font-medium">
                                {{ \Carbon\Carbon::parse($cert->issued_date)->translatedFormat('d F Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ $cert->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('verifikasi.sertifikat', ['code' => $cert->code]) }}" target="_blank" class="p-2 rounded-lg text-sky-400 hover:bg-sky-950/40 transition-colors" title="Cek Verifikasi Publik">
                                        <span class="material-symbols-outlined text-base">verified</span>
                                    </a>
                                    <form action="{{ route('admin.certificates.delete', $cert->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan/menghapus sertifikat {{ $cert->code }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 rounded-lg text-rose-400 hover:bg-rose-950/40 transition-colors" title="Hapus Sertifikat">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Belum ada sertifikat yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($certificates->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $certificates->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Terbitkan Sertifikat --}}
    <div 
        x-show="modalOpen" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        style="display: none;"
    >
        <div 
            @click.away="modalOpen = false" 
            class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-6 shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Terbitkan Sertifikat Baru</h3>
                    <p class="text-xs text-slate-400">Pilih mahasiswa dan kegiatan terkait.</p>
                </div>
                <button @click="modalOpen = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form action="{{ route('admin.certificates.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Pilih Peserta Penerima</label>
                    <select name="user_id" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                        <option value="">-- Pilih Peserta --</option>
                        @foreach($students as $st)
                            <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->nim ?: $st->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Pilih Agenda Kegiatan</label>
                    <select name="activity_id" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                        <option value="">-- Pilih Kegiatan --</option>
                        @foreach($activities as $ac)
                            <option value="{{ $ac->id }}">{{ $ac->title }} ({{ $ac->category }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Kode Unik Sertifikat (Opsional)</label>
                    <input type="text" name="code" placeholder="Kosongkan untuk generate otomatis sistem" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    <span class="text-[10px] text-slate-500 mt-1 block">Format otomatis: SMD-KATEGORI-2026-XXXX</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Tanggal Diterbitkan</label>
                    <input type="date" name="issued_date" value="{{ date('Y-m-d') }}" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 rounded-xl border border-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-900">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/20">
                        Terbitkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
