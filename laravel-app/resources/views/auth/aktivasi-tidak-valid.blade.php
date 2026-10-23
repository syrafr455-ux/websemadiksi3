<x-layouts.app title="Link Aktivasi Tidak Valid">
    <div class="py-16 px-4">
        <div class="max-w-lg mx-auto bg-surface border border-surface-container rounded-2xl p-8 shadow-sm text-center space-y-4">
            <span class="material-symbols-outlined text-4xl text-amber-500">link_off</span>
            <h2 class="text-xl font-bold text-on-surface">Link aktivasi tidak dapat dipakai</h2>
            <p class="text-sm text-outline">{{ $pesan }}</p>
            <div class="flex flex-wrap justify-center gap-3 pt-2">
                @if(! empty($tautanMasuk))
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors">Masuk</a>
                    <a href="{{ route('password.request') }}" class="px-4 py-2 rounded-xl border border-primary text-primary text-sm font-semibold hover:bg-primary/5 transition-colors">Lupa Kata Sandi</a>
                @endif
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-xl border border-surface-container text-on-surface text-sm font-semibold hover:bg-surface-container transition-colors">Kembali ke Pendaftaran</a>
            </div>
        </div>
    </div>
</x-layouts.app>
