@extends('layouts.admin')

@section('title', 'Verifikasi Berkas KIP-K')
@section('pageTitle', 'Verifikasi & Penilaian Berkas')
@section('pageSubtitle', 'Review kelayakan dokumen persyaratan beasiswa, scoring, dan verifikasi berkas digital')

@section('content')
<div class="p-6 md:p-8 space-y-8" x-data="{
    isOpen: false,
    docId: '',
    docTitle: '',
    docStudent: '',
    docStatus: 'Disetujui',
    docScore: 100,
    docNotes: '',
    openModal(doc) {
        this.docId = doc?.id || '';
        this.docTitle = doc?.title || '';
        this.docStudent = doc?.student || '';
        this.docStatus = doc?.status || 'Disetujui';
        this.docScore = doc?.score ?? 100;
        this.docNotes = doc?.notes || '';
        this.isOpen = true;
    },
    closeModal() {
        this.isOpen = false;
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Verifikasi &amp; Penilaian Berkas KIP-K</h2>
            <p class="text-xs text-slate-400 mt-1">Review kelayakan dokumen persyaratan beasiswa, scoring, dan verifikasi berkas digital.</p>
        </div>
    </div>

    {{-- Documents Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Mahasiswa &amp; NIM</th>
                        <th class="px-6 py-4">Nama Dokumen &amp; Jenis</th>
                        <th class="px-6 py-4">Berkas File</th>
                        <th class="px-6 py-4">Skor / Nilai</th>
                        <th class="px-6 py-4">Status Review</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($documents as $doc)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-white text-sm">{{ $doc->user_name ?: 'Mahasiswa KIP-K' }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">NIM: {{ $doc->user_nim ?: '-' }}</div>
                                @if($doc->user?->programStudi)
                                    <div class="text-[11px] text-slate-500">{{ $doc->user->programStudi->nama }}</div>
                                @else
                                    {{-- Hanya super_admin yang bisa melihat mahasiswa tanpa prodi (lihat User::mencakupMahasiswa). --}}
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">Prodi belum diatur</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-200">{{ $doc->title }}</div>
                                <div class="text-[11px] text-slate-500">{{ $doc->category }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($doc->file_url)
                                    <a href="{{ $doc->file_url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-800 font-semibold transition-colors">
                                        <span class="material-symbols-outlined text-sm">download</span>
                                        Buka Dokumen
                                    </a>
                                @else
                                    <span class="text-slate-500 italic">Tidak ada file</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($doc->score !== null)
                                    <span class="font-bold text-sm text-emerald-400">{{ $doc->score }} / 100</span>
                                @else
                                    <span class="text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($doc->status === 'Disetujui')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Disetujui
                                    </span>
                                @elseif($doc->status === 'Perlu Revisi')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Perlu Revisi
                                    </span>
                                @elseif($doc->status === 'Ditolak')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                        {{ $doc->status ?: 'Menunggu Review' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button 
                                    @click="openModal({{ json_encode(['id' => $doc->id, 'title' => $doc->title, 'student' => $doc->user_name, 'status' => $doc->status, 'score' => $doc->score ?? 100, 'notes' => $doc->notes]) }})"
                                    class="px-3 py-1.5 rounded-lg bg-amber-600/20 hover:bg-amber-600/30 text-amber-400 font-bold text-[11px] transition-colors"
                                >
                                    Review &amp; Nilai
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Belum ada berkas KIP-K yang diunggah.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Review & Grading Berkas --}}
    <div 
        x-show="isOpen" 
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
            @click.away="closeModal()" 
            class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-white">Penilaian &amp; Verifikasi Berkas</h3>
                    <p class="text-xs text-slate-400" x-text="docTitle + (docStudent ? ' (' + docStudent + ')' : '')"></p>
                </div>
                <button type="button" @click="closeModal()" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form :action="'{{ url('/admin/berkas') }}/' + docId + '/status'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Status Verifikasi</label>
                    <select name="status" x-model="docStatus" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                        <option value="Disetujui">Disetujui / Valid</option>
                        <option value="Perlu Revisi">Perlu Revisi</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Skor Dokumen (0 - 100)</label>
                    <input type="number" name="score" x-model="docScore" min="0" max="100" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Catatan Reviewer / Alasan Revisi</label>
                    <textarea name="notes" x-model="docNotes" rows="3" placeholder="Catatan untuk mahasiswa..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="closeModal()" class="px-4 py-2 rounded-xl border border-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-900">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">
                        Simpan Penilaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
