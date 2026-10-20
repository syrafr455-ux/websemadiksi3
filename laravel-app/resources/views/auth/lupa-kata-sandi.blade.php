<x-layouts.app title="Lupa Kata Sandi">
    <div class="min-h-[calc(100vh-160px)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        <div class="organic-blob w-96 h-96 bg-primary top-10 left-10"></div>
        <div class="organic-blob w-96 h-96 bg-secondary bottom-10 right-10"></div>

        <div class="w-full max-w-md bg-surface/90 backdrop-blur-xl border border-surface-variant/40 rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-primary-container mx-auto flex items-center justify-center text-white shadow-lg shadow-primary/20 mb-4">
                    <span class="material-symbols-outlined text-3xl">lock_reset</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Lupa Kata Sandi</h2>
                <p class="text-sm text-outline mt-1.5">
                    Masukkan email akun Anda. Mahasiswa UNUSA juga dapat memakai NIM.
                    Link atur ulang dikirim ke email yang tercatat di akun dan berlaku {{ $berlakuMenit }} menit.
                </p>
            </div>

            @if(session('status'))
                <div class="mb-5 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-2" data-status-lupa-sandi>
                    <span class="material-symbols-outlined text-lg">mark_email_read</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <form action="{{ route('password.email') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="identity" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Email / NIM
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
                            autocomplete="username"
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

                <button
                    type="submit"
                    class="w-full py-3.5 px-4 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-lg shadow-primary/25 transition-all flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-xl">send</span>
                    Kirim Link Atur Ulang
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/40 text-center text-xs text-outline">
                <a href="{{ $dariAdmin ? route('admin.login') : route('login') }}" class="font-bold text-primary hover:underline inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Kembali ke halaman masuk
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
