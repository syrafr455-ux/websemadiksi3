<!DOCTYPE html>
<html lang="id" class="h-full antialiased font-sans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-authenticated" content="1" data-role="{{ Auth::user()->isStaff() ? 'staff' : 'user' }}">
    @endauth

    <title>@yield('title', $title ?? 'Panel Administrator') - SEMADIKSI UNUSA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 selection:bg-emerald-500 selection:text-white">
{{-- Struktur dan kelas sama dengan resources/js/Layouts/AdminLayout.jsx (acuan). Ubah keduanya bersamaan. --}}
<div data-tata-letak="admin" class="min-h-screen bg-slate-950 text-slate-100 flex font-sans" x-data="{ sidebarOpen: false }">

    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>

    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed top-0 bottom-0 left-0 z-50 w-72 bg-slate-900 border-r border-slate-800/80 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
    >
        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between">
            <a href="{{ \App\Support\MenuAdmin::beranda(Auth::user()) ?? route('home') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 font-black text-white text-lg tracking-wider">
                    SD
                </div>
                <div>
                    <div class="text-base font-bold tracking-wide text-emerald-400">SEMADIKSI</div>
                    <div class="text-xs uppercase font-bold text-slate-400 tracking-widest">Panel Admin • UNUSA</div>
                </div>
            </a>
            <button type="button" class="lg:hidden text-slate-400 hover:text-white p-1" @click="sidebarOpen = false" aria-label="Tutup menu">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <div class="p-4 mx-4 my-3 rounded-2xl bg-slate-950/70 border border-slate-800/60 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-black flex items-center justify-center text-sm">
                {{ strtoupper(mb_substr(Auth::user()->name ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Admin' }}</div>
                <div class="text-xs text-slate-400 truncate">{{ Auth::user()->email ?? '-' }}</div>
                <div class="mt-1">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                        {{ Auth::user()->role instanceof \App\Enums\UserRole ? Auth::user()->role->label() : (string) Auth::user()->role }}
                    </span>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 py-2 space-y-1 overflow-y-auto">
            {{-- Satu sumber menu dengan AdminLayout.jsx: App\Support\MenuAdmin (hanya link yang boleh diakses role ini). --}}
            @foreach (\App\Support\MenuAdmin::untuk(Auth::user(), request()) as $menu)
                <a href="{{ $menu['href'] }}" data-menu-admin="{{ $menu['route'] }}" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ $menu['active'] ? 'bg-emerald-700 text-white shadow-lg shadow-emerald-700/25 font-bold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">{{ $menu['icon'] }}</span>
                        <span>{{ $menu['label'] }}</span>
                    </div>
                    @if(!empty($menu['badge']))
                        <span class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-400 text-slate-950">{{ $menu['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="p-4 border-t border-slate-800/80 space-y-2">
            <a href="{{ route('home') }}" target="_blank" rel="noreferrer" class="flex items-center justify-center gap-2 w-full px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/80 transition-colors">
                <span class="material-symbols-outlined text-base">public</span>
                <span>Lihat Web Publik</span>
            </a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center justify-center gap-2 w-full px-3 py-2 rounded-xl text-xs font-bold text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition-colors">
                    <span class="material-symbols-outlined text-base">logout</span>
                    <span>Keluar Sistem</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 lg:pl-72 flex flex-col min-w-0">
        <header class="sticky top-0 z-30 h-16 bg-slate-900/80 border-b border-slate-800/80 px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button type="button" @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none" aria-label="Buka menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-sm font-bold text-white tracking-wide">@yield('pageTitle', $pageTitle ?? 'Panel Administrator')</h1>
            </div>
        </header>

        @foreach ([
            'success' => ['bg-emerald-500/15 border-emerald-500/30 text-emerald-300', 'text-emerald-400', 'check_circle'],
            'warning' => ['bg-amber-500/15 border-amber-500/30 text-amber-300', 'text-amber-400', 'warning'],
            'error' => ['bg-rose-500/15 border-rose-500/30 text-rose-300', 'text-rose-400', 'error'],
        ] as $jenis => [$kelasKotak, $kelasIkon, $ikon])
            @if(session($jenis))
                <div class="p-4 px-6">
                    <div class="flex items-center gap-2 p-4 rounded-xl border text-xs font-medium shadow-md {{ $kelasKotak }}">
                        <span class="material-symbols-outlined {{ $kelasIkon }}">{{ $ikon }}</span>
                        <span>{{ session($jenis) }}</span>
                    </div>
                </div>
            @endif
        @endforeach

        <main class="flex-1 p-6 lg:p-8">
            @if(isset($slot) && trim($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </main>
    </div>
</div>
</body>
</html>
