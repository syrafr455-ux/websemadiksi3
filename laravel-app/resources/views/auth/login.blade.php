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

            @if(session('warning'))
                <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3 text-sm" role="alert" id="session-warning-banner">
                    <span class="material-symbols-outlined text-amber-600 text-xl shrink-0 mt-0.5">warning</span>
                    <div class="flex-1 font-medium leading-relaxed">
                        {{ session('warning') }}
                    </div>
                </div>
            @endif

            @if(session('info'))
                <div class="mb-6 p-4 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 flex items-start gap-3 text-sm" role="alert">
                    <span class="material-symbols-outlined text-sky-600 text-xl shrink-0 mt-0.5">info</span>
                    <div class="flex-1 font-medium leading-relaxed">
                        {{ session('info') }}
                    </div>
                </div>
            @endif

            @if($errors->has('sso') || session('sso_error'))
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 text-sm" role="alert" id="sso-error-banner">
                    <span class="material-symbols-outlined text-rose-600 text-xl shrink-0 mt-0.5">error</span>
                    <div class="flex-1 font-medium leading-relaxed">
                        {{ $errors->first('sso') ?: session('sso_error') }}
                    </div>
                </div>
            @endif

            @if(!empty($ssoAktif))
                <div class="mb-6">
                    <a 
                        href="{{ route('auth.login') }}" 
                        id="btn-sso-unusa"
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

                <button 
                    type="submit" 
                    class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm transition-all flex items-center justify-center gap-2 active:scale-[0.99]"
                >
                    <span class="material-symbols-outlined text-xl">login</span>
                    Masuk
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/80 text-center space-y-3 text-xs text-outline">
                <p>
                    Belum memiliki akun? 
                    <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">Daftar sekarang</a>
                </p>
                <div class="pt-1">
                    <p class="text-xs text-outline">
                        Akses pengelola (super admin, verifikator, juri): 
                        <a href="{{ route('admin.login') }}" class="font-semibold text-amber-700 hover:underline inline-flex items-center gap-1">
                            <span>Masuk sebagai staf</span>
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
