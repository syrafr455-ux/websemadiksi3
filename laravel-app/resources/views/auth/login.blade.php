<x-layouts.app title="Masuk Portal Mahasiswa">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        {{-- Background decorative blobs --}}
        <div class="organic-blob w-96 h-96 bg-primary top-10 left-10"></div>
        <div class="organic-blob w-96 h-96 bg-secondary bottom-10 right-10"></div>

        <div class="w-full max-w-md bg-surface/90 backdrop-blur-xl border border-surface-variant/40 rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10" x-data="{ showPass: false }">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-primary-container mx-auto flex items-center justify-center text-white shadow-lg shadow-primary/20 mb-4">
                    <span class="material-symbols-outlined text-3xl">school</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Masuk Portal SEMADIKSI</h2>
                <p class="text-sm text-outline mt-1.5">Silakan masukkan Email Akademik atau NIM aktif Anda.</p>
            </div>

            <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="identity" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Email Mahasiswa / NIM
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-xl">person</span>
                        <input 
                            type="text" 
                            id="identity" 
                            name="identity" 
                            value="{{ old('identity') }}" 
                            required 
                            autofocus
                            placeholder="nama@student.unusa.ac.id atau 2240021001" 
                            class="w-full pl-11 pr-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('identity') border-rose-500 @enderror"
                        >
                    </div>
                    @error('identity')
                        <p class="text-xs text-rose-600 font-medium mt-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-bold text-on-surface uppercase tracking-wider">
                            Kata Sandi
                        </label>
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-primary hover:underline">Lupa kata sandi?</a>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-xl">lock</span>
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Masukkan kata sandi akun Anda" 
                            class="w-full pl-11 pr-11 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('password') border-rose-500 @enderror"
                        >
                        <button 
                            type="button" 
                            @click="showPass = !showPass" 
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                        >
                            <span class="material-symbols-outlined text-xl" x-text="showPass ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-xs text-rose-600 font-medium mt-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 cursor-pointer text-outline hover:text-on-surface">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded text-primary focus:ring-primary/20 border-surface-variant">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-4 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-lg shadow-primary/25 transition-all hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-xl">login</span>
                    Masuk ke Portal
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/40 text-center space-y-3 text-xs text-outline">
                <p>
                    Belum memiliki akun? 
                    <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Daftar sekarang</a>
                </p>
                <p>
                    Akses khusus pengurus? 
                    <a href="{{ route('admin.login') }}" class="font-semibold text-amber-700 hover:underline">Masuk sebagai Administrator</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
