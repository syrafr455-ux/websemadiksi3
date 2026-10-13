<x-layouts.dashboard title="Berkas Dokumen KIP-K" pageTitle="Arsip Berkas Digital" pageSubtitle="Unggah dan kelola kelengkapan dokumen beasiswa Anda">
    <div class="space-y-8">
        {{-- Upload Card --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm">
            <h3 class="text-base font-bold text-on-surface mb-1">Unggah Dokumen Baru</h3>
            <p class="text-xs text-outline mb-6">Pilih kategori berkas (Kartu KIP-K, SKTM, KHS/Transkrip, atau Sertifikat Prestasi).</p>

            <form action="{{ route('dashboard.berkas.upload') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Judul Dokumen <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" required placeholder="Contoh: Kartu KIP-K Digital 2026" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Kategori Berkas <span class="text-rose-500">*</span></label>
                    <select name="category" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                        <option value="Kartu KIP-K">Kartu KIP-K Digital</option>
                        <option value="SKTM">SKTM (Surat Keterangan Tidak Mampu)</option>
                        <option value="KHS / Transkrip">KHS / Transkrip Nilai Akademik</option>
                        <option value="Keaktifan Ormawa">Surat Keputusan / SK Keaktifan Ormawa</option>
                        <option value="Keikutsertaan Kompetisi">Sertifikat / Bukti Prestasi Lomba</option>
                        <option value="Dokumen Tambahan">Dokumen Pendukung Lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Tahapan Terkait <span class="text-rose-500">*</span></label>
                    <select name="tahap" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                        <option value="1">Tahap 1: Pengajuan Pencairan Beasiswa</option>
                        <option value="2">Tahap 2: Pelaporan Keaktifan & Lomba</option>
                        <option value="3">Tahap 3: Monev Akademik & Ekonomi</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Pilih File (PDF atau Gambar) <span class="text-rose-500">*</span></label>
                    <input type="file" name="document_file" required accept=".pdf,image/*" class="block w-full text-xs text-outline file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-primary-container cursor-pointer">
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-xs shadow-md shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">upload_file</span>
                        Unggah Berkas ke Database
                    </button>
                </div>
            </form>
        </div>

        {{-- Documents Table --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-on-surface">Daftar Dokumen Anda</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-variant/40 text-outline uppercase font-bold">
                            <th class="py-3 px-4">Nama Dokumen</th>
                            <th class="py-3 px-4">Kategori</th>
                            <th class="py-3 px-4">Tahap</th>
                            <th class="py-3 px-4">Ukuran</th>
                            <th class="py-3 px-4">Status & Nilai</th>
                            <th class="py-3 px-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-variant/20">
                        @forelse($documents as $doc)
                            <tr class="hover:bg-surface-container/50">
                                <td class="py-3 px-4 font-bold text-on-surface">{{ $doc->title }}</td>
                                <td class="py-3 px-4 text-outline">{{ $doc->category }}</td>
                                <td class="py-3 px-4 text-outline">Tahap {{ $doc->tahap }}</td>
                                <td class="py-3 px-4 font-mono text-outline">{{ $doc->file_size ?? '-' }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $doc->status === 'Disetujui' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $doc->status }} ({{ $doc->score }}/100)
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="{{ $doc->file_url }}" target="_blank" class="font-bold text-primary hover:underline">
                                        Buka File
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-outline">Belum ada berkas tersimpan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
