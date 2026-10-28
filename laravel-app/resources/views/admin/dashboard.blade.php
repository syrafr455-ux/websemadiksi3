<x-layouts.admin title="Dashboard Pengelola" pageTitle="Dashboard Utama Pengurus" pageSubtitle="Ringkasan Utama aktivitas beasiswa & basis data PostgreSQL">
    <div class="space-y-8">
        @if(!empty($tanpaPeriodeAktif))
            <div role="status" class="px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200 flex flex-wrap items-center justify-between gap-2">
                <span>Belum ada periode akademik aktif; batas kegiatan belum berlaku.</span>
                <a href="{{ route('admin.periode-akademik') }}" class="underline font-bold text-amber-100">Atur periode akademik</a>
            </div>
        @endif
        @if(!empty($emailQuotaStatus['is_near_limit']))
            <div class="bg-amber-950/40 border border-amber-600/40 rounded-2xl p-4 sm:p-5 flex items-start gap-4 text-amber-200">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">mail_lock</span>
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-amber-300">Peringatan Kuota Pengiriman Email</h4>
                    <p class="text-xs text-amber-200/90 leading-relaxed">
                        Pengiriman email hari ini telah mencapai <strong>{{ $emailQuotaStatus['used'] }}</strong> dari kuota <strong>{{ $emailQuotaStatus['quota'] }}</strong> ({{ $emailQuotaStatus['percentage'] }}%).
                        @if($emailQuotaStatus['is_exceeded'])
                            Batas kuota harian telah tercapai. Email massal (pengingat dan pengumuman) ditunda otomatis ke hari esok, sedangkan email kritis (aktivasi, reset kata sandi, tiket) tetap dikirim.
                        @else
                            Penggunaan email mendekati batas harian (&gt;= 80%). Jika kuota habis, email massal akan ditunda ke hari esok.
                        @endif
                    </p>
                </div>
            </div>
        @endif
        @if(!empty($menungguPersetujuanAnda) && $menungguPersetujuanAnda > 0)
            <div class="bg-amber-950/40 border border-amber-600/40 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-amber-200">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl">pending_actions</span>
                    </div>
                    <div class="space-y-0.5">
                        <h4 class="text-sm font-bold text-amber-300">Menunggu persetujuan Anda ({{ $menungguPersetujuanAnda }})</h4>
                        <p class="text-xs text-amber-200/90 leading-relaxed">
                            Terdapat {{ $menungguPersetujuanAnda }} permohonan penerbitan kegiatan baru yang membutuhkan verifikasi dan suara persetujuan Anda sebagai Super Administrator.
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.kegiatan', ['status' => 'diajukan']) }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black shrink-0 transition-colors text-center">
                    Tinjau Sekarang
                </a>
            </div>
        @endif

        {{-- Kartu Data Terkini Sistem (Pindahan dari Landing Page, Khusus Super Admin) --}}
        <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-xl">analytics</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Data Terkini Sistem</h3>
                        <p class="text-xs text-slate-400">Statistik internal basis data untuk Super Administrator</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300 text-[11px] font-semibold">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Super Admin
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Mahasiswa Aktif</span>
                    <span class="text-2xl font-black text-amber-400 mt-1 block">{{ number_format($stats['active_students']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Dari total {{ number_format($stats['total_students']) }} terdaftar</span>
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Total Kegiatan</span>
                    <span class="text-2xl font-black text-emerald-400 mt-1 block">{{ number_format($stats['total_kegiatan']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">{{ number_format($stats['kegiatan_aktif']) }} dipublikasikan / berlangsung</span>
                    @if($stats['legacy']['kegiatan'] > 0)
                        <span class="text-[10px] text-slate-500 mt-1 block" title="Tabel activities (model warisan), tidak termasuk angka di atas">Data lama: {{ number_format($stats['legacy']['kegiatan']) }} kegiatan</span>
                    @endif
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Sertifikat Terbit</span>
                    <span class="text-2xl font-black text-blue-400 mt-1 block">{{ number_format($stats['sertifikat_terbit']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Sertifikat valid</span>
                    @if($stats['sertifikat_dicabut'] > 0)
                        <span class="text-[10px] text-slate-500 mt-0.5 block">{{ number_format($stats['sertifikat_dicabut']) }} dicabut</span>
                    @endif
                    @if($stats['legacy']['sertifikat'] > 0)
                        <span class="text-[10px] text-slate-500 mt-1 block" title="Tabel certificates (model warisan), tidak termasuk angka di atas">Data lama: {{ number_format($stats['legacy']['sertifikat']) }} sertifikat</span>
                    @endif
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Kehadiran Peserta</span>
                    <span class="text-2xl font-black text-purple-400 mt-1 block">{{ number_format($stats['presensi_akhir']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Presensi akhir (form + tanda tangan)</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">{{ number_format($stats['tiket_hadir']) }} tiket tercatat hadir</span>
                    @if($stats['legacy']['presensi'] > 0)
                        <span class="text-[10px] text-slate-500 mt-1 block" title="Tabel attendances (model warisan), tidak termasuk angka di atas">Data lama: {{ number_format($stats['legacy']['presensi']) }} presensi</span>
                    @endif
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-amber-950/20 border border-amber-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-base">payments</span>
                    </div>
                    <div>
                        <span class="font-bold text-white block">Pencairan KIP-K Semester {{ $stats['pencairan']['periode'] }}</span>
                        <span class="text-[11px] text-slate-400">Pengajuan {{ $stats['pencairan']['mulai']->translatedFormat('j M Y') }} &ndash; {{ $stats['pencairan']['selesai']->translatedFormat('j M Y') }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold text-amber-400 bg-amber-950/60 px-3 py-1.5 rounded-xl border border-amber-800/40">{{ number_format($stats['pencairan']['menunggu']) }} Menunggu Review</span>
                    <span class="font-bold text-emerald-400 bg-emerald-950/60 px-3 py-1.5 rounded-xl border border-emerald-800/40">{{ number_format($stats['pencairan']['disetujui']) }} Disetujui</span>
                    @if($stats['pencairan']['menunggu_sebelumnya'] > 0)
                        <span class="font-semibold text-slate-300 px-3 py-1.5 rounded-xl border border-slate-700">+{{ number_format($stats['pencairan']['menunggu_sebelumnya']) }} menunggu dari periode sebelumnya</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Two Tables: Recent Students & Recent Attendances --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Students --}}
            <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-400 text-lg">person_add</span>
                        Pendaftar Mahasiswa Terbaru
                    </h3>
                    <a href="{{ route('admin.users') }}" class="text-xs font-bold text-amber-500 hover:underline">Kelola Semua</a>
                </div>

                <div class="divide-y divide-slate-800/60">
                    @forelse($recentStudents as $s)
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-white block">{{ $s->name }}</span>
                                <span class="text-slate-400">{{ $s->nim }} &bull; {{ $s->prodi }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $s->verification_status === 'Verified' ? 'bg-emerald-950 text-emerald-400' : 'bg-amber-950 text-amber-400' }}">
                                {{ $s->verification_status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-500 text-xs">Belum ada mahasiswa terdaftar.</div>
                    @endforelse
                </div>
            </div>

            {{-- Attendances --}}
            <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-400 text-lg">camera_enhance</span>
                        Presensi Terkini
                    </h3>
                    <a href="{{ route('admin.kegiatan') }}" class="text-xs font-bold text-amber-500 hover:underline">Lihat Kegiatan</a>
                </div>

                <div class="divide-y divide-slate-800/60">
                    @forelse($recentPresensi as $presensi)
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-white block">{{ $presensi->nama_lengkap }}</span>
                                <span class="text-slate-400">{{ $presensi->kegiatan?->nama_kegiatan ?? 'Kegiatan' }} &bull; {{ $presensi->nim }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 whitespace-nowrap">
                                {{ \App\Support\Wib::dari($presensi->check_in_at)?->translatedFormat('j M Y H:i') }} WIB
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-500 text-xs">Belum ada presensi masuk.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
