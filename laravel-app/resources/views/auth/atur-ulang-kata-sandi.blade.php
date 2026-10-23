<x-layouts.app title="Atur Ulang Kata Sandi">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md bg-surface border border-surface-container rounded-2xl p-8 sm:p-10 shadow-sm" x-data="{ showPass: false }">
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl bg-primary/10 mx-auto flex items-center justify-center text-primary mb-3">
                    <span class="material-symbols-outlined text-2xl">password</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-on-surface">Atur Ulang Kata Sandi</h2>
                <p class="text-sm text-outline mt-1.5">Buat kata sandi baru minimal 8 karakter yang memuat huruf dan angka.</p>
            </div>

            <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                @error('email')
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-start gap-2">
                        <span class="material-symbols-outlined text-lg">link_off</span>
                        <span>
                            {{ $message }}
                            <a href="{{ route('password.request') }}" class="block mt-1 font-bold underline">Minta link baru</a>
                        </span>
                    </div>
                @enderror

                <div>
                    <label for="email" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Email Akun</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        required
                        @if($email) readonly @endif
                        autocomplete="username"
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-sm font-medium text-outline"
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Kata Sandi Baru</label>
                    <div class="relative">
                        <input
                            :type="showPass ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            autofocus
                            autocomplete="new-password"
                            class="w-full pl-4 pr-11 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('password') border-rose-500 @enderror"
                        >
                        <button type="button" @click="showPass = !showPass" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface">
                            <span class="material-symbols-outlined text-xl" x-text="showPass ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-xs text-rose-600 font-medium mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Ulangi Kata Sandi Baru</label>
                    <input
                        :type="showPass ? 'text' : 'password'"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary/90 text-white font-semibold text-sm shadow-sm transition-colors flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    Simpan Kata Sandi Baru
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
