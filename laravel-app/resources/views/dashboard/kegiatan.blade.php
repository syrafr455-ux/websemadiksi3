<x-layouts.dashboard title="Eksplor Kegiatan" pageTitle="Daftar Kegiatan & Seminar" pageSubtitle="Ikuti pelatihan dan raih poin XP keaktifan mahasiswa">
    <div class="space-y-6">
        {{-- Search & Category Filter --}}
        <div class="bg-surface rounded-2xl border border-surface-variant/40 p-4 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
            <form action="{{ route('dashboard.kegiatan') }}" method="GET" class="w-full sm:w-80 relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
                <input 
                    type="text" 
                    name="q" 
                    value="{{ request('q') }}" 
                    placeholder="Cari kegiatan..." 
                    class="w-full pl-10 pr-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary"
                >
            </form>

            <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto">
                <a href="{{ route('dashboard.kegiatan') }}" class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap {{ !request('category') ? 'bg-primary text-white' : 'bg-surface-container text-outline hover:text-on-surface' }}">
                    Semua
                </a>
                <a href="{{ route('dashboard.kegiatan', ['category' => 'Seminar']) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap {{ request('category') == 'Seminar' ? 'bg-primary text-white' : 'bg-surface-container text-outline hover:text-on-surface' }}">
                    Seminar
                </a>
                <a href="{{ route('dashboard.kegiatan', ['category' => 'Webinar']) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap {{ request('category') == 'Webinar' ? 'bg-primary text-white' : 'bg-surface-container text-outline hover:text-on-surface' }}">
                    Webinar
                </a>
                <a href="{{ route('dashboard.kegiatan', ['category' => 'Sosial']) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap {{ request('category') == 'Sosial' ? 'bg-primary text-white' : 'bg-surface-container text-outline hover:text-on-surface' }}">
                    Sosial
                </a>
            </div>
        </div>

        {{-- Activities Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($activities as $act)
                <div class="bg-surface rounded-3xl border border-surface-variant/40 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="h-44 overflow-hidden relative">
                            <img src="{{ $act->image_url }}" alt="{{ $act->title }}" class="w-full h-full object-cover">
                            <div class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-bold bg-surface/90 backdrop-blur-md text-primary shadow">
                                +{{ $act->xp_points }} XP
                            </div>
                            <div class="absolute bottom-3 left-3 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-black/60 text-white backdrop-blur-sm">
                                {{ $act->date_text }}
                            </div>
                        </div>

                        <div class="p-5 space-y-3">
                            <span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-surface-container text-primary">
                                {{ $act->category }} &bull; {{ $act->type }}
                            </span>
                            <h3 class="text-base font-bold text-on-surface line-clamp-2">{{ $act->title }}</h3>
                            <p class="text-xs text-outline line-clamp-3 leading-relaxed">{{ $act->description }}</p>

                            <div class="space-y-1.5 pt-2 text-xs text-outline">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base text-primary">location_on</span>
                                    <span class="truncate">{{ $act->location }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base text-amber-600">group</span>
                                    <span>Kuota: {{ $act->quota }} Peserta</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0 border-t border-surface-variant/30 mt-4 flex items-center justify-between">
                        <span class="text-xs font-black text-primary">{{ $act->price }}</span>

                        @if(in_array($act->id, $myRegisteredIds))
                            <a href="{{ route('dashboard.kegiatan.saya') }}" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-xl transition-all" title="Buka E-Tiket Saya">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Terdaftar (Lihat Tiket)
                            </a>
                        @else
                            <a href="{{ route('kegiatan.detail', $act->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-white bg-primary hover:bg-primary-container px-4 py-2 rounded-xl transition-all">
                                Pilih Kursi / Daftar
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 text-outline text-sm">
                    Tidak ada kegiatan ditemukan.
                </div>
            @endforelse
        </div>

        <div>
            {{ $activities->links() }}
        </div>
    </div>
</x-layouts.dashboard>
