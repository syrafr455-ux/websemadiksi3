@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
    {{-- Breadcrumb Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-outline">
        <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Beranda</a>
        <span class="material-symbols-outlined text-xs">chevron_right</span>
        <a href="{{ route('berita.acara.index') }}" class="hover:text-primary transition-colors">Berita Acara</a>
        <span class="material-symbols-outlined text-xs">chevron_right</span>
        <span class="text-on-surface font-medium truncate max-w-xs">{{ $item->title }}</span>
    </nav>

    {{-- Main Article Card --}}
    <article class="bg-surface border border-surface-container rounded-3xl overflow-hidden shadow-sm">
        {{-- Banner Image --}}
        <div class="relative w-full aspect-video md:aspect-[21/9] bg-surface-container overflow-hidden">
            <img 
                src="{{ $item->banner_image_url ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop&q=80' }}" 
                alt="{{ $item->title }}"
                class="w-full h-full object-cover"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent flex items-end p-6 md:p-10">
                <div class="space-y-3 text-white max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-primary text-white shadow">
                            {{ $item->category }}
                        </span>
                        @if($item->status)
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $item->status === 'Selesai' ? 'bg-emerald-600' : 'bg-amber-600' }} text-white shadow">
                                {{ $item->status }}
                            </span>
                        @endif
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight leading-tight">
                        {{ $item->title }}
                    </h1>
                </div>
            </div>
        </div>

        {{-- Meta Info Bar --}}
        <div class="bg-surface-container-low px-6 md:px-10 py-4 border-b border-surface-container flex flex-wrap items-center justify-between gap-4 text-sm text-outline">
            <div class="flex flex-wrap items-center gap-6">
                <span class="flex items-center gap-2 text-on-surface font-medium">
                    <span class="material-symbols-outlined text-primary text-base">calendar_month</span>
                    {{ $item->date_text }}
                </span>
                <span class="flex items-center gap-2 text-on-surface font-medium">
                    <span class="material-symbols-outlined text-primary text-base">location_on</span>
                    {{ $item->location }}
                </span>
                @if($item->organizer)
                    <span class="flex items-center gap-2 text-on-surface font-medium">
                        <span class="material-symbols-outlined text-primary text-base">domain</span>
                        {{ $item->organizer }}
                    </span>
                @endif
                @if($item->attendee_count)
                    <span class="flex items-center gap-2 text-on-surface font-medium">
                        <span class="material-symbols-outlined text-primary text-base">groups</span>
                        {{ $item->attendee_count }} Peserta Hadir
                    </span>
                @endif
            </div>

            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-surface-container text-xs font-semibold hover:bg-surface transition-colors">
                <span class="material-symbols-outlined text-sm">print</span>
                Cetak Dokumen
            </button>
        </div>

        {{-- Content Body --}}
        <div class="p-6 md:p-10 space-y-8">
            {{-- Summary Highlight --}}
            <div class="p-6 rounded-2xl bg-primary/5 border border-primary/20 space-y-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">summarize</span>
                    Ringkasan Notulensi Resmi
                </h4>
                <p class="text-on-surface text-base leading-relaxed font-medium">
                    {{ $item->summary }}
                </p>
            </div>

            {{-- Detail / Notes / Outcomes --}}
            <div class="space-y-4 text-on-surface-variant leading-relaxed text-base">
                <h3 class="text-xl font-bold text-on-surface">Uraian &amp; Notulensi Kegiatan</h3>
                @if($item->notes)
                    <div class="whitespace-pre-line bg-surface-container-low/60 p-6 rounded-2xl border border-surface-container">
                        {{ $item->notes }}
                    </div>
                @else
                    <p class="italic text-outline">
                        Kegiatan ini telah selesai dilaksanakan secara tertib sesuai dengan ketentuan organisasi SEMADIKSI dan pedoman kemahasiswaan Universitas Nahdlatul Ulama Surabaya.
                    </p>
                @endif
            </div>

            {{-- Documentation Photos --}}
            @if($item->documentation_photos && is_array($item->documentation_photos) && count($item->documentation_photos) > 0)
                <div class="space-y-4 pt-6 border-t border-surface-container">
                    <h3 class="text-xl font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">photo_library</span>
                        Galeri Dokumentasi Kegiatan
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($item->documentation_photos as $photo)
                            <div class="aspect-video rounded-2xl overflow-hidden border border-surface-container shadow-sm group">
                                <img src="{{ $photo }}" alt="Dokumentasi" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </article>

    {{-- Related Berita Acara --}}
    @if($related->count() > 0)
        <div class="space-y-6 pt-6">
            <h3 class="text-2xl font-bold text-on-surface">Berita Acara Lainnya</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($related as $rel)
                    <a href="{{ route('berita.acara.show', $rel->id) }}" class="group bg-surface border border-surface-container rounded-2xl p-5 hover:shadow-md hover:border-primary/40 transition-all flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary">
                                {{ $rel->category }}
                            </span>
                            <h4 class="font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-2 text-sm">
                                {{ $rel->title }}
                            </h4>
                            <p class="text-xs text-outline line-clamp-2">
                                {{ $rel->summary }}
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-primary flex items-center gap-1">
                            Baca Selengkapnya
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
