<x-layouts.dashboard title="Sertifikat Saya" pageTitle="Portofolio Sertifikat Digital" pageSubtitle="Daftar sertifikat keikutsertaan kegiatan resmi SEMADIKSI">
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($certificates as $cert)
                <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                        </div>
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            {{ $cert->status }}
                        </span>
                        <h3 class="text-base font-bold text-on-surface line-clamp-2">{{ $cert->activity_title }}</h3>
                        <div class="p-3 bg-surface-container rounded-xl text-xs font-mono text-outline space-y-1">
                            <p><span class="font-bold text-on-surface">Kode:</span> {{ $cert->code }}</p>
                            <p><span class="font-bold text-on-surface">Tanggal:</span> {{ $cert->issued_date ? date('d M Y', strtotime($cert->issued_date)) : '-' }}</p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-surface-variant/30 flex items-center justify-between">
                        <a href="{{ route('verifikasi.sertifikat', ['code' => $cert->code]) }}" target="_blank" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">verified</span>
                            Cek Validasi
                        </a>
                        @if($cert->file_url)
                            <a href="{{ $cert->file_url }}" target="_blank" class="px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-container transition-all">
                                Unduh PDF
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 bg-surface rounded-3xl border border-surface-variant/40 text-outline text-sm space-y-3">
                    <span class="material-symbols-outlined text-4xl text-outline/60">workspace_premium</span>
                    <p>Belum ada sertifikat yang diterbitkan atas nama Anda.</p>
                    <p class="text-xs">Sertifikat akan otomatis muncul setelah kehadiran kegiatan Anda diverifikasi oleh admin.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.dashboard>
