<x-layouts.app title="Masuk Administrator">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-slate-900/5">
        <div class="w-full max-w-md bg-surface/95 backdrop-blur-xl border border-amber-500/20 rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10" x-data="{ showPass: false }">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 to-amber-700 mx-auto flex items-center justify-center text-white shadow-lg shadow-amber-600/30 mb-4">
                    <span class="material-symbols-outlined text-3xl">admin_panel_settings</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Panel Administrator</h2>
                <p class="text-sm text-outline mt-1.5">Masuk untuk mengelola verifikasi beasiswa & kegiatan SEMADIKSI.</p>
            </div>

            <form action="{{ route('admin.login.post') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Email Administrator
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-xl">badge</span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            required 
                            autofocus
                            placeholder="admin@semadiksi.ac.id" 
                            class="w-full pl-11 pr-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20 text-sm font-medium transition-all @error('email') border-rose-500 @enderror"
                        >
                    </div>
                    @error('email')
                        <p class="text-xs text-rose-600 font-medium mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Kata Sandi Administrator
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-xl">shield_lock</span>
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="••••••••••••" 
                            class="w-full pl-11 pr-11 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20 text-sm font-medium transition-all"
                        >
                        <button 
                            type="button" 
                            @click="showPass = !showPass" 
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                        >
                            <span class="material-symbols-outlined text-xl" x-text="showPass ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-4 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm shadow-lg shadow-amber-600/25 transition-all hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-xl">security</span>
                    Otentikasi Administrator
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/40 text-center text-xs text-outline">
                <a href="{{ route('home') }}" class="hover:text-primary transition-colors flex items-center justify-center gap-1 font-medium">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Kembali ke Halaman Utama
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
