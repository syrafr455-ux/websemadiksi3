<x-layouts.app title="Verifikasi Email">
    <div class="py-16 px-4">
        <div class="max-w-lg mx-auto bg-surface border border-surface-container rounded-2xl p-8 shadow-sm text-center space-y-4">
            <span class="material-symbols-outlined text-4xl text-primary">mark_email_unread</span>
            <h2 class="text-xl font-bold text-on-surface">Verifikasi email Anda</h2>
            @if(session('success'))
                <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
            @endif
            <p class="text-sm text-outline">
                Kami telah mengirim link verifikasi ke <strong>{{ auth()->user()->email }}</strong>.
                Akun Anda akan aktif setelah link tersebut dibuka.
            </p>
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors">Kirim Ulang Link Verifikasi</button>
            </form>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-outline hover:text-on-surface">Keluar</button>
            </form>
        </div>
    </div>
</x-layouts.app>
