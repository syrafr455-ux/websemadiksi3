<x-layouts.dashboard title="Sertifikat Saya" pageTitle="Sertifikat & Riwayat Kegiatan" pageSubtitle="Pantau partisipasi kegiatan Anda, lihat detail program yang diikuti, dan unduh sertifikat resmi sebagai bukti portofolio Anda.">
    <div class="space-y-8" x-data="{ selectedCert: null }">
        {{-- Header Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="bg-surface rounded-2xl border border-surface-variant/40 p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">verified</span>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Sertifikat Terbit</p>
                    <h3 class="text-2xl font-black text-on-surface mt-0.5">{{ count($certificates) }} Sertifikat</h3>
                </div>
            </div>

            <div class="bg-surface rounded-2xl border border-surface-variant/40 p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">E-Portofolio</p>
                    <h3 class="text-2xl font-black text-on-surface mt-0.5">{{ count($certificates) > 0 ? 'Aktif & Sah' : 'Belum Ada' }}</h3>
                </div>
            </div>

            <div class="bg-surface rounded-2xl border border-surface-variant/40 p-5 shadow-sm flex items-center gap-4 sm:col-span-2 lg:col-span-1">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-700 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">qr_code_scanner</span>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Keaslian Dokumen</p>
                    <h3 class="text-sm font-bold text-on-surface mt-0.5">Tervalidasi QR Code SHA-256</h3>
                </div>
            </div>
        </div>

        {{-- Certificate Cards Grid --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-on-surface">Daftar Sertifikat Terbit</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($certificates as $cert)
                    <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-800 flex items-center justify-center font-bold border border-emerald-100">
                                    <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    {{ $cert->status }}
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-on-surface line-clamp-2">{{ $cert->title }}</h3>
                            <p class="text-xs text-on-surface-variant">{{ $cert->organizer }}</p>

                            <div class="p-3 bg-surface-container rounded-xl text-xs font-mono text-outline space-y-1">
                                <p><span class="font-bold text-on-surface">No Seri:</span> <span class="text-emerald-800 font-bold">{{ $cert->code }}</span></p>
                                <p><span class="font-bold text-on-surface">Tanggal:</span> {{ $cert->date }}</p>
                            </div>
                        </div>

                        {{-- Card Actions --}}
                        <div class="pt-4 border-t border-surface-variant/30 flex items-center gap-2">
                            <button 
                                type="button"
                                @click="selectedCert = {{ json_encode($cert) }}"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 rounded-xl font-bold text-xs cursor-pointer transition-all active:scale-95"
                            >
                                <span class="material-symbols-outlined text-base">visibility</span>
                                <span>Lihat Sertifikat</span>
                            </button>

                            @if($cert->file_url)
                                <a 
                                    href="{{ $cert->file_url }}" 
                                    class="flex items-center justify-center gap-1 py-2.5 px-3 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl font-bold text-xs cursor-pointer transition-all active:scale-95 shadow-sm"
                                    title="Unduh PDF Resmi"
                                >
                                    <span class="material-symbols-outlined text-base">download</span>
                                    <span>Unduh</span>
                                </a>
                            @else
                                <a 
                                    href="{{ $cert->verification_url }}" 
                                    target="_blank"
                                    class="flex items-center justify-center gap-1 py-2.5 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold text-xs cursor-pointer transition-all active:scale-95 shadow-sm"
                                    title="Cek Verifikasi"
                                >
                                    <span class="material-symbols-outlined text-base">verified</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-16 bg-surface rounded-3xl border border-surface-variant/40 text-outline text-sm space-y-3">
                        <span class="material-symbols-outlined text-5xl text-outline/40">workspace_premium</span>
                        <p class="font-bold text-on-surface text-base">Belum ada sertifikat yang diterbitkan atas nama Anda.</p>
                        <p class="text-xs max-w-md mx-auto text-on-surface-variant">
                            Sertifikat e-kehadiran akan otomatis terbit setelah Anda menyelesaikan presensi mandiri atau setelah diterbitkan langsung oleh admin kegiatan.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- MODAL: Interactive High-Fidelity Certificate Viewer (Persis Desain Asli) --}}
        <template x-if="selectedCert !== null">
            <div 
                class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
                @keydown.escape.window="selectedCert = null"
            >
                <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[92vh] overflow-y-auto shadow-2xl relative flex flex-col p-5 sm:p-7">
                    {{-- Modal Header --}}
                    <div class="flex justify-between items-center pb-4 border-b border-slate-200 mb-5">
                        <div>
                            <h3 class="font-bold text-lg text-slate-900">Pratinjau Sertifikat Resmi</h3>
                            <p class="text-xs text-slate-500 mt-0.5">No Seri: <span class="font-mono font-bold text-emerald-800" x-text="selectedCert.code"></span></p>
                        </div>
                        <button 
                            type="button" 
                            @click="selectedCert = null" 
                            class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors text-slate-600"
                        >
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>

                    {{-- Certificate Paper Rendering --}}
                    <div class="flex-grow flex items-center justify-center p-2 sm:p-6 bg-slate-100 rounded-2xl overflow-x-auto shadow-inner">
                        <div 
                            class="w-[780px] h-[540px] bg-[#FDFBF7] border-[10px] border-double border-[#C5A059] p-8 flex flex-col justify-between relative shrink-0 shadow-xl text-stone-800 select-none"
                            style="font-family: 'Plus Jakarta Sans', sans-serif;"
                        >
                            {{-- Decorative patterns --}}
                            <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[radial-gradient(#C5A059_2px,transparent_2px)] [background-size:16px_16px]"></div>
                            <div class="absolute top-6 left-6 right-6 bottom-6 border border-[#C5A059]/25 pointer-events-none"></div>

                            {{-- Header --}}
                            <div class="text-center space-y-1.5 z-10">
                                <div class="flex justify-center items-center gap-2 mb-1">
                                    <span class="w-8 h-8 rounded-full bg-emerald-700 flex items-center justify-center text-white text-base font-bold">
                                        S
                                    </span>
                                    <span class="font-display font-extrabold text-emerald-800 tracking-widest text-lg">SEMADIKSI</span>
                                </div>
                                <h4 class="font-serif tracking-[0.2em] text-[#C5A059] text-xs uppercase font-bold">Sertifikat Penghargaan</h4>
                                <p class="text-[10px] text-stone-500 tracking-wider">NOMOR SERI ELEKTRONIK: <span class="font-mono font-bold" x-text="selectedCert.code"></span></p>
                            </div>

                            {{-- Body --}}
                            <div class="text-center space-y-3 z-10">
                                <p class="font-serif italic text-stone-500 text-xs">Dengan bangga mempersembahkan penghargaan kepada:</p>
                                <div>
                                    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-[#1B6D24] tracking-wide border-b-2 border-stone-300 w-fit mx-auto pb-1 px-8 uppercase" x-text="selectedCert.recipient_name"></h1>
                                    <p class="text-[11px] text-stone-500 mt-1.5 font-semibold uppercase tracking-widest">
                                        Penerima Manfaat • Universitas Nahdlatul Ulama Surabaya
                                    </p>
                                </div>
                                <p class="max-w-xl mx-auto text-xs leading-relaxed text-stone-600">
                                    Atas partisipasi aktif dan kontribusinya sebagai <span class="font-bold text-stone-800">Peserta</span> dalam kegiatan:
                                    <br>
                                    <span class="font-bold text-stone-900 text-sm mt-1 inline-block" x-text="'&ldquo;' + selectedCert.title + '&rdquo;'"></span>
                                    <br>
                                    Yang diselenggarakan oleh <span class="font-semibold text-stone-700" x-text="selectedCert.organizer"></span> pada tanggal <span class="font-semibold text-stone-700" x-text="selectedCert.date"></span>.
                                </p>
                            </div>

                            {{-- Footer (Signatures & Interactive QR) --}}
                            <div class="flex justify-between items-end px-6 z-10">
                                {{-- Left Signatory --}}
                                <div class="text-center space-y-0.5 w-40">
                                    <div class="h-10 flex items-center justify-center border-b border-stone-300 relative">
                                        <span class="font-serif italic text-[#1B6D24] text-base select-none opacity-85 rotate-[-3deg]">
                                            m.fatih
                                        </span>
                                    </div>
                                    <p class="text-[10px] font-bold text-stone-800">Muhammad Fatih</p>
                                    <p class="text-[8px] text-stone-500">Ketua Umum SEMADIKSI</p>
                                </div>

                                {{-- Middle Seal --}}
                                <div class="flex flex-col items-center justify-center relative -bottom-1">
                                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-[#E6C98A] via-[#C5A059] to-[#9E7E38] shadow-md flex items-center justify-center border-4 border-[#FDFBF7] relative z-20">
                                        <span class="material-symbols-outlined text-white text-2xl font-bold">workspace_premium</span>
                                    </div>
                                    <span class="text-[8px] font-bold text-[#C5A059] mt-1 uppercase tracking-widest">VERIFIED KIP-K</span>
                                </div>

                                {{-- Right Sign / Interactive QR Validation --}}
                                <div class="text-center space-y-1 w-40 flex flex-col items-center">
                                    <a 
                                        :href="selectedCert.verification_url" 
                                        target="_blank" 
                                        class="w-12 h-12 bg-white p-1 border border-stone-300 shadow-sm flex items-center justify-center cursor-pointer hover:border-emerald-600 transition-all group relative rounded"
                                        title="Klik untuk membuka portal verifikasi publik"
                                    >
                                        {{-- Simulated QR Code Box --}}
                                        <div class="w-full h-full bg-[radial-gradient(#1A1C1C_3px,transparent_3px)] [background-size:6px_6px] relative">
                                            <div class="absolute top-0 left-0 w-3 h-3 border-2 border-stone-800 bg-white"></div>
                                            <div class="absolute top-0 right-0 w-3 h-3 border-2 border-stone-800 bg-white"></div>
                                            <div class="absolute bottom-0 left-0 w-3 h-3 border-2 border-stone-800 bg-white"></div>
                                        </div>
                                        <div class="absolute inset-0 bg-emerald-700/80 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity rounded">
                                            <span class="material-symbols-outlined text-white text-sm">open_in_new</span>
                                        </div>
                                    </a>
                                    <p class="text-[8px] font-bold text-stone-500 uppercase tracking-wider">PINDAI UNTUK VALIDASI</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Actions --}}
                    <div class="flex justify-end gap-3 mt-5 pt-4 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="selectedCert = null" 
                            class="px-5 py-2.5 border border-slate-300 text-slate-700 hover:bg-slate-100 rounded-xl font-bold text-xs cursor-pointer transition-all"
                        >
                            Tutup
                        </button>
                        <template x-if="selectedCert.file_url">
                            <a 
                                :href="selectedCert.file_url" 
                                class="px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl font-bold text-xs cursor-pointer shadow-md active:scale-95 transition-all flex items-center gap-1.5"
                            >
                                <span class="material-symbols-outlined text-base">download</span>
                                <span>Unduh PDF Resmi</span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.dashboard>
