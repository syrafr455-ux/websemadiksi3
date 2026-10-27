<x-layouts.dashboard title="Riwayat Presensi Kegiatan" pageTitle="Riwayat Presensi Kegiatan" pageSubtitle="Catatan riwayat kehadiran kegiatan dan panduan alur presensi resmi dua tahap">
    <div class="space-y-8">
        {{-- Card Penjelasan Alur Presensi 2 Tahap --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Alur Presensi Resmi 2-Tahap</h3>
                    <p class="text-xs text-outline mt-0.5">Kehadiran peserta kini dicatat secara otomatis melalui sistem tiket terpadu dan presensi akhir berbatas waktu.</p>
                </div>
                <a href="{{ route('dashboard.kegiatan.saya') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary hover:bg-primary-container text-white text-xs font-bold shadow-sm transition-all self-start sm:self-auto">
                    <span class="material-symbols-outlined text-sm">confirmation_number</span>
                    <span>Buka E-Tiket di Kegiatan Saya</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Tahap 1 --}}
                <div class="p-5 rounded-2xl bg-surface-container border border-surface-variant/30 space-y-2">
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-black">1</span>
                        <span>Tahap 1: Check-in Awal</span>
                    </div>
                    <p class="text-xs text-on-surface leading-relaxed">
                        <strong>Kegiatan Luring / Hybrid:</strong> Tunjukkan QR E-Tiket Anda kepada petugas panitia di meja registrasi lokasi acara.<br>
                        <strong>Kegiatan Daring:</strong> Klik tombol <em>"Gabung Sekarang"</em> di halaman Kegiatan Saya saat ruang pertemuan dibuka.
                    </p>
                </div>

                {{-- Tahap 2 --}}
                <div class="p-5 rounded-2xl bg-surface-container border border-surface-variant/30 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs uppercase tracking-wider">
                        <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-700 flex items-center justify-center text-xs font-black">2</span>
                        <span>Tahap 2: Presensi Akhir</span>
                    </div>
                    <p class="text-xs text-on-surface leading-relaxed">
                        Pindai kode QR dinamis di layar proyektor acara menjelang penutupan kegiatan dan bubuhkan tanda tangan digital Anda. Sertifikat resmi akan diverifikasi dan diterbitkan panitia setelah kegiatan selesai.
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-700 text-lg shrink-0 mt-0.5">info</span>
                <div class="leading-relaxed">
                    <strong>Pemberitahuan:</strong> Formulir unggah swafoto mandiri sebelumnya telah dipensiunkan demi akurasi verifikasi. Seluruh riwayat presensi lama Anda tetap tersimpan dan dapat dilihat pada tabel di bawah.
                </div>
            </div>
        </div>

        {{-- Attendance History Table --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Riwayat Kehadiran Anda</h3>
                    <p class="text-xs text-outline mt-0.5">Daftar presensi kegiatan yang tercatat di sistem SEMADIKSI.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-surface-container text-on-surface border border-surface-variant/40">
                    Total: {{ count($attendances) }} Catatan
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-variant/40 text-outline uppercase font-bold">
                            <th class="py-3 px-4">Kegiatan</th>
                            <th class="py-3 px-4">Waktu Presensi</th>
                            <th class="py-3 px-4">Lokasi</th>
                            <th class="py-3 px-4">Sumber</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-variant/20">
                        @forelse($attendances as $att)
                            <tr class="hover:bg-surface-container/50">
                                <td class="py-3.5 px-4 font-bold text-on-surface">{{ $att->activity_title }}</td>
                                <td class="py-3.5 px-4 text-outline">{{ $att->submitted_at ? \App\Support\Wib::dari($att->submitted_at)->translatedFormat('d M Y, H:i').' WIB' : '-' }}</td>
                                <td class="py-3.5 px-4 text-outline">{{ $att->location_name ?? '-' }}</td>
                                <td class="py-3.5 px-4">
                                    @if(!empty($att->is_legacy))
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                            Legacy
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800 border border-teal-300">
                                            Presensi Resmi
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $att->status === 'Hadir' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $att->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-outline">
                                    <span class="material-symbols-outlined text-4xl text-outline/40 block mb-2">fact_check</span>
                                    <p class="font-bold text-on-surface">Belum ada riwayat presensi yang tercatat.</p>
                                    <p class="text-xs text-outline mt-1">Ikuti kegiatan dan selesaikan alur presensi dua tahap untuk mencatat kehadiran.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
