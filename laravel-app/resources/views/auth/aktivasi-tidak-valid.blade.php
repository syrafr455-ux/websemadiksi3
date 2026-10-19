<x-layouts.app title="Link Aktivasi Tidak Valid">
    <div class="py-16 px-4">
        <div class="max-w-lg mx-auto bg-surface/90 border border-surface-variant/40 rounded-3xl p-10 shadow-2xl text-center space-y-4">
            <span class="material-symbols-outlined text-5xl text-amber-500">link_off</span>
            <h2 class="text-xl font-extrabold text-on-surface">Link aktivasi tidak dapat dipakai</h2>
            <p class="text-sm text-outline">{{ $pesan }}</p>
            <div class="flex justify-center gap-3 pt-2">
                @if(! empty($tautanMasuk))
                    <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold">Masuk</a>
                @endif
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-xl border border-primary text-primary text-sm font-bold">Ke Halaman Pendaftaran</a>
            </div>
        </div>
    </div>
</x-layouts.app>
