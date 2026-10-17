<x-layouts.dashboard title="Panduan & Regulasi KIP" pageTitle="Informasi & Regulasi KIP-Kuliah" pageSubtitle="Pengumuman penting, hak, kewajiban, dan prosedur resmi">
    <div class="space-y-6">
        @forelse($infoList as $info)
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
                <div class="flex items-center justify-between text-xs text-outline">
                    <span class="px-3 py-1 rounded-full font-bold {{ $info->priority === 'Tinggi' ? 'bg-rose-100 text-rose-800' : 'bg-primary/10 text-primary' }}">
                        {{ $info->category }} &bull; Prioritas {{ $info->priority }}
                    </span>
                    <span>Diunggah: {{ $info->date_uploaded ?? \App\Support\Wib::dari($info->created_at)->translatedFormat('d M Y') }}</span>
                </div>

                <h3 class="text-lg font-bold text-on-surface">{{ $info->title }}</h3>
                <p class="text-xs sm:text-sm text-outline leading-relaxed whitespace-pre-line">{{ $info->content }}</p>

                <div class="pt-4 border-t border-surface-variant/30 flex items-center justify-between text-xs text-outline">
                    <span>Penulis: {{ $info->author }}</span>
                    @if($info->attachment_url)
                        <a href="{{ $info->attachment_url }}" target="_blank" class="font-bold text-primary hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">attachment</span>
                            Unduh Lampiran Resmi
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-16 bg-surface rounded-3xl border border-surface-variant/40 text-outline text-sm">
                Belum ada pengumuman panduan KIP-K.
            </div>
        @endforelse
    </div>
</x-layouts.dashboard>
