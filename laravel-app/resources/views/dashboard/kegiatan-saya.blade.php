<x-layouts.dashboard title="Kegiatan Saya" pageTitle="E-Tiket & Kegiatan Terdaftar" pageSubtitle="Daftar agenda kegiatan yang telah Anda registrasi">
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @forelse($registrations as $reg)
                <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-xs mb-3">
                            <span class="px-2.5 py-1 rounded-md bg-primary/10 text-primary font-bold">
                                Kursi: {{ $reg->selected_seat ?? 'Reguler' }}
                            </span>
                            <span class="px-2.5 py-1 rounded-full font-bold bg-emerald-100 text-emerald-800">
                                {{ $reg->status }}
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-on-surface">{{ $reg->activity->title ?? 'Kegiatan' }}</h3>
                        <p class="text-xs text-outline mt-1.5 leading-relaxed">{{ $reg->activity->description ?? '' }}</p>

                        <div class="mt-4 p-3.5 rounded-2xl bg-surface-container space-y-2 text-xs text-outline">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-primary">calendar_today</span>
                                <span>{{ $reg->activity->date_text ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-primary">location_on</span>
                                <span class="truncate">{{ $reg->activity->location ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-amber-600">confirmation_number</span>
                                <span>ID Pendaftaran: #REG-{{ str_pad($reg->id, 5, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-surface-variant/30 flex items-center justify-between">
                        <a href="{{ route('dashboard.kegiatan.show', $reg->activity_id) }}" class="text-xs font-bold text-primary hover:underline">
                            Lihat Detail Acara
                        </a>
                        <form action="{{ route('dashboard.kegiatan.cancel', $reg->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pendaftaran ini? Kursi Anda akan dilepas.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 hover:underline">
                                Batalkan Pendaftaran
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center py-16 bg-surface rounded-3xl border border-surface-variant/40 text-outline text-sm space-y-3">
                    <span class="material-symbols-outlined text-4xl text-outline/60">event_busy</span>
                    <p>Anda belum terdaftar pada kegiatan apapun.</p>
                    <a href="{{ route('dashboard.kegiatan') }}" class="inline-block px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold">
                        Jelajahi Kegiatan
                    </a>
                </div>
            @endforelse
        </div>

        <div>
            {{ $registrations->links() }}
        </div>
    </div>
</x-layouts.dashboard>
