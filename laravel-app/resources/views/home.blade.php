<x-layouts.app title="Beranda">
    {{-- Hero Section --}}
    <section class="relative pt-10 pb-16 lg:pt-16 lg:pb-24 border-b border-surface-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                <div class="lg:col-span-7 space-y-5">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        Sentra Komunikasi Mahasiswa KIP-K UNUSA
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-on-surface leading-[1.2]">
                        Layanan Informasi dan Kegiatan <span class="text-primary">Mahasiswa KIP-K UNUSA</span>
                    </h1>

                    <p class="text-base text-outline leading-relaxed max-w-2xl">
                        Portal resmi Serikat Mahasiswa KIP-K Universitas Nahdlatul Ulama Surabaya untuk pendaftaran kegiatan, pelaporan berkas beasiswa, presensi mandiri, dan perolehan e-sertifikat resmi.
                    </p>

                    <div class="flex flex-wrap items-center gap-3.5 pt-1">
                        @auth
                            <a href="{{ route('dashboard.index') }}" class="px-5 py-3 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm transition-all flex items-center gap-2 active:scale-95">
                                <span class="material-symbols-outlined text-lg">dashboard</span>
                                Buka Dashboard Saya
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="px-5 py-3 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm transition-all flex items-center gap-2 active:scale-95">
                                <span class="material-symbols-outlined text-lg">how_to_reg</span>
                                Daftar Akun
                            </a>
                            <a href="{{ route('login') }}" class="px-5 py-3 rounded-xl bg-surface border border-surface-variant hover:bg-surface-container text-on-surface font-semibold text-sm transition-all">
                                Masuk ke Akun
                            </a>
                        @endauth
                    </div>
                </div>

                {{-- Hero Visual Service Info Card (Static) --}}
                <div class="lg:col-span-5 relative">
                    <div class="relative mx-auto max-w-md bg-surface border border-surface-variant rounded-2xl p-6 sm:p-7 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-4 border-b border-surface-variant/40">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                                    <span class="material-symbols-outlined text-xl">verified_user</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-on-surface">Layanan Terpadu Mahasiswa</h4>
                                    <p class="text-xs text-outline">Portal Resmi KIP-Kuliah UNUSA</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                                Resmi
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-2xl bg-surface-container border border-surface-variant/30">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-lg">folder_shared</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface block">Pelaporan KIP-K</span>
                                <span class="text-[11px] text-outline mt-0.5 block">Monev & Berkas Berkala</span>
                            </div>
                            <div class="p-4 rounded-2xl bg-surface-container border border-surface-variant/30">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-lg">event</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface block">Agenda Kegiatan</span>
                                <span class="text-[11px] text-outline mt-0.5 block">Seminar, Workshop & Lomba</span>
                            </div>
                            <div class="p-4 rounded-2xl bg-surface-container border border-surface-variant/30">
                                <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-lg">workspace_premium</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface block">E-Sertifikat Digital</span>
                                <span class="text-[11px] text-outline mt-0.5 block">Terverifikasi Resmi</span>
                            </div>
                            <div class="p-4 rounded-2xl bg-surface-container border border-surface-variant/30">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-lg">how_to_reg</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface block">Presensi Mandiri</span>
                                <span class="text-[11px] text-outline mt-0.5 block">Kehadiran Kegiatan Terpadu</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-primary/5 border border-primary/20 flex items-center justify-between text-xs">
                            <span class="font-medium text-on-surface">Pusat Layanan Mahasiswa:</span>
                            <span class="font-bold text-primary">SEMADIKSI UNUSA</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Kegiatan Unggulan --}}
    <section class="py-16 bg-surface-container-low border-y border-surface-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-primary">Agenda & Pengembangan Diri</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface mt-1">Kegiatan Terbaru SEMADIKSI</h2>
                </div>
                <a href="{{ route('dashboard.kegiatan') }}" class="mt-4 md:mt-0 inline-flex items-center gap-1.5 text-sm font-bold text-primary hover:underline">
                    Lihat Semua Agenda
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @forelse($activities as $act)
                    <div class="bg-surface rounded-2xl border border-surface-variant overflow-hidden shadow-sm hover:border-primary/40 transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-48 overflow-hidden relative">
                                <img src="{{ $act->image_url }}" alt="{{ $act->title }}" class="w-full h-full object-cover">
                                <div class="absolute top-3 right-3 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-surface/95 text-primary shadow-sm">
                                    {{ $act->category }}
                                </div>
                                <div class="absolute bottom-3 left-3 px-2 py-0.5 rounded text-[11px] font-medium bg-black/70 text-white">
                                    {{ $act->date_text }}
                                </div>
                            </div>
                            <div class="p-6 space-y-3">
                                <h3 class="text-base font-bold text-on-surface line-clamp-2">{{ $act->title }}</h3>
                                <p class="text-xs text-outline line-clamp-3 leading-relaxed">{{ $act->description }}</p>
                                <div class="flex items-center gap-2 text-xs text-outline pt-1">
                                    <span class="material-symbols-outlined text-base text-primary">location_on</span>
                                    <span class="truncate">{{ $act->location }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 pt-0 border-t border-surface-variant/40 mt-4 flex items-center justify-between">
                            <span class="text-xs font-semibold text-primary bg-primary/10 px-2.5 py-1 rounded-md">
                                {{ $act->price }}
                            </span>
                            <a href="{{ !empty($act->is_kegiatan) ? route('kegiatan.detail', $act->id) : route('dashboard.kegiatan.show', $act->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                Rincian Kegiatan
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 text-outline text-sm bg-surface rounded-2xl border border-surface-variant">
                        Saat ini belum ada agenda kegiatan baru yang dibuka.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Berita Acara Terkini --}}
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-primary">Dokumentasi Resmi</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface mt-1">Berita Acara Kegiatan</h2>
                </div>
                <a href="{{ route('berita.acara.index') }}" class="mt-4 md:mt-0 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                    Arsip Berita Acara
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @forelse($beritaAcara as $ba)
                    <div class="bg-surface rounded-2xl border border-surface-variant p-6 shadow-sm hover:border-primary/40 transition-all space-y-4">
                        <div class="flex items-center justify-between text-xs text-outline">
                            <span class="px-2.5 py-0.5 rounded bg-surface-container font-medium text-primary">{{ $ba->category }}</span>
                            <span>{{ $ba->date_text }}</span>
                        </div>
                        <h3 class="text-base font-bold text-on-surface line-clamp-2">
                            <a href="{{ route('berita.acara.show', $ba->id) }}" class="hover:text-primary transition-colors">
                                {{ $ba->title }}
                            </a>
                        </h3>
                        <p class="text-xs text-outline line-clamp-3 leading-relaxed">{{ $ba->summary }}</p>
                        <div class="pt-3 border-t border-surface-variant/40 flex items-center justify-between text-xs">
                            <span class="text-outline truncate">Oleh: {{ $ba->author }}</span>
                            <a href="{{ route('berita.acara.show', $ba->id) }}" class="font-semibold text-primary hover:underline">Baca Selengkapnya &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 text-outline text-sm bg-surface rounded-2xl border border-surface-variant">
                        Belum ada berita acara yang diterbitkan.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- CTA Band --}}
    <section class="py-14 bg-primary text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-5">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Akses Layanan Mahasiswa KIP-K UNUSA</h2>
            <p class="text-sm sm:text-base text-white/90 max-w-2xl mx-auto leading-relaxed">
                Kelola pelaporan berkas beasiswa, ikuti kegiatan kemahasiswaan, dan peroleh e-sertifikat resmi dalam satu akun.
            </p>
            <div class="pt-2">
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white text-primary hover:bg-slate-50 font-semibold text-sm shadow-sm transition-all active:scale-95">
                    Daftar Akun
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
            </div>
        </div>
    </section>
</x-layouts.app>
