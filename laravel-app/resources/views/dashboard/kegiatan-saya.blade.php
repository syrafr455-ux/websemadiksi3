<x-layouts.dashboard title="Kegiatan Saya" pageTitle="E-Tiket & Kegiatan Terdaftar" pageSubtitle="Daftar agenda kegiatan yang telah Anda registrasi">
    <div class="space-y-6">
        @if(!empty($enterpriseRegistrations) && count($enterpriseRegistrations) > 0)
            <div class="space-y-4">
                <h3 class="text-base font-bold text-on-surface">Kegiatan Resmi Terdaftar</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($enterpriseRegistrations as $reg)
                        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between text-xs mb-3">
                                    <span class="px-2.5 py-1 rounded-md bg-primary/10 text-primary font-bold">
                                        Kursi: {{ $reg->seat?->seat_code ?? ($reg->needs_seat_reselection ? 'Perlu pilih ulang' : 'Reguler') }}
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full font-bold bg-emerald-100 text-emerald-800">
                                        {{ $reg->status_pembayaran?->label() ?? 'Terdaftar' }}
                                    </span>
                                </div>

                                <h3 class="text-base font-bold text-on-surface">{{ $reg->kegiatan?->nama_kegiatan ?? 'Kegiatan' }}</h3>
                                <p class="text-xs text-outline mt-1.5 leading-relaxed">{{ $reg->kegiatan?->deskripsi ?? '' }}</p>

                                <div class="mt-4 p-3.5 rounded-2xl bg-surface-container space-y-2 text-xs text-outline">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base text-primary">calendar_today</span>
                                        <span>{{ $reg->kegiatan?->tanggal_mulai ? \App\Support\Wib::dari($reg->kegiatan->tanggal_mulai)->translatedFormat('d F Y, H:i').' WIB' : '-' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base text-primary">location_on</span>
                                        <span class="truncate">{{ $reg->kegiatan?->lokasi_nama ?? 'Kampus UNUSA' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base text-amber-600">confirmation_number</span>
                                        <span>Tiket: {{ $reg->nomor_tiket }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-surface-variant/30 space-y-2">
                                @if($reg->sertifikat_status === 'terbit')
                                    <a href="{{ $reg->sertifikat_url ?: $reg->sertifikat_verification_url }}" target="_blank" class="w-full py-2 px-3 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 text-amber-800 font-bold text-xs flex items-center justify-center gap-1.5 transition-all">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                        Unduh Sertifikat ({{ $reg->sertifikat_nomor }})
                                    </a>
                                @elseif($reg->sertifikat_status === 'menunggu_verifikasi')
                                    <div class="w-full py-2 px-3 rounded-xl bg-slate-100 border border-slate-300 text-slate-700 text-xs font-semibold flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm text-amber-600">hourglass_top</span>
                                        <span>Menunggu verifikasi panitia</span>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between text-xs">
                                    <a href="{{ route('kegiatan.detail', $reg->kegiatan_id) }}" class="font-bold text-primary hover:underline">
                                        Lihat Detail Acara
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($registrations) && count($registrations) > 0)
            <div class="space-y-4">
                <h3 class="text-base font-bold text-on-surface">Riwayat Pendaftaran Lama</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($registrations as $reg)
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
                    @endforeach
                </div>

                @if(method_exists($registrations, 'links'))
                    <div>
                        {{ $registrations->links() }}
                    </div>
                @endif
            </div>
        @elseif(empty($enterpriseRegistrations) || count($enterpriseRegistrations) === 0)
            <div class="text-center py-16 bg-surface rounded-3xl border border-surface-variant/40 text-outline text-sm space-y-3">
                <span class="material-symbols-outlined text-4xl text-outline/60">event_busy</span>
                <p>Anda belum terdaftar pada kegiatan apapun.</p>
                <a href="{{ route('dashboard.kegiatan') }}" class="inline-block px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold">
                    Jelajahi Kegiatan
                </a>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
