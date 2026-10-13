<x-layouts.dashboard title="Pelaporan Keaktifan & Lomba" pageTitle="Pelaporan Prestasi & Ormawa" pageSubtitle="Tahap 2: Dokumentasi partisipasi organisasi dan kejuaraan perlombaan">
    <div class="max-w-3xl mx-auto space-y-8">
        <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 flex items-start gap-4">
            <span class="material-symbols-outlined text-emerald-700 text-2xl shrink-0 mt-0.5">emoji_events</span>
            <div class="text-xs leading-relaxed space-y-1">
                <p class="font-bold text-sm text-emerald-900">Kewajiban Pelaporan Prestasi Mahasiswa KIP-Kuliah</p>
                <p class="text-emerald-800">Setiap semester, mahasiswa penerima KIP-Kuliah diwajibkan aktif berpartisipasi dalam organisasi kemahasiswaan atau mengikuti kompetisi akademik/non-akademik.</p>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-10 shadow-sm">
            <h3 class="text-xl font-bold text-on-surface mb-6">Formulir Pelaporan Keaktifan</h3>

            <form action="{{ route('dashboard.pelaporan.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Nama Ormawa / Kepanitiaan / Lomba <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_kegiatan" required placeholder="Contoh: BEM UNUSA / Juara 2 Lomba Karya Tulis Ilmiah Nasional" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Peran / Jabatan / Tingkat <span class="text-rose-500">*</span></label>
                        <input type="text" name="peran" required placeholder="Contoh: Anggota Divisi / Juara 2 Nasional" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Periode / Tanggal Pelaksanaan <span class="text-rose-500">*</span></label>
                        <input type="text" name="tanggal" required placeholder="Contoh: Semester Ganjil 2026/2027" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Tautan Bukti SK / Piagam / Sertifikat (Google Drive) <span class="text-rose-500">*</span></label>
                    <input type="url" name="link_bukti" required placeholder="https://drive.google.com/file/..." class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Deskripsi Kontribusi / Ringkasan Prestasi</label>
                    <textarea name="deskripsi" rows="3" placeholder="Jelaskan peran aktif atau capaian Anda..." class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary"></textarea>
                </div>

                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 flex items-center justify-center gap-2 transition-all hover:scale-[1.01]">
                    <span class="material-symbols-outlined text-xl">send</span>
                    Kirim Laporan Prestasi
                </button>
            </form>
        </div>

        @if($submissions->count() > 0)
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-on-surface">Riwayat Pelaporan Keaktifan</h3>
                <div class="space-y-3">
                    @foreach($submissions as $sub)
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-on-surface block">{{ $sub->answers['nama_kegiatan'] ?? 'Laporan Keaktifan' }}</span>
                                <span class="text-outline text-[11px]">{{ $sub->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold {{ $sub->status === 'Disetujui' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $sub->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
