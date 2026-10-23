<x-layouts.app title="Masuk Portal Mahasiswa">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md bg-surface border border-surface-variant/80 rounded-2xl p-8 sm:p-10 shadow-sm" x-data="{ showPass: false }">
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-xl bg-primary/10 text-primary mx-auto flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl">school</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">Masuk ke SEMADIKSI</h1>
                <p class="text-sm text-outline mt-1.5">Gunakan akun UNUSA atau NIM/email yang terdaftar.</p>
            </div>

            @if(!empty($ssoAktif))
                <div class="mb-6">
                    <a 
                        href="{{ route('auth.login') }}" 
                        class="w-full py-3 px-4 rounded-xl border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary font-semibold text-sm transition-all flex items-center justify-center gap-2.5 active:scale-[0.99]"
                    >
                        <span class="material-symbols-outlined text-xl">account_circle</span>
                        Masuk dengan akun UNUSA
                    </a>
                </div>

                <div class="relative flex items-center justify-center my-6">
                    <div class="border-t border-surface-variant/80 w-full"></div>
                    <span class="bg-surface px-3 text-xs font-medium text-outline uppercase tracking-wider relative">atau dengan kata sandi</span>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="identity" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-2">
                        Email Mahasiswa atau NIM
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
                            class="w-full pl-11 pr-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/80 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('identity') border-rose-500 @enderror"
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
                        <label for="password" class="block text-xs font-semibold text-on-surface uppercase tracking-wider">
                            Kata Sandi
                        </label>
                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-primary hover:underline">Lupa kata sandi?</a>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-xl">lock</span>
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Masukkan kata sandi" 
                            class="w-full pl-11 pr-11 py-2.5 bg-surface-container rounded-xl border border-surface-variant/80 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('password') border-rose-500 @enderror"
                        >
                        <button 
                            type="button" 
                            @click="showPass = !showPass" 
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                            aria-label="Tampilkan atau sembunyikan kata sandi"
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
                    class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm transition-all flex items-center justify-center gap-2 active:scale-[0.99]"
                >
                    <span class="material-symbols-outlined text-xl">login</span>
                    Masuk
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/80 text-center space-y-2 text-xs text-outline">
                <p>
                    Belum memiliki akun? 
                    <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">Daftar sekarang</a>
                </p>
                <p>
                    Akses pengurus: 
                    <a href="{{ route('admin.login') }}" class="font-medium text-on-surface hover:underline">Masuk sebagai staf</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
