<!DOCTYPE html>
<html lang="id" class="h-full antialiased font-sans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Portal Mahasiswa SEMADIKSI' }} - UNUSA</title>
    <meta name="description" content="Wadah perjuangan dan kolaborasi mahasiswa beasiswa KIP-Kuliah Universitas Nahdlatul Ulama Surabaya.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col bg-background text-on-background selection:bg-primary/20">

    {{-- Main Navbar --}}
    <header class="sticky top-0 z-40 bg-surface/80 backdrop-blur-md border-b border-surface-variant/40 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                {{-- Logo & Brand --}}
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-primary to-primary-container flex items-center justify-center text-white shadow-md shadow-primary/20 group-hover:scale-105 transition-transform duration-300">
                        <span class="material-symbols-outlined text-2xl">school</span>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-primary block leading-none">SEMADIKSI</span>
                        <span class="text-xs font-medium text-outline uppercase tracking-wider block mt-1">Portal Mahasiswa KIP-K UNUSA</span>
                    </div>
                </a>

                {{-- Desktop Navigation --}}
                <nav class="hidden md:flex items-center gap-1">
                    <a href="{{ route('home') }}" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('home') ? 'bg-primary/10 text-primary' : 'text-on-surface hover:text-primary hover:bg-surface-container' }}">
                        Beranda
                    </a>
                    <a href="{{ route('profil.semadiksi') }}" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('profil.semadiksi') ? 'bg-primary/10 text-primary' : 'text-on-surface hover:text-primary hover:bg-surface-container' }}">
                        Profil
                    </a>
                    <a href="{{ route('berita.acara.index') }}" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('berita.acara.*') ? 'bg-primary/10 text-primary' : 'text-on-surface hover:text-primary hover:bg-surface-container' }}">
                        Berita Acara
                    </a>
                </nav>

                {{-- Auth Button Action --}}
                <div class="hidden md:flex items-center gap-3">
                    @auth
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm shadow-md transition-all">
                                <span class="material-symbols-outlined text-lg">admin_panel_settings</span>
                                Panel Admin
                            </a>
                        @else
                            <a href="{{ route('dashboard.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm shadow-md shadow-primary/20 transition-all">
                                <span class="material-symbols-outlined text-lg">dashboard</span>
                                Dashboard Mahasiswa
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-primary hover:bg-primary/10 transition-colors">
                            Masuk
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-container text-white font-semibold text-sm shadow-md shadow-primary/20 transition-all hover:scale-105 active:scale-95">
                            Daftar Akun
                            <span class="material-symbols-outlined text-base">arrow_forward</span>
                        </a>
                    @endauth
                </div>

                {{-- Mobile Menu Button --}}
                <div class="md:hidden flex items-center" x-data="{ mobileMenu: false }">
                    <button @click="mobileMenu = !mobileMenu" type="button" class="p-2 rounded-xl text-on-surface hover:bg-surface-container transition-colors">
                        <span class="material-symbols-outlined text-2xl" x-text="mobileMenu ? 'close' : 'menu'">menu</span>
                    </button>

                    {{-- Mobile Drawer Dropdown --}}
                    <div x-show="mobileMenu" @click.away="mobileMenu = false" x-transition class="absolute top-20 left-0 right-0 bg-surface border-b border-surface-variant/40 shadow-xl p-5 flex flex-col gap-2 z-50">
                        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-on-surface hover:bg-primary/10 hover:text-primary">Beranda</a>
                        <a href="{{ route('profil.semadiksi') }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-on-surface hover:bg-primary/10 hover:text-primary">Profil SEMADIKSI</a>
                        <a href="{{ route('berita.acara.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-on-surface hover:bg-primary/10 hover:text-primary">Berita Acara</a>
                        <hr class="border-surface-variant/40 my-2">
                        @auth
                            <a href="{{ Auth::user()->isAdmin() ? route('admin.dashboard') : route('dashboard.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-bold bg-primary text-white text-center">Menuju Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-primary text-primary text-center">Masuk</a>
                            <a href="{{ route('register') }}" class="px-4 py-2.5 rounded-xl text-sm font-bold bg-primary text-white text-center">Daftar Akun Baru</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-rose-600">error</span>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Content Slot & Section --}}
    <main class="flex-1">
        @if(isset($slot) && trim($slot))
            {{ $slot }}
        @else
            @yield('content')
        @endif
    </main>

    {{-- Modern Footer --}}
    <footer class="bg-surface-container border-t border-surface-variant/40 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white">
                            <span class="material-symbols-outlined text-xl">school</span>
                        </div>
                        <span class="text-lg font-bold text-primary">SEMADIKSI UNUSA</span>
                    </div>
                    <p class="text-sm text-outline max-w-md leading-relaxed">
                        Sentra Komunikasi dan Perjuangan Mahasiswa Penerima Kartu Indonesia Pintar Kuliah (KIP-K) Universitas Nahdlatul Ulama Surabaya. Berintegritas, Mandiri, dan Berprestasi.
                    </p>
                    <p class="text-xs text-outline/80">
                        Kampus B UNUSA: Jl. Raya Jemursari No.57, Surabaya, Jawa Timur 60237
                    </p>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-4">Layanan Cepat</h4>
                    <ul class="space-y-2.5 text-sm text-outline">
                        <li><a href="{{ route('berita.acara.index') }}" class="hover:text-primary transition-colors">Berita Acara</a></li>
                        <li><a href="{{ route('profil.semadiksi') }}" class="hover:text-primary transition-colors">Struktur Organisasi</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-4">Akses Sistem</h4>
                    <ul class="space-y-2.5 text-sm text-outline">
                        <li><a href="{{ route('login') }}" class="hover:text-primary transition-colors">Masuk Mahasiswa</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-primary transition-colors">Registrasi Akun</a></li>
                        <li><a href="{{ route('admin.login') }}" class="hover:text-primary transition-colors">Portal Administrator</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-surface-variant/30 mt-8 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-outline">
                <p>&copy; {{ date('Y') }} SEMADIKSI UNUSA. Hak Cipta Dilindungi Undang-Undang.</p>
                <p class="mt-2 sm:mt-0 font-medium">Biro Kemahasiswaan & Kerjasama UNUSA</p>
            </div>
        </div>
    </footer>

</body>
</html>
