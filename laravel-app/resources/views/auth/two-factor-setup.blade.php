<x-layouts.app title="Aktivasi Autentikasi Dua Faktor (2FA)">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-lg bg-surface border border-surface-variant/80 rounded-3xl p-8 sm:p-10 shadow-xl space-y-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 mx-auto flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl">security</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-on-surface">Aktivasi Autentikasi Dua Faktor (2FA)</h1>
                <p class="text-xs text-outline mt-1.5 leading-relaxed">
                    Demi keamanan akun Anda, silakan hubungkan aplikasi autentikator (Google Authenticator, Aegis, Authy, dll.) sebelum melanjutkan.
                </p>
            </div>

            <div class="space-y-4">
                <div class="p-5 rounded-2xl bg-surface-container border border-surface-variant/60 flex flex-col items-center justify-center space-y-4 text-center">
                    @if(!empty($qrCodeBase64))
                        <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200">
                            <img src="{{ $qrCodeBase64 }}" alt="QR Code 2FA" class="w-44 h-44 mx-auto">
                        </div>
                        <p class="text-[11px] text-outline font-medium">Pindai kode QR di atas menggunakan aplikasi autentikator Anda.</p>
                    @endif

                    <div class="w-full pt-2 border-t border-surface-variant/40 space-y-2">
                        <span class="block text-[11px] font-bold text-outline uppercase tracking-wider">Kunci Manual (Base32)</span>
                        <div class="flex items-center justify-center gap-2">
                            <code class="px-3 py-1.5 rounded-xl bg-surface border border-surface-variant font-mono text-xs font-bold text-primary tracking-widest select-all">
                                {{ $secret }}
                            </code>
                        </div>
                        @if(!empty($otpauthUrl))
                            <div class="pt-1">
                                <a href="{{ $otpauthUrl }}" class="text-[11px] text-primary hover:underline font-semibold inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                                    Buka langsung di aplikasi autentikator
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <form action="{{ route('two-factor.confirm') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="code" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Masukkan 6 Digit Kode Konfirmasi
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="code" 
                                name="code" 
                                maxlength="6" 
                                inputmode="numeric" 
                                autocomplete="one-time-code" 
                                required 
                                autofocus
                                placeholder="123456" 
                                class="w-full text-center tracking-[0.4em] font-mono font-black text-xl py-3 bg-surface-container rounded-2xl border border-surface-variant/80 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 text-on-surface transition-all @error('code') border-rose-500 @enderror"
                            >
                        </div>
                        @error('code')
                            <p class="text-xs text-rose-600 font-medium mt-1.5 flex items-center gap-1 justify-center">
                                <span class="material-symbols-outlined text-sm">error</span>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3.5 px-4 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-sm shadow-lg shadow-amber-600/25 active:scale-[0.99] transition-all flex items-center justify-center gap-2"
                    >
                        <span class="material-symbols-outlined text-lg">verified_user</span>
                        Konfirmasi &amp; Aktifkan 2FA
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
