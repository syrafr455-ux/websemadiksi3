<x-layouts.app :title="$title ?? 'Pusat Verifikasi Dokumen & Keaslian Sertifikat'">
    <div class="py-16 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        <div class="text-center mb-10 space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 text-teal-800 border border-teal-200 text-xs font-bold">
                <span class="material-symbols-outlined text-sm">verified</span>
                Pusat Validasi Dokumen Resmi & TTE SEMADIKSI UNUSA
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Verifikasi Keaslian Sertifikat</h1>
            <p class="text-sm text-slate-500 max-w-xl mx-auto leading-relaxed">
                Pindai QR code, masukkan kode sertifikat, atau unggah file dokumen PDF Anda untuk memverifikasi keabsahan data penerima dan integritas hash SHA-256 secara langsung.
            </p>
        </div>

        {{-- Verification Input Form & Hash File Upload Tab --}}
        <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-xl mb-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Method 1: By Certificate Code --}}
                <div class="border-b md:border-b-0 md:border-r border-slate-200 pb-6 md:pb-0 md:pr-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-teal-700">pin</span>
                        Cari Berdasarkan Kode Sertifikat
                    </span>
                    <form action="{{ route('verifikasi.sertifikat') }}" method="GET" class="space-y-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">qr_code_scanner</span>
                            <input 
                                type="text" 
                                name="code" 
                                value="{{ $code }}" 
                                placeholder="Contoh: CERT-SMD-2026-X8Y7K2" 
                                class="w-full pl-12 pr-4 py-3.5 bg-slate-50 rounded-2xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 text-sm font-mono uppercase tracking-wider"
                            >
                        </div>
                        <button 
                            type="submit" 
                            class="w-full py-3.5 rounded-2xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-sm shadow-md shadow-teal-700/20 flex items-center justify-center gap-2 transition hover:scale-[1.01] active:scale-[0.99]"
                        >
                            <span class="material-symbols-outlined text-lg">search</span>
                            Verifikasi Kode
                        </button>
                    </form>
                </div>

                {{-- Method 2: By PDF File Hash Verification --}}
                <div class="md:pl-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-teal-700">fingerprint</span>
                        Validasi Integritas File PDF (SHA-256)
                    </span>
                    <form action="{{ route('verifikasi.sertifikat') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div class="relative">
                            <input 
                                type="file" 
                                name="document_file" 
                                accept="application/pdf"
                                required
                                class="w-full px-4 py-2.5 bg-slate-50 rounded-2xl border border-slate-300 text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-teal-800 hover:file:bg-teal-100 cursor-pointer"
                            >
                        </div>
                        <button 
                            type="submit" 
                            class="w-full py-3.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm shadow-md flex items-center justify-center gap-2 transition hover:scale-[1.01] active:scale-[0.99]"
                        >
                            <span class="material-symbols-outlined text-lg">security</span>
                            Periksa Integritas Hash
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Hash Verification Alert if File was uploaded --}}
        @if($hashVerificationResult)
            @if($hashVerificationResult === 'match')
                <div class="mb-8 p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-sm flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-600 text-2xl flex-shrink-0">verified</span>
                    <div>
                        <strong class="font-bold block">Integritas Berkas Valid (SHA-256 Cocok)</strong>
                        File PDF yang Anda unggah memiliki hash digital identik dengan basis data resmi. Berkas terbukti otentik dan tidak mengalami modifikasi.
                        <div class="mt-1 font-mono text-xs text-emerald-800 break-all">Hash: {{ $uploadedHash }}</div>
                    </div>
                </div>
            @else
                <div class="mb-8 p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-sm flex items-start gap-3">
                    <span class="material-symbols-outlined text-rose-600 text-2xl flex-shrink-0">gpp_bad</span>
                    <div>
                        <strong class="font-bold block">Integritas Berkas Tidak Cocok!</strong>
                        Hash SHA-256 dari file yang Anda unggah tidak cocok dengan sertifikat manapun di database resmi kami. Berkas mungkin telah diubah atau bukan terbitan resmi.
                        <div class="mt-1 font-mono text-xs text-rose-800 break-all">Hash: {{ $uploadedHash }}</div>
                    </div>
                </div>
            @endif
        @endif

        {{-- Results Area for New Dynamic Sertifikat Model --}}
        @if($sertifikat)
            <div class="bg-gradient-to-br from-emerald-50/90 to-teal-50/70 border-2 border-emerald-500/50 rounded-3xl p-8 shadow-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-emerald-200/60 pb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/30 flex-shrink-0">
                            <span class="material-symbols-outlined text-3xl">verified</span>
                        </div>
                        <div>
                            <span class="text-xs font-extrabold uppercase tracking-wider text-emerald-700 block">
                                Status: Dokumen Resmi Terverifikasi
                            </span>
                            <h2 class="text-xl sm:text-2xl font-black text-emerald-950 mt-0.5">Sertifikat Digital Ber-TTE UNUSA</h2>
                        </div>
                    </div>
                    @if($sertifikat->file_url)
                        <a 
                            href="{{ $sertifikat->file_url }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-md transition"
                        >
                            <span class="material-symbols-outlined text-sm">download</span>
                            Unduh File PDF Resmi
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Nama Penerima</span>
                        <span class="text-lg font-bold text-slate-900 mt-1 block">
                            {{ $sertifikat->presensi?->nama_lengkap ?: ($sertifikat->user?->name ?? 'Penerima Terdaftar') }}
                        </span>
                        @if($sertifikat->presensi?->nim || $sertifikat->user?->nim)
                            <span class="text-xs text-slate-600 font-mono">
                                NIM. {{ $sertifikat->presensi?->nim ?: $sertifikat->user?->nim }}
                            </span>
                        @endif
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Nomor Sertifikat Resmi</span>
                        <span class="text-base font-mono font-bold text-slate-900 mt-1 block">{{ $sertifikat->nomor_sertifikat }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Nama Kegiatan</span>
                        <span class="text-base font-semibold text-slate-900 mt-1 block">
                            {{ $sertifikat->kegiatan?->nama_kegiatan ?? 'Kegiatan SEMADIKSI' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Peran / Kategori</span>
                        <span class="text-base font-bold text-teal-800 mt-1 block uppercase">
                            {{ $sertifikat->peran_label }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Tanggal Penerbitan</span>
                        <span class="text-base font-medium text-slate-900 mt-1 block">
                            {{ $sertifikat->issued_at ? \App\Support\Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i') . ' WIB' : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Status Presensi & TTE</span>
                        <span class="text-base font-semibold text-emerald-900 mt-1 block flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-emerald-600 text-lg">draw</span>
                            Tanda Tangan Digital Terverifikasi
                        </span>
                    </div>
                </div>

                {{-- SHA-256 Document Hash Verification Box --}}
                <div class="pt-6 border-t border-emerald-200/60">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 block mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">lock</span>
                        Integritas Kriptografi Dokumen (SHA-256 Hash):
                    </span>
                    <div class="p-3 bg-white/90 rounded-xl border border-emerald-200 font-mono text-xs text-slate-700 break-all select-all">
                        {{ $sertifikat->document_hash }}
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5">
                        Hash di atas dihasilkan otomatis dari struktur bit berkas PDF asli pada saat penerbitan, menjamin keaslian dan proteksi dari pemalsuan.
                    </p>
                </div>
            </div>

        {{-- Results Area for Legacy Certificate Model --}}
        @elseif($certificate)
            <div class="bg-emerald-50/70 border-2 border-emerald-500/40 rounded-3xl p-8 shadow-xl space-y-6">
                <div class="flex items-center gap-4 border-b border-emerald-200/60 pb-6">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/30">
                        <span class="material-symbols-outlined text-3xl">verified</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 block">Status: Dokumen Asli & Terverifikasi</span>
                        <h2 class="text-xl sm:text-2xl font-black text-emerald-950 mt-0.5">Sertifikat Resmi SEMADIKSI UNUSA</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Nama Penerima</span>
                        <span class="text-base font-bold text-slate-900 mt-1 block">{{ $certificate->user_name }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Kode Sertifikat</span>
                        <span class="text-base font-mono font-bold text-slate-900 mt-1 block">{{ $certificate->code }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Nama Kegiatan</span>
                        <span class="text-base font-medium text-slate-900 mt-1 block">{{ $certificate->activity_title }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Tanggal Penerbitan</span>
                        <span class="text-base font-medium text-slate-900 mt-1 block">{{ $certificate->issued_date ? date('d F Y', strtotime($certificate->issued_date)) : '-' }}</span>
                    </div>
                </div>
            </div>

        {{-- Not Found Alert --}}
        @elseif($code && ! $hashVerificationResult)
            <div class="bg-rose-50 border-2 border-rose-300 rounded-3xl p-8 text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-rose-600 text-white flex items-center justify-center mx-auto shadow-lg shadow-rose-600/30">
                    <span class="material-symbols-outlined text-3xl">cancel</span>
                </div>
                <h3 class="text-xl font-bold text-rose-950">Sertifikat Tidak Ditemukan</h3>
                <p class="text-sm text-rose-800 max-w-md mx-auto">
                    Kode sertifikat <span class="font-mono font-bold">{{ $code }}</span> tidak terdaftar dalam basis data resmi kami. Pastikan Anda memasukkan kode dengan benar atau hubungi Biro Kemahasiswaan UNUSA.
                </p>
            </div>
        @endif
    </div>
</x-layouts.app>
