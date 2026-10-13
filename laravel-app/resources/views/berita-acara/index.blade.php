@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    {{-- Header Banner --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-800 via-primary to-emerald-950 p-8 sm:p-12 text-white shadow-xl">
        <div class="relative z-10 max-w-3xl space-y-4">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/15 backdrop-blur-md text-emerald-100 border border-white/20">
                <span class="material-symbols-outlined text-sm">history_edu</span>
                Dokumentasi &amp; Transparansi
            </span>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight">
                Arsip Berita Acara Kegiatan
            </h1>
            <p class="text-emerald-100 text-sm sm:text-base leading-relaxed max-w-2xl">
                Catatan resmi notulensi, dokumentasi kegiatan, dan transparansi laporan program kerja SEMADIKSI Universitas Nahdlatul Ulama Surabaya.
            </p>
        </div>
        <div class="absolute -right-10 -bottom-10 w-80 h-80 rounded-full bg-white/5 blur-3xl pointer-events-none"></div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-surface border border-surface-container rounded-2xl p-4 sm:p-6 shadow-sm">
        <form action="{{ route('berita.acara.index') }}" method="GET" class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">search</span>
                <input 
                    type="text" 
                    name="q" 
                    value="{{ request('q') }}" 
                    placeholder="Cari judul berita acara, lokasi, atau isi notulensi..."
                    class="w-full pl-12 pr-4 py-3 rounded-xl bg-surface-container-low border border-surface-container text-on-surface text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                >
            </div>

            <div class="flex flex-wrap sm:flex-nowrap gap-3 items-center">
                <select 
                    name="category" 
                    onchange="this.form.submit()"
                    class="px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container text-on-surface text-sm focus:outline-none focus:border-primary transition-all"
                >
                    <option value="">Semua Kategori</option>
                    @foreach(['Rapat Kerja', 'Sosialisasi KIP-K', 'Pelatihan', 'Workshop', 'Lomba', 'Seminar', 'Pengabdian Masyarakat'] as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>

                <button type="submit" class="px-6 py-3 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary/90 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    Filter
                </button>

                @if(request()->hasAny(['q', 'category']))
                    <a href="{{ route('berita.acara.index') }}" class="px-4 py-3 rounded-xl border border-surface-container text-outline hover:text-on-surface text-sm font-semibold transition-all flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Berita Acara Grid --}}
    @if($beritaAcaraList->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($beritaAcaraList as $item)
                <article class="group bg-surface border border-surface-container rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:border-primary/40 transition-all duration-300 flex flex-col">
                    <div class="relative aspect-video overflow-hidden bg-surface-container">
                        <img 
                            src="{{ $item->banner_image_url ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format&fit=crop&q=80' }}" 
                            alt="{{ $item->title }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        >
                        <div class="absolute top-3 left-3 flex gap-2">
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-primary text-white shadow-md">
                                {{ $item->category }}
                            </span>
                            @if($item->status)
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold {{ $item->status === 'Selesai' ? 'bg-emerald-600' : 'bg-amber-600' }} text-white shadow-md">
                                    {{ $item->status }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-4 text-xs text-outline">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">calendar_today</span>
                                    {{ $item->date_text }}
                                </span>
                                @if($item->attendee_count)
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">group</span>
                                        {{ $item->attendee_count }} Peserta
                                    </span>
                                @endif
                            </div>

                            <h3 class="font-bold text-lg text-on-surface line-clamp-2 group-hover:text-primary transition-colors">
                                <a href="{{ route('berita.acara.show', $item->id) }}">
                                    {{ $item->title }}
                                </a>
                            </h3>

                            <p class="text-sm text-on-surface-variant line-clamp-3 leading-relaxed">
                                {{ $item->summary }}
                            </p>
                        </div>

                        <div class="pt-4 border-t border-surface-container flex items-center justify-between">
                            <span class="text-xs text-outline flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">location_on</span>
                                {{ Str::limit($item->location, 20) }}
                            </span>
                            <a href="{{ route('berita.acara.show', $item->id) }}" class="inline-flex items-center gap-1 text-sm font-bold text-primary group-hover:translate-x-1 transition-transform">
                                Baca Notulensi
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="pt-6">
            {{ $beritaAcaraList->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-surface border border-surface-container rounded-3xl space-y-4">
            <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center mx-auto text-outline">
                <span class="material-symbols-outlined text-3xl">article</span>
            </div>
            <h3 class="text-lg font-bold text-on-surface">Tidak Ada Berita Acara Ditemukan</h3>
            <p class="text-sm text-outline max-w-md mx-auto">
                Silakan coba ubah kata kunci pencarian atau filter kategori kegiatan yang Anda gunakan.
            </p>
            <a href="{{ route('berita.acara.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold shadow-md">
                Tampilkan Semua Berita Acara
            </a>
        </div>
    @endif
</div>
@endsection
