@extends('layouts.admin')

@section('title', 'Formulir & Laporan Masuk')
@section('pageTitle', 'Formulir Masuk')
@section('pageSubtitle', 'Review pengajuan pencairan beasiswa, pelaporan kegiatan, dan monev IPK mahasiswa')

@section('content')
<div class="p-6 md:p-8 space-y-8" x-data="{ selectedSub: null, viewDetail: null }">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Formulir &amp; Laporan Masuk</h2>
            <p class="text-xs text-slate-400 mt-1">Review pengajuan pencairan beasiswa, pelaporan kegiatan, dan monev IPK mahasiswa.</p>
        </div>
    </div>

    {{-- Submissions Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Mahasiswa &amp; NIM</th>
                        <th class="px-6 py-4">Jenis Formulir</th>
                        <th class="px-6 py-4">Tanggal Diajukan</th>
                        <th class="px-6 py-4">Status Pengajuan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($submissions as $sub)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-white text-sm">{{ $sub->applicant_name ?: ($sub->user?->name ?? '-') }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">NIM: {{ $sub->applicant_nim ?: ($sub->user?->nim ?: '-') }}</div>
                                @if($sub->user?->programStudi)
                                    <div class="text-[11px] text-slate-500">{{ $sub->user->programStudi->nama }}</div>
                                @else
                                    {{-- Hanya super_admin yang bisa melihat mahasiswa tanpa prodi (lihat User::mencakupMahasiswa). --}}
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">Prodi belum diatur</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-200">{{ $sub->form->title ?? 'Formulir KIP-K' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $sub->form->category ?? 'Umum' }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                {{ \App\Support\Wib::dari($sub->created_at)->translatedFormat('d F Y, H:i') }} WIB
                            </td>
                            <td class="px-6 py-4">
                                @if($sub->status === 'Disetujui')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Disetujui
                                    </span>
                                @elseif($sub->status === 'Perlu Revisi')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Perlu Revisi
                                    </span>
                                @elseif($sub->status === 'Ditolak')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                        {{ $sub->status ?: 'Pending' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button 
                                        @click="viewDetail = {{ json_encode(['title' => $sub->form->title ?? 'Formulir', 'student' => $sub->applicant_name ?: $sub->user?->name, 'answers' => $sub->answers, 'submitted_at' => $sub->submitted_at]) }}"
                                        class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 font-bold text-[11px] transition-colors"
                                    >
                                        Lihat Isian
                                    </button>

                                    <button 
                                        @click="selectedSub = {{ json_encode(['id' => $sub->id, 'student' => $sub->applicant_name ?: $sub->user?->name, 'form' => $sub->form->title ?? 'Formulir', 'status' => $sub->status, 'admin_notes' => $sub->admin_notes]) }}"
                                        class="px-3 py-1.5 rounded-lg bg-amber-600/20 hover:bg-amber-600/30 text-amber-400 font-bold text-[11px] transition-colors"
                                    >
                                        Update Status
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                Belum ada formulir atau laporan yang diajukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Lihat Isian Jawaban --}}
    <div 
        x-show="viewDetail !== null" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        style="display: none;"
    >
        <template x-if="viewDetail !== null">
            <div 
                @click.away="viewDetail = null" 
                class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-6 shadow-2xl max-h-[85vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white" x-text="viewDetail.title"></h3>
                        <p class="text-xs text-slate-400" x-text="'Oleh: ' + viewDetail.student"></p>
                    </div>
                    <button type="button" @click="viewDetail = null" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <div class="space-y-4 text-xs">
                    <template x-for="(val, key) in viewDetail.answers" :key="key">
                        <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 space-y-1">
                            <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px]" x-text="key"></span>
                            <div class="text-white font-medium" x-text="val"></div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-800">
                    <button type="button" @click="viewDetail = null" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-700">
                        Tutup
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Modal Update Status Submission --}}
    <div 
        x-show="selectedSub !== null" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <template x-if="selectedSub !== null">
            <div 
                @click.away="selectedSub = null" 
                class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Status Persetujuan Formulir</h3>
                        <p class="text-xs text-slate-400" x-text="selectedSub.form + ' (' + selectedSub.student + ')'"></p>
                    </div>
                    <button type="button" @click="selectedSub = null" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <form :action="'{{ url('/admin/submissions') }}/' + selectedSub.id + '/status'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Status Pengajuan</label>
                        <select name="status" x-model="selectedSub.status" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                            <option value="Disetujui">Disetujui</option>
                            <option value="Perlu Revisi">Perlu Revisi</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Catatan Admin</label>
                        <textarea name="admin_notes" x-model="selectedSub.admin_notes" rows="3" placeholder="Misal: Berkas telah diverifikasi untuk pencairan gelombang 1..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="selectedSub = null" class="px-4 py-2 rounded-xl border border-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-900">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">
                            Simpan Status
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>
@endsection
