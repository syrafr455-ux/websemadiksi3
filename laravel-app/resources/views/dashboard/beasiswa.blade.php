<x-layouts.dashboard title="Informasi Beasiswa" pageTitle="Pusat Informasi Beasiswa" pageSubtitle="Daftar program beasiswa internal UNUSA dan eksternal nasional">
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($scholarships as $sch)
                <div class="bg-surface rounded-3xl border border-surface-variant/40 overflow-hidden shadow-sm hover:shadow-lg transition-all flex flex-col justify-between">
                    <div>
                        <div class="h-40 overflow-hidden relative">
                            <img src="{{ $sch->banner_img ?: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=80' }}" alt="{{ $sch->title }}" class="w-full h-full object-cover">
                            <div class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-bold bg-surface/90 text-primary shadow">
                                {{ $sch->status }}
                            </div>
                        </div>
                        <div class="p-5 space-y-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-primary bg-surface-container px-2.5 py-0.5 rounded-md">
                                {{ $sch->category }}
                            </span>
                            <h3 class="text-base font-bold text-on-surface line-clamp-2">{{ $sch->title }}</h3>
                            <p class="text-xs text-outline line-clamp-3 leading-relaxed">{{ $sch->description }}</p>

                            <div class="p-3 bg-surface-container rounded-xl text-xs text-outline space-y-1">
                                <p><span class="font-bold text-on-surface">Penyelenggara:</span> {{ $sch->provider }}</p>
                                <p><span class="font-bold text-on-surface">Batas Pendaftaran:</span> {{ $sch->close_date }}</p>
                                <p><span class="font-bold text-on-surface">Cakupan:</span> {{ $sch->coverage }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0 border-t border-surface-variant/30 mt-4 flex items-center justify-between">
                        @if($sch->apply_url)
                            <a href="{{ $sch->apply_url }}" target="_blank" class="w-full text-center py-2.5 rounded-xl bg-primary hover:bg-primary-container text-white text-xs font-bold transition-all">
                                Kunjungi Laman Pendaftaran &rarr;
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 text-outline text-sm">
                    Belum ada info beasiswa dibuka saat ini.
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.dashboard>
