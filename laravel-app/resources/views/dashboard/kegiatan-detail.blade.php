<x-layouts.dashboard title="{{ $activity->title }}" pageTitle="Detail Kegiatan" pageSubtitle="Informasi lengkap & reservasi kursi">
    <div class="max-w-4xl mx-auto space-y-8" x-data="{ selectedSeat: '{{ $registration?->selected_seat ?? '' }}' }">
        {{-- Activity Header Banner --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 overflow-hidden shadow-sm">
            <div class="h-64 sm:h-80 relative overflow-hidden">
                <img src="{{ $activity->image_url }}" alt="{{ $activity->title }}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                <div class="absolute bottom-6 left-6 right-6 text-white space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-primary text-white shadow">
                            {{ $activity->category }}
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur-md">
                            {{ $activity->type }}
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500 text-white">
                            +{{ $activity->xp_points }} XP
                        </span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ $activity->title }}</h2>
                </div>
            </div>

            <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6 border-b border-surface-variant/30 text-xs text-outline">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary">calendar_today</span>
                    <div>
                        <span class="block font-bold text-on-surface">Waktu Pelaksanaan</span>
                        <span>{{ $activity->date_text }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary">location_on</span>
                    <div>
                        <span class="block font-bold text-on-surface">Lokasi Acara</span>
                        <span>{{ $activity->location }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary">payments</span>
                    <div>
                        <span class="block font-bold text-on-surface">Biaya Pendaftaran</span>
                        <span class="text-primary font-bold text-sm">{{ $activity->price }}</span>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-2">Deskripsi Agenda</h3>
                    <p class="text-sm text-outline leading-relaxed">{{ $activity->description }}</p>
                </div>

                @if($activity->speaker)
                    <div>
                        <h3 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-2">Narasumber / Pemateri</h3>
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center gap-3 text-sm">
                            <span class="material-symbols-outlined text-2xl text-primary">record_voice_over</span>
                            <span class="font-bold text-on-surface">{{ $activity->speaker }}</span>
                        </div>
                    </div>
                @endif

                @if($activity->rundown)
                    <div>
                        <h3 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-2">Susunan Acara (Rundown)</h3>
                        <div class="p-4 rounded-2xl bg-surface-container text-xs text-outline font-mono whitespace-pre-line leading-relaxed">
                            {{ $activity->rundown }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Registration / Seat Map Card --}}
        @if($registration)
            <div class="p-6 sm:p-8 rounded-3xl bg-emerald-50 border-2 border-emerald-500/40 text-emerald-950 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-2xl">check_circle</span>
                    </div>
                    <div>
                        <h4 class="text-base font-bold">Anda Sudah Terdaftar pada Kegiatan Ini</h4>
                        <p class="text-xs text-emerald-800">
                            Kursi Anda: <span class="font-mono font-bold">{{ $registration->selected_seat ?? 'Reguler / Bebas' }}</span> &bull; Status: {{ $registration->status }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('dashboard.kegiatan.saya') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition-all">
                    Lihat E-Tiket Saya
                </a>
            </div>
        @else
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-6">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Formulir Pendaftaran & Pemilihan Kursi</h3>
                    <p class="text-xs text-outline mt-0.5">Pilih kursi auditorium pilihan Anda sebelum mendaftar.</p>
                </div>

                <form action="{{ route('dashboard.kegiatan.daftar', $activity->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <input type="hidden" name="selected_seat" :value="selectedSeat">

                    {{-- Seat Map Interactive Visualization --}}
                    @if($activity->enable_seat_booking)
                        <div class="space-y-4">
                            <div class="p-3 bg-surface-container rounded-xl text-center text-xs font-bold uppercase tracking-wider text-outline border border-surface-variant/40">
                                Area Panggung Utama / Layar Proyektor
                            </div>

                            {{-- Seat grid generator (Rows A-D, Cols 1-8) --}}
                            @php
                                $rows = ['A', 'B', 'C', 'D'];
                                $cols = [1, 2, 3, 4, 5, 6, 7, 8];
                            @endphp

                            <div class="overflow-x-auto py-4">
                                <div class="min-w-[420px] flex flex-col items-center gap-3">
                                    @foreach($rows as $r)
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 text-xs font-bold text-outline text-center">{{ $r }}</span>
                                            <div class="flex items-center gap-2">
                                                @foreach($cols as $c)
                                                    @php $seatId = "{$r}-{$c}"; $isTaken = in_array($seatId, $occupiedSeats); @endphp
                                                    <button 
                                                        type="button" 
                                                        @if($isTaken) disabled @else @click="selectedSeat = '{{ $seatId }}'" @endif
                                                        :class="{
                                                            'bg-rose-100 text-rose-400 cursor-not-allowed border-rose-200': {{ $isTaken ? 'true' : 'false' }},
                                                            'bg-primary text-white ring-2 ring-primary': selectedSeat === '{{ $seatId }}',
                                                            'bg-surface-container text-on-surface hover:bg-primary/20 border-surface-variant': !{{ $isTaken ? 'true' : 'false' }} && selectedSeat !== '{{ $seatId }}'
                                                        }"
                                                        class="w-9 h-9 rounded-xl border text-xs font-mono font-bold flex items-center justify-center transition-all"
                                                        title="{{ $isTaken ? 'Sudah Terisi' : 'Pilih Kursi '.$seatId }}"
                                                    >
                                                        {{ $c }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-6 text-xs text-outline pt-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-4 h-4 rounded-md bg-surface-container border border-surface-variant"></span>
                                    <span>Tersedia</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-4 h-4 rounded-md bg-primary"></span>
                                    <span>Pilihan Anda</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-4 h-4 rounded-md bg-rose-100 border border-rose-200"></span>
                                    <span>Terisi</span>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between text-xs font-medium">
                                <span>Kursi yang Dipilih:</span>
                                <span class="text-sm font-bold font-mono text-primary" x-text="selectedSeat ? selectedSeat : 'Belum dipilih (Klik nomor kursi di atas)'"></span>
                            </div>
                        </div>
                    @endif

                    @if($activity->require_file_upload)
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider">
                                Unggah Berkas Persyaratan <span class="text-rose-500">*</span>
                            </label>
                            <p class="text-xs text-outline">{{ $activity->file_upload_instruction ?? 'Unggah bukti pendaftaran/follow media sosial dalam format PDF atau gambar.' }}</p>
                            <input 
                                type="file" 
                                name="upload_file" 
                                required
                                class="block w-full text-xs text-outline file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-primary-container cursor-pointer"
                            >
                        </div>
                    @endif

                    <button 
                        type="submit" 
                        class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 flex items-center justify-center gap-2 transition-all hover:scale-[1.01] active:scale-[0.99]"
                    >
                        <span class="material-symbols-outlined text-xl">how_to_reg</span>
                        Konfirmasi Pendaftaran Kegiatan
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
