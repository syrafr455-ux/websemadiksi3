<x-layouts.app title="Verifikasi Email">
    <div class="py-16 px-4">
        <div class="max-w-lg mx-auto bg-surface/90 border border-surface-variant/40 rounded-3xl p-10 shadow-2xl text-center space-y-4">
            <span class="material-symbols-outlined text-5xl text-primary">mark_email_unread</span>
            <h2 class="text-xl font-extrabold text-on-surface">Verifikasi email Anda</h2>
            @if(session('success'))
                <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
            @endif
            <p class="text-sm text-outline">
                Kami telah mengirim link verifikasi ke <strong>{{ auth()->user()->email }}</strong>.
                Akun Anda aktif setelah link tersebut dibuka.
            </p>
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold">Kirim Ulang Link Verifikasi</button>
            </form>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-outline hover:underline">Keluar</button>
            </form>
        </div>
    </div>
</x-layouts.app>
