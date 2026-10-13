<x-layouts.dashboard title="Beranda Ringkasan" pageTitle="Beranda Mahasiswa" pageSubtitle="Selamat datang di Portal Layanan KIP-Kuliah UNUSA">
    <div class="space-y-8">
        {{-- Welcome Hero Card --}}
        <div class="p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-primary to-primary-container text-white shadow-xl shadow-primary/15 relative overflow-hidden">
            <div class="relative z-10 max-w-2xl space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-bold">
                    <span class="material-symbols-outlined text-sm">verified_user</span>
                    Status KIP: {{ $user->kip_status }} ({{ $user->verification_status }})
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Halo, {{ $user->name }}!</h2>
                <p class="text-sm text-white/90 leading-relaxed">
                    {{ $user->prodi }} &bull; Angkatan {{ $user->angkatan }} &bull; {{ $user->university }}
                </p>
                <div class="pt-2 flex flex-wrap gap-3">
                    <a href="{{ route('dashboard.pencairan') }}" class="px-4 py-2.5 rounded-xl bg-white text-primary font-bold text-xs shadow hover:bg-slate-50 transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">payments</span>
                        Ajukan Pencairan KIP-K
                    </a>
                    <a href="{{ route('dashboard.pelaporan') }}" class="px-4 py-2.5 rounded-xl bg-white/20 hover:bg-white/30 text-white font-bold text-xs backdrop-blur-md transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">description</span>
                        Pelaporan Keaktifan
                    </a>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 opacity-15 hidden sm:block pointer-events-none">
                <span class="material-symbols-outlined text-[240px]">school</span>
            </div>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <div class="p-5 rounded-2xl bg-surface border border-surface-variant/40 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">star</span>
                </div>
                <div>
                    <span class="text-xs font-semibold text-outline block">Total Poin XP</span>
                    <span class="text-xl font-black text-on-surface mt-0.5 block">{{ number_format($totalXp) }} XP</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-surface border border-surface-variant/40 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">event</span>
                </div>
                <div>
                    <span class="text-xs font-semibold text-outline block">Kegiatan Diikuti</span>
                    <span class="text-xl font-black text-on-surface mt-0.5 block">{{ $registrationsCount }} Agenda</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-surface border border-surface-variant/40 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                </div>
                <div>
                    <span class="text-xs font-semibold text-outline block">Sertifikat Resmi</span>
                    <span class="text-xl font-black text-on-surface mt-0.5 block">{{ $certificatesCount }} Dokumen</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-surface border border-surface-variant/40 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">folder_shared</span>
                </div>
                <div>
                    <span class="text-xs font-semibold text-outline block">Berkas Terunggah</span>
                    <span class="text-xl font-black text-on-surface mt-0.5 block">{{ $documentsCount }} File</span>
                </div>
            </div>
        </div>

        {{-- Two Columns: Registered Activities & Upcoming Activities --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- My Registered Activities --}}
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-surface-variant/30">
                    <h3 class="text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xl">confirmation_number</span>
                        Kegiatan Terdaftar Saya
                    </h3>
                    <a href="{{ route('dashboard.kegiatan.saya') }}" class="text-xs font-bold text-primary hover:underline">Lihat Semua</a>
                </div>

                <div class="space-y-3">
                    @forelse($myRecentActivities as $reg)
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between gap-3">
                            <div class="overflow-hidden">
                                <h4 class="text-sm font-bold text-on-surface truncate">{{ $reg->activity->title ?? 'Kegiatan' }}</h4>
                                <p class="text-xs text-outline mt-0.5">
                                    {{ $reg->activity->date_text ?? '-' }} &bull; Kursi: <span class="font-mono font-bold">{{ $reg->selected_seat ?? 'Reguler' }}</span>
                                </p>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                {{ $reg->status }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-8 text-outline text-xs">
                            Anda belum mendaftar kegiatan apapun.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Upcoming Activities from PostgreSQL --}}
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-surface-variant/30">
                    <h3 class="text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600 text-xl">upcoming</span>
                        Agenda Mendatang
                    </h3>
                    <a href="{{ route('dashboard.kegiatan') }}" class="text-xs font-bold text-primary hover:underline">Eksplor Agenda</a>
                </div>

                <div class="space-y-3">
                    @forelse($upcomingActivities as $act)
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between gap-3">
                            <div class="overflow-hidden">
                                <h4 class="text-sm font-bold text-on-surface truncate">{{ $act->title }}</h4>
                                <p class="text-xs text-outline mt-0.5">
                                    {{ $act->date_text }} &bull; <span class="text-primary font-bold">{{ $act->price }}</span>
                                </p>
                            </div>
                            <a href="{{ route('dashboard.kegiatan.show', $act->id) }}" class="shrink-0 px-3 py-1.5 rounded-xl bg-primary hover:bg-primary-container text-white text-xs font-bold transition-all">
                                Daftar
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-8 text-outline text-xs">
                            Belum ada agenda mendatang.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
