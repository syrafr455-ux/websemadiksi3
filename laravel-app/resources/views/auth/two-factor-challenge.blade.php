<x-layouts.app title="Verifikasi Dua Faktor (2FA)">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md bg-surface border border-surface-variant/80 rounded-3xl p-8 sm:p-10 shadow-xl space-y-6" x-data="{ useRecovery: false }">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 mx-auto flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl">lock_person</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-on-surface">Verifikasi Dua Langkah</h1>
                <p class="text-xs text-outline mt-1.5 leading-relaxed" x-show="!useRecovery">
                    Masukkan 6 digit kode dari aplikasi autentikator di perangkat Anda.
                </p>
                <p class="text-xs text-outline mt-1.5 leading-relaxed" x-show="useRecovery" x-cloak>
                    Masukkan salah satu kode pemulihan (recovery code) cadangan Anda.
                </p>
            </div>

            <form action="{{ route('two-factor.challenge.verify') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Mode TOTP 6-digit -->
                <div x-show="!useRecovery">
                    <label for="code" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2 text-center">
                        Kode Autentikator (6 Digit)
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            maxlength="6" 
                            inputmode="numeric" 
                            autocomplete="one-time-code" 
                            autofocus
                            placeholder="123456" 
                            class="w-full text-center tracking-[0.4em] font-mono font-black text-xl py-3 bg-surface-container rounded-2xl border border-surface-variant/80 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 text-on-surface transition-all @error('code') border-rose-500 @enderror"
                        >
                    </div>
                </div>

                <!-- Mode Recovery Code -->
                <div x-show="useRecovery" x-cloak>
                    <label for="recovery_code" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2 text-center">
                        Kode Pemulihan (Recovery Code)
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="recovery_code" 
                            name="recovery_code" 
                            placeholder="ABCD-EFGH" 
                            class="w-full text-center tracking-widest font-mono font-bold text-base py-3 bg-surface-container rounded-2xl border border-surface-variant/80 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 text-on-surface uppercase transition-all @error('code') border-rose-500 @enderror"
                        >
                    </div>
                </div>

                @error('code')
                    <p class="text-xs text-rose-600 font-medium text-center flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">error</span>
                        {{ $message }}
                    </p>
                @enderror

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-4 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-sm shadow-lg shadow-amber-600/25 active:scale-[0.99] transition-all flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                    Verifikasi Masuk
                </button>
            </form>

            <div class="pt-2 text-center border-t border-surface-variant/60">
                <button 
                    type="button" 
                    @click="useRecovery = !useRecovery" 
                    class="text-xs font-semibold text-primary hover:underline transition-colors"
                >
                    <span x-show="!useRecovery">Gunakan kode pemulihan darurat</span>
                    <span x-show="useRecovery" x-cloak>Gunakan kode aplikasi autentikator</span>
                </button>
            </div>
        </div>
    </div>
</x-layouts.app>
