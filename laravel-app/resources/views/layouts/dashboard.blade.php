<!DOCTYPE html>
<html lang="id" class="h-full antialiased font-sans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-authenticated" content="1" data-role="{{ Auth::user()->isStaff() ? 'staff' : 'user' }}">
    @endauth

    <title>{{ $title ?? 'Dashboard Mahasiswa' }} - SEMADIKSI UNUSA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-surface-container-low text-on-background flex" x-data="{ sidebarOpen: false }">

    {{-- Sidebar for Desktop & Mobile Overlay --}}
    <aside 
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-surface border-r border-surface-variant/40 flex flex-col transition-transform duration-300 ease-in-out shadow-lg lg:shadow-none"
    >
        {{-- Sidebar Brand --}}
        <div class="h-20 px-6 border-b border-surface-variant/30 flex items-center justify-between">
            <a href="{{ route('dashboard.index') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-primary-container flex items-center justify-center text-white shadow-md">
                    <span class="material-symbols-outlined text-2xl">school</span>
                </div>
                <div>
                    <span class="text-lg font-bold text-primary block leading-none">SEMADIKSI</span>
                    <span class="text-[11px] font-semibold text-outline uppercase tracking-wider block mt-0.5">Portal Mahasiswa</span>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-outline hover:bg-surface-container">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        {{-- Student Profile Quick Info --}}
        <div class="p-4 mx-4 my-3 rounded-2xl bg-surface-container border border-surface-variant/30 flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold overflow-hidden shrink-0">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover">
                @else
                    <span class="material-symbols-outlined text-2xl">person</span>
                @endif
            </div>
            <div class="overflow-hidden">
                <h4 class="text-sm font-bold text-on-surface truncate">{{ Auth::user()->name }}</h4>
                <p class="text-xs text-outline font-mono truncate">{{ Auth::user()->nim ?? 'Mahasiswa' }}</p>
                <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full mt-1 {{ Auth::user()->isVerified() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ Auth::user()->verification_status }}
                </span>
            </div>
        </div>

        {{-- Nav Links --}}
        <nav class="flex-1 overflow-y-auto px-4 py-2 space-y-1">
            <p class="px-3 text-[11px] font-bold text-outline uppercase tracking-wider mb-2">Utama</p>

            <a href="{{ route('dashboard.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.index') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">dashboard</span>
                Beranda Ringkasan
            </a>

            <a href="{{ route('dashboard.kegiatan') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.kegiatan') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">event</span>
                Eksplor Kegiatan
            </a>

            <a href="{{ route('dashboard.kegiatan.saya') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.kegiatan.saya') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">confirmation_number</span>
                Kegiatan Saya
            </a>

            <a href="{{ route('dashboard.sertifikat') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.sertifikat') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">workspace_premium</span>
                Sertifikat & Portofolio
            </a>

            <a href="{{ route('dashboard.absensi') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.absensi') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">camera_enhance</span>
                Presensi & Log Kehadiran
            </a>

            <p class="px-3 pt-4 text-[11px] font-bold text-outline uppercase tracking-wider mb-2">Layanan KIP-Kuliah</p>

            <a href="{{ route('dashboard.pencairan') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.pencairan') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">payments</span>
                Pengajuan Pencairan
            </a>

            <a href="{{ route('dashboard.pelaporan') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.pelaporan') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">description</span>
                Pelaporan Keaktifan
            </a>

            <a href="{{ route('dashboard.monev') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.monev') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">trending_up</span>
                Monev Akademik (IPK)
            </a>

            <a href="{{ route('dashboard.berkas') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.berkas') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">folder_shared</span>
                Berkas Dokumen KIP-K
            </a>

            <p class="px-3 pt-4 text-[11px] font-bold text-outline uppercase tracking-wider mb-2">Pusat Informasi</p>

            <a href="{{ route('dashboard.beasiswa') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.beasiswa') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">stars</span>
                Informasi Beasiswa
            </a>

            <a href="{{ route('dashboard.info.kip') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.info.kip') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">info</span>
                Panduan & Regulasi KIP
            </a>

            <a href="{{ route('dashboard.profil') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.profil') ? 'bg-primary text-white shadow-sm' : 'text-on-surface hover:bg-surface-container' }}">
                <span class="material-symbols-outlined text-xl">manage_accounts</span>
                Profil & Keamanan Akun
            </a>
        </nav>

        {{-- Bottom Logout --}}
        <div class="p-4 border-t border-surface-variant/30">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-rose-700 hover:bg-rose-50 transition-colors">
                    <span class="material-symbols-outlined text-xl">logout</span>
                    Keluar dari Akun
                </button>
            </form>
        </div>
    </aside>

    {{-- Backdrop for mobile sidebar --}}
    <div 
        x-show="sidebarOpen" 
        @click="sidebarOpen = false" 
        x-transition:enter="transition-opacity ease-linear duration-300" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="transition-opacity ease-linear duration-300" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0" 
        class="fixed inset-0 bg-black/40 z-40 lg:hidden"
    ></div>

    {{-- Main App Area --}}
    <div class="flex-1 lg:pl-72 flex flex-col min-h-screen">
        {{-- Topbar --}}
        <header class="h-20 bg-surface/80 backdrop-blur-md border-b border-surface-variant/30 sticky top-0 z-30 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl text-on-surface hover:bg-surface-container">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-on-surface">{{ $pageTitle ?? 'Portal Mahasiswa' }}</h1>
                    <p class="text-xs text-outline">{{ $pageSubtitle ?? 'Universitas Nahdlatul Ulama Surabaya' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="p-2 rounded-xl text-outline hover:text-primary hover:bg-surface-container" title="Buka Halaman Publik">
                    <span class="material-symbols-outlined text-2xl">public</span>
                </a>
                <a href="{{ route('dashboard.profil') }}" class="flex items-center gap-2 pl-3 py-1.5 pr-2 rounded-full border border-surface-variant/40 hover:bg-surface-container transition-colors">
                    <span class="text-xs font-bold text-on-surface hidden sm:inline">{{ Auth::user()->name }}</span>
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs overflow-hidden">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" alt="avatar" class="w-full h-full object-cover">
                        @else
                            {{ substr(Auth::user()->name, 0, 1) }}
                        @endif
                    </div>
                </a>
            </div>
        </header>

        {{-- Flash Messages in Dashboard --}}
        @if(session('success'))
            <div class="px-4 sm:px-6 lg:px-8 mt-4">
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('warning'))
            <div class="px-4 sm:px-6 lg:px-8 mt-4">
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600">warning</span>
                    <span class="text-sm font-medium">{{ session('warning') }}</span>
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="px-4 sm:px-6 lg:px-8 mt-4">
                <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-800 flex items-center gap-3">
                    <span class="material-symbols-outlined text-sky-600">info</span>
                    <span class="text-sm font-medium">{{ session('info') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="px-4 sm:px-6 lg:px-8 mt-4">
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3">
                    <span class="material-symbols-outlined text-rose-600">error</span>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- Main Dashboard Body --}}
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @if(isset($slot) && trim($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </main>
    </div>

</body>
</html>
