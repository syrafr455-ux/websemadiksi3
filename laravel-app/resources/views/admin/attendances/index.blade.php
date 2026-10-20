@extends('layouts.admin')

@section('title', 'Verifikasi Presensi & Kehadiran')
@section('pageTitle', 'Verifikasi Presensi')
@section('pageSubtitle', 'Review foto selfie bukti presensi kegiatan mahasiswa secara real-time')

@section('content')
<div class="p-6 md:p-8 space-y-8" x-data="{ selectedAtt: null, previewImg: null }">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Verifikasi Presensi &amp; Kehadiran</h2>
            <p class="text-xs text-slate-400 mt-1">Review foto selfie bukti presensi kegiatan mahasiswa secara real-time.</p>
        </div>
    </div>

    {{-- Attendances Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Bukti Selfie</th>
                        <th class="px-6 py-4">Mahasiswa</th>
                        <th class="px-6 py-4">Kegiatan Acara</th>
                        <th class="px-6 py-4">Waktu Presensi</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($attendances as $att)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                @if($att->proof_image_url)
                                    <div 
                                        @click="previewImg = '{{ $att->proof_image_url }}'" 
                                        class="w-12 h-12 rounded-xl bg-slate-900 border border-slate-800 overflow-hidden cursor-pointer hover:border-amber-500 transition-colors group relative"
                                    >
                                        <img src="{{ $att->proof_image_url }}" alt="Bukti" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="material-symbols-outlined text-white text-base">zoom_in</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-600">
                                        <span class="material-symbols-outlined text-xl">no_photography</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-white text-sm">{{ $att->student_name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">NIM: {{ $att->student_nim }}</div>
                                <div class="text-[10px] text-slate-500">{{ $att->university }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-200">{{ $att->activity_title }}</div>
                                <div class="text-[11px] text-slate-500">{{ $att->activity_category ?: 'Kegiatan' }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                <div class="font-medium">{{ $att->submitted_at ? \App\Support\Wib::dari($att->submitted_at)->translatedFormat('d M Y, H:i').' WIB' : '-' }}</div>
                                <div class="text-[10px] text-slate-500">{{ $att->location_name ?: 'Kampus UNUSA' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($att->status === 'Hadir')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Hadir
                                    </span>
                                @elseif($att->status === 'Izin')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Izin
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        {{ $att->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button 
                                    @click="selectedAtt = {{ json_encode(['id' => $att->id, 'name' => $att->student_name, 'status' => $att->status, 'notes' => $att->notes]) }}"
                                    class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-800 font-bold text-[11px] transition-colors"
                                >
                                    Ubah Status
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Belum ada catatan presensi masuk.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attendances->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $attendances->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Update Status Presensi --}}
    <div 
        x-show="selectedAtt !== null" 
        x-transition:enter="transition ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <template x-if="selectedAtt !== null">
            <div 
                @click.away="selectedAtt = null" 
                class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Update Status Presensi</h3>
                        <p class="text-xs text-slate-400" x-text="selectedAtt.name"></p>
                    </div>
                    <button type="button" @click="selectedAtt = null" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <form :action="'{{ url('/admin/attendances') }}/' + selectedAtt.id + '/status'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Status Kehadiran</label>
                        <select name="status" x-model="selectedAtt.status" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                            <option value="Hadir">Hadir (Disetujui)</option>
                            <option value="Izin">Izin</option>
                            <option value="Tidak Hadir">Tidak Hadir / Ditolak</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Catatan Verifikator</label>
                        <textarea name="notes" x-model="selectedAtt.notes" rows="3" placeholder="Opsional, misal foto buram / verifikasi manual" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="selectedAtt = null" class="px-4 py-2 rounded-xl border border-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-900">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- Modal Preview Foto --}}
    <div 
        x-show="previewImg !== null" 
        @click="previewImg = null"
        class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4 cursor-pointer"
        style="display: none;"
    >
        <div class="max-w-2xl max-h-[85vh] rounded-2xl overflow-hidden border border-slate-800 shadow-2xl">
            <img :src="previewImg" alt="Preview Foto Bukti" class="w-full h-full object-contain">
        </div>
    </div>
</div>
@endsection
