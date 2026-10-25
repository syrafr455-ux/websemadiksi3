<x-layouts.app title="Simpan Kode Pemulihan 2FA">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-lg bg-surface border border-surface-variant/80 rounded-3xl p-8 sm:p-10 shadow-xl space-y-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 mx-auto flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl">key</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-on-surface">Simpan Kode Pemulihan 2FA</h1>
                <p class="text-xs text-rose-500 font-bold mt-2 flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">warning</span>
                    PENTING: Simpan kode ini sekarang. Kode ini hanya ditampilkan sekali!
                </p>
                <p class="text-xs text-outline mt-1 leading-relaxed">
                    Jika Anda kehilangan akses ke aplikasi autentikator, setiap kode pemulihan dapat digunakan satu kali untuk masuk ke akun Anda.
                </p>
            </div>

            <div class="p-5 rounded-2xl bg-surface-container border border-surface-variant/80 space-y-4">
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach($codes as $code)
                        <div class="px-3.5 py-2.5 rounded-xl bg-surface border border-surface-variant/70 text-center font-mono font-bold text-sm tracking-wider text-on-surface select-all shadow-sm">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-2">
                <a 
                    href="{{ $redirectUrl }}" 
                    class="w-full py-3.5 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-lg shadow-emerald-600/25 active:scale-[0.99] transition-all flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-lg">check_circle</span>
                    Saya Sudah Menyimpan Kode Pemulihan
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
