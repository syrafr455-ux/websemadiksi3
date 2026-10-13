<x-layouts.dashboard title="Monev Akademik (IPK)" pageTitle="Monitoring & Evaluasi Akademik" pageSubtitle="Tahap 3: Pemutakhiran capaian indeks prestasi & kondisi ekonomi">
    <div class="max-w-3xl mx-auto space-y-8">
        <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 flex items-start gap-4">
            <span class="material-symbols-outlined text-amber-700 text-2xl shrink-0 mt-0.5">trending_up</span>
            <div class="text-xs leading-relaxed space-y-1">
                <p class="font-bold text-sm text-amber-900">Standar Evaluasi Akademik KIP-K UNUSA</p>
                <p class="text-amber-800">Indeks Prestasi Kumulatif (IPK) minimal yang wajib dipertahankan adalah 3.00. Jika IPK di bawah standar, mahasiswa wajib mengikuti pembinaan akademik khusus.</p>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-10 shadow-sm">
            <h3 class="text-xl font-bold text-on-surface mb-6">Formulir Monev IPS / IPK Semester</h3>

            <form action="{{ route('dashboard.monev.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">IPS Terakhir (Semester Berjalan) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" min="0" max="4" name="ips" required placeholder="Contoh: 3.85" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">IPK Kumulatif Terkini <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" min="0" max="4" name="ipk" required placeholder="Contoh: 3.78" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Status Perkuliahan <span class="text-rose-500">*</span></label>
                    <select name="status_perkuliahan" required class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                        <option value="Aktif Perkuliahan">Aktif Perkuliahan Penuh</option>
                        <option value="Sedang Menyusun Skripsi / TA">Sedang Menyusun Skripsi / TA</option>
                        <option value="Sedang Magang / Studi Independen">Sedang Magang / Studi Independen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Tautan Kartu Hasil Studi (KHS) / Transkrip Nilai Resmi <span class="text-rose-500">*</span></label>
                    <input type="url" name="khs_url" required placeholder="https://drive.google.com/file/..." class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Pekerjaan Orang Tua / Wali</label>
                        <input type="text" name="pekerjaan_ortu" placeholder="Contoh: Petani / Wiraswasta" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Total Penghasilan Orang Tua</label>
                        <input type="text" name="penghasilan_ortu" placeholder="Contoh: Rp 1.500.000 / bulan" class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 flex items-center justify-center gap-2 transition-all hover:scale-[1.01]">
                    <span class="material-symbols-outlined text-xl">send</span>
                    Simpan Laporan Monev
                </button>
            </form>
        </div>

        @if($submissions->count() > 0)
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-on-surface">Riwayat Evaluasi Monev Anda</h3>
                <div class="space-y-3">
                    @foreach($submissions as $sub)
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-on-surface block">Evaluasi IPK: {{ $sub->answers['ipk'] ?? '-' }} (IPS: {{ $sub->answers['ips'] ?? '-' }})</span>
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
