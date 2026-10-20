@extends('layouts.admin')

@section('title', 'Kelola Kegiatan & Event')
@section('pageTitle', 'Kelola Kegiatan')
@section('pageSubtitle', 'Daftar agenda resmi, kuota peserta, dan konfigurasi pendaftaran')

@section('content')
<div class="p-6 md:p-8 space-y-8" x-data="{ modalOpen: false }">
    {{-- Header Actions --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Kelola Kegiatan &amp; Event</h2>
            <p class="text-xs text-slate-400 mt-1">Daftar agenda resmi, kuota peserta, dan konfigurasi pendaftaran.</p>
        </div>
        <button 
            @click="modalOpen = true" 
            class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-amber-600/20 active:scale-95 transition-all"
        >
            <span class="material-symbols-outlined text-base">add</span>
            Tambah Kegiatan Baru
        </button>
    </div>

    {{-- Activities Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Kegiatan</th>
                        <th class="px-6 py-4">Kategori &amp; Tipe</th>
                        <th class="px-6 py-4">Waktu &amp; Lokasi</th>
                        <th class="px-6 py-4">Kuota / Terdaftar</th>
                        <th class="px-6 py-4">XP Poin</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($activities as $act)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-slate-900 overflow-hidden shrink-0 border border-slate-800">
                                        <img src="{{ $act->image_url ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=100' }}" class="w-full h-full object-cover" alt="">
                                    </div>
                                    <div>
                                        <span class="font-bold text-white text-sm block">{{ $act->title }}</span>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">{{ Str::limit($act->speaker, 30) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    {{ $act->category }}
                                </span>
                                <span class="text-[11px] text-slate-400 block mt-1">{{ $act->type }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-200">{{ $act->date_text }}</div>
                                <div class="text-[11px] text-slate-400">{{ Str::limit($act->location, 25) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-white">{{ $act->registrations_count }}</span>
                                <span class="text-slate-500">/ {{ $act->quota }}</span>
                                @if($act->enable_seat_booking)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[9px] bg-sky-500/10 text-sky-400 border border-sky-500/20 ml-1">Pilih Kursi</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-emerald-400">+{{ $act->xp_points }} XP</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('admin.kegiatan.delete', $act->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini? Seluruh data pendaftaran terkait akan ikut dihapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-rose-400 hover:bg-rose-950/40 transition-colors" title="Hapus Kegiatan">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Belum ada data kegiatan. Klik tombol Tambah Kegiatan Baru di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activities->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $activities->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Tambah Kegiatan --}}
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
            class="bg-slate-950 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl my-8 max-h-[90vh] overflow-y-auto"
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Tambah Kegiatan / Event Baru</h3>
                    <p class="text-xs text-slate-400">Data tersimpan langsung ke PostgreSQL.</p>
                </div>
                <button @click="modalOpen = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-800">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form action="{{ route('admin.kegiatan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Judul Kegiatan</label>
                    <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Kategori</label>
                        <select name="category" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                            <option value="Sosialisasi KIP-K">Sosialisasi KIP-K</option>
                            <option value="Pelatihan">Pelatihan</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Lomba">Lomba</option>
                            <option value="Pengabdian Masyarakat">Pengabdian Masyarakat</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Tipe Pelaksanaan</label>
                        <select name="type" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                            <option value="Luring">Luring (Offline)</option>
                            <option value="Daring">Daring (Online)</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Tanggal &amp; Waktu</label>
                        <input type="text" name="date_text" placeholder="Contoh: 28 Oktober 2026, 08:30 WIB" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Lokasi</label>
                        <input type="text" name="location" placeholder="Contoh: Auditorium Tower UNUSA Lt. 9" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Biaya / HTM</label>
                        <input type="text" name="price" value="Gratis" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Kuota Peserta</label>
                        <input type="number" name="quota" value="100" min="1" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Poin XP</label>
                        <input type="number" name="xp_points" value="150" min="0" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Narasumber / Pembicara</label>
                    <input type="text" name="speaker" placeholder="Contoh: Dr. Ir. H. Achmad Jazidie, M.Eng." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Deskripsi Lengkap</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Foto Poster Banner</label>
                    <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 text-xs">
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="enable_seat_booking" value="1" class="rounded bg-slate-900 border-slate-800 text-amber-500 focus:ring-0">
                        <span class="text-xs text-slate-300 font-semibold">Aktifkan Pemilihan Kursi Interaktif</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="require_file_upload" value="1" class="rounded bg-slate-900 border-slate-800 text-amber-500 focus:ring-0">
                        <span class="text-xs text-slate-300 font-semibold">Wajib Upload Berkas Syarat</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 rounded-xl border border-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-900">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/20">
                        Simpan Kegiatan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
