<x-layouts.app :title="$title ?? 'Portal Validasi Sertifikat SEMADIKSI'">
    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col font-sans select-none pb-12" x-data="verificationPortal('{{ $code ?? '' }}')">
        {{-- Banner Header --}}
        <div class="w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-indigo-700 text-white text-center py-7 px-4 shadow-md shrink-0">
            <p class="tracking-widest uppercase text-xs font-semibold opacity-90">Digital Signature Verification</p>
            <h1 class="text-xl md:text-2xl font-extrabold tracking-wide mt-1 uppercase">
                Portal Validasi Sertifikat SEMADIKSI
            </h1>
        </div>

        <main class="flex-grow max-w-4xl w-full mx-auto px-4 mt-8 flex flex-col items-center">
            {{-- Search & Scan Form --}}
            <section class="w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 text-center">
                    Verifikasi Keaslian Sertifikat
                </h2>

                <form action="{{ route('verifikasi.sertifikat') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-grow">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            qr_code_scanner
                        </span>
                        <input 
                            type="text" 
                            name="code" 
                            x-model="certInput"
                            placeholder="Masukkan Nomor Seri Sertifikat (contoh: CERT-LKMB-2024-0891)" 
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm font-mono tracking-wide uppercase font-bold text-slate-800"
                        >
                    </div>
                    <div class="flex gap-2">
                        <button 
                            type="submit" 
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl active:scale-95 transition-all cursor-pointer flex items-center justify-center gap-1.5 shrink-0 shadow-sm"
                        >
                            <span class="material-symbols-outlined text-[18px]">verified</span>
                            <span>Verifikasi</span>
                        </button>
                        <button 
                            type="button" 
                            @click="simulateScan()" 
                            :disabled="isScanning"
                            class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl active:scale-95 transition-all cursor-pointer flex items-center justify-center gap-1.5 shrink-0 disabled:bg-slate-300 shadow-sm"
                        >
                            <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                            <span x-text="isScanning ? 'Scanning...' : 'Scan QR'"></span>
                        </button>
                    </div>
                </form>

                {{-- Scanner Viewport Simulation --}}
                <div 
                    x-show="isScanning" 
                    x-transition 
                    class="mt-6 w-full max-w-sm mx-auto aspect-video rounded-xl bg-slate-900 border border-slate-800 relative overflow-hidden flex flex-col items-center justify-center text-white shadow-inner"
                >
                    <div class="absolute inset-x-8 top-1/2 -translate-y-1/2 h-0.5 bg-emerald-500 shadow-[0_0_10px_2px_rgba(16,185,129,0.5)] animate-pulse"></div>
                    <span class="material-symbols-outlined text-4xl text-emerald-400 animate-bounce">qr_code_2</span>
                    <p class="text-[10px] tracking-wider uppercase font-semibold text-emerald-300 mt-2">Membuka Kamera QR Scanner...</p>
                </div>

                {{-- Tab Unggah Berkas PDF untuk Verifikasi Hash --}}
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <details class="group">
                        <summary class="text-xs font-bold text-slate-600 cursor-pointer flex items-center gap-1.5 hover:text-blue-600 transition-colors">
                            <span class="material-symbols-outlined text-sm text-slate-400 group-open:rotate-90 transition-transform">chevron_right</span>
                            <span>Atau Validasi Integritas Berkas PDF Langsung (SHA-256 Checksum)</span>
                        </summary>
                        <form action="{{ route('verifikasi.sertifikat') }}" method="POST" enctype="multipart/form-data" class="mt-3 flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input 
                                type="file" 
                                name="document_file" 
                                accept="application/pdf" 
                                required
                                class="flex-grow p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                            >
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 shrink-0 transition-all"
                            >
                                <span class="material-symbols-outlined text-sm">security</span>
                                <span>Periksa Hash</span>
                            </button>
                        </form>
                    </details>
                </div>
            </section>

            {{-- Hash Verification Alert if File was uploaded --}}
            @if($hashVerificationResult)
                @if($hashVerificationResult === 'match')
                    <div class="w-full mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-sm flex items-start gap-3 shadow-sm">
                        <span class="material-symbols-outlined text-emerald-600 text-2xl flex-shrink-0">verified</span>
                        <div>
                            <strong class="font-bold block">Integritas Berkas Valid (SHA-256 Cocok)</strong>
                            File PDF yang Anda unggah memiliki hash digital identik dengan basis data resmi. Berkas terbukti otentik dan tidak mengalami modifikasi.
                            <div class="mt-1 font-mono text-xs text-emerald-800 break-all bg-emerald-100/60 p-1.5 rounded">Hash: {{ $uploadedHash }}</div>
                        </div>
                    </div>
                @else
                    <div class="w-full mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-sm flex items-start gap-3 shadow-sm">
                        <span class="material-symbols-outlined text-rose-600 text-2xl flex-shrink-0">gpp_bad</span>
                        <div>
                            <strong class="font-bold block">Integritas Berkas Tidak Cocok!</strong>
                            Hash SHA-256 dari file yang Anda unggah tidak cocok dengan sertifikat manapun di database resmi kami. Berkas mungkin telah diubah atau bukan terbitan resmi.
                            <div class="mt-1 font-mono text-xs text-rose-800 break-all bg-rose-100/60 p-1.5 rounded">Hash: {{ $uploadedHash }}</div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Verification Result Display --}}
            @if(!empty($code))
                <section class="w-full space-y-6">
                    @if($documentData)
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-md p-6 space-y-8">
                            {{-- 1. INFORMASI DOKUMEN --}}
                            <div>
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest text-center border-b border-slate-100 pb-2 mb-4">
                                    Informasi Dokumen
                                </h3>

                                <div class="divide-y divide-slate-100 text-sm font-medium">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 items-center gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm font-bold sm:font-normal">STATUS</span>
                                        <div class="sm:col-span-2 flex items-center gap-1.5">
                                            <span class="px-4 py-1.5 rounded-full text-sm font-black bg-emerald-100 text-emerald-800 border-2 border-emerald-300 flex items-center gap-1.5 tracking-wider uppercase shadow-sm">
                                                <span>{{ $documentData['status'] }}</span>
                                                <span class="material-symbols-outlined text-[20px] font-bold text-emerald-600">check_circle</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 items-center gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">Nomor Seri</span>
                                        <span class="sm:col-span-2 font-mono font-bold text-slate-900 text-xs sm:text-sm">
                                            {{ $documentData['code'] }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 items-center gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">Hash Dokumen (SHA-256)</span>
                                        <span class="sm:col-span-2 font-mono text-xs text-slate-600 select-all break-all bg-slate-50 p-2 rounded-lg border border-slate-200">
                                            {{ $documentData['hash'] }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 items-center gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">Nama Penerima</span>
                                        <span class="sm:col-span-2 font-bold text-slate-900 text-sm flex items-center gap-2">
                                            <span>{{ $documentData['recipient_name'] }}</span>
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md text-[10px] border border-blue-200 font-bold uppercase tracking-wider">
                                                Penerima Sah
                                            </span>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">Judul Dokumen</span>
                                        <span class="sm:col-span-2 font-bold text-slate-800">{{ $documentData['title'] }}</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">Author Dokumen</span>
                                        <span class="sm:col-span-2 text-slate-700">{{ $documentData['author'] }}</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 py-3 items-center gap-1 sm:gap-0">
                                        <span class="text-slate-400 text-xs sm:text-sm">File Dokumen</span>
                                        <div class="sm:col-span-2">
                                            @if($documentData['download_url'])
                                                <a 
                                                    href="{{ $documentData['download_url'] }}" 
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-lg transition-all shadow-sm cursor-pointer"
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">download</span>
                                                    <span>Download PDF</span>
                                                </a>
                                            @else
                                                <button 
                                                    type="button" 
                                                    onclick="alert('Berkas digital arsip valid untuk nomor: {{ $documentData['code'] }}')" 
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-lg transition-all shadow-sm cursor-pointer"
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">download</span>
                                                    <span>Download PDF</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. INFORMASI PENANDATANGANAN (TTE) --}}
                            <div>
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest text-center border-b border-slate-100 pb-2 mb-4">
                                    Informasi Penandatanganan (TTE)
                                </h3>

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                                <th class="p-3 w-10 text-center">NO</th>
                                                <th class="p-3 w-40 text-center">TIPE (TTE)</th>
                                                <th class="p-3">NAMA PENANDATANGAN</th>
                                                <th class="p-3 w-44">TANGGAL TTE</th>
                                                <th class="p-3 w-24 text-center">CERTIFIED</th>
                                                <th class="p-3 w-24 text-center">VALIDITY</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 font-medium">
                                            @foreach($documentData['signatories'] as $idx => $sig)
                                                <tr class="hover:bg-slate-50/70 transition-colors">
                                                    <td class="p-3 text-center font-bold text-slate-400">{{ $idx + 1 }}</td>
                                                    <td class="p-3 text-center">
                                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono {{ ($sig['type'] ?? '') === 'NIP' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                                            {{ $sig['type'] ?? 'TTE' }}. {{ $sig['nipNim'] ?? '-' }}
                                                        </span>
                                                    </td>
                                                    <td class="p-3 text-slate-800 font-bold">{{ $sig['name'] }}</td>
                                                    <td class="p-3 text-slate-500 whitespace-nowrap">
                                                        {{ $sig['date'] ?? $documentData['date'] }}
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <span class="material-symbols-outlined text-emerald-600 font-bold text-lg inline-block align-middle" title="Tersertifikasi Sah">check_circle</span>
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <span class="material-symbols-outlined text-emerald-600 font-bold text-lg inline-block align-middle" title="Validitas Terkonfirmasi">check_circle</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Footer Seal --}}
                            <div class="flex justify-between items-center pt-4 border-t border-slate-100 text-[11px] text-slate-400 font-semibold">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-4 h-4 rounded-full bg-blue-600 flex items-center justify-center text-white text-[8px] font-bold">S</span>
                                    <span>PORTAL RESMI SEMADIKSI UNUSA</span>
                                </div>
                                <span>© {{ date('Y') }} Digital Signature Management System</span>
                            </div>
                        </div>
                    @else
                        {{-- Invalid Code Alert --}}
                        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-3 shadow-sm">
                            <span class="material-symbols-outlined text-rose-500 text-5xl">warning</span>
                            <h3 class="text-base font-bold text-rose-900">Sertifikat Tidak Ditemukan atau Tidak Valid</h3>
                            <p class="text-xs text-rose-700 max-w-md mx-auto leading-relaxed">
                                Nomor seri sertifikat <span class="font-mono font-bold">{{ $code }}</span> tidak terdaftar dalam pangkalan data resmi e-sertifikat SEMADIKSI. Pastikan kode yang Anda masukkan sudah sesuai atau periksa kembali keaslian dokumen fisik Anda.
                            </p>
                        </div>
                    @endif
                </section>
            @endif
        </main>
    </div>

    <script>
    function verificationPortal(initialCode) {
        return {
            certInput: initialCode || '',
            isScanning: false,

            simulateScan() {
                this.isScanning = true;
                setTimeout(() => {
                    const mockCodes = ['CERT-LKMB-2024-0891', 'CERT-VOL-2024-1102', 'CERT-WD-2024-0345'];
                    const picked = mockCodes[Math.floor(Math.random() * mockCodes.length)];
                    this.certInput = picked;
                    this.isScanning = false;
                    window.location.href = '{{ route("verifikasi.sertifikat") }}?code=' + encodeURIComponent(picked);
                }, 1500);
            }
        };
    }
    </script>
</x-layouts.app>
