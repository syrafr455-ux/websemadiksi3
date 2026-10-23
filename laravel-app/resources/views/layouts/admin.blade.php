<!DOCTYPE html>
<html lang="id" class="h-full antialiased font-sans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? 'Panel Administrator') - SEMADIKSI UNUSA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-900 text-slate-100 flex admin-body" style="background-color: #0f172a; color: #f8fafc;" x-data="{ sidebarOpen: false }">

    {{-- Admin Sidebar --}}
    <aside 
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 border-r border-slate-800 flex flex-col transition-transform duration-300 ease-in-out shadow-2xl lg:shadow-none"
    >
        <div class="h-20 px-6 border-b border-slate-800 flex items-center justify-between">
            <a href="{{ \App\Support\MenuAdmin::beranda(Auth::user()) ?? route('home') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-600 flex items-center justify-center text-white shadow-sm">
                    <span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
                </div>
                <div>
                    <span class="text-base font-bold text-amber-500 block leading-none">SEMADIKSI</span>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest block mt-1">Panel Pengelola</span>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-800">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1 text-xs">
            {{-- Satu sumber menu dengan AdminLayout.jsx: App\Support\MenuAdmin (hanya link yang boleh diakses role ini). --}}
            @foreach (\App\Support\MenuAdmin::untuk(Auth::user(), request()) as $menu)
                <a href="{{ $menu['href'] }}" data-menu-admin="{{ $menu['route'] }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors {{ $menu['active'] ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                    <span class="material-symbols-outlined text-lg">{{ $menu['icon'] }}</span>
                    {{ $menu['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="p-4 border-t border-slate-800">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-rose-400 hover:bg-rose-950/40 transition-colors">
                    <span class="material-symbols-outlined text-lg">logout</span>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Admin App Area --}}
    <div class="flex-1 lg:pl-72 flex flex-col min-h-screen bg-slate-900 text-slate-100" style="background-color: #0f172a;">
        <header class="h-20 bg-slate-950 border-b border-slate-800 sticky top-0 z-30 px-6 flex items-center justify-between" style="background-color: #020617;">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl text-slate-400 hover:bg-slate-900">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
                <div>
                    <h1 class="text-base font-bold text-white">@yield('pageTitle', $pageTitle ?? 'Panel Pengelola')</h1>
                    <p class="text-xs text-slate-400">@yield('pageSubtitle', $pageSubtitle ?? 'Sistem Informasi SEMADIKSI UNUSA')</p>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs">
                <a href="{{ route('home') }}" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">public</span>
                    Lihat Web Publik
                </a>
                <span class="text-amber-500 font-bold hidden sm:inline">{{ Auth::user()->name }}</span>
            </div>
        </header>

        @if(session('success'))
            <div class="px-6 mt-4">
                <div class="p-4 rounded-2xl bg-emerald-950 border border-emerald-800 text-emerald-200 text-xs font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-400">check_circle</span>
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="px-6 mt-4">
                <div class="p-4 rounded-2xl bg-rose-950 border border-rose-800 text-rose-200 text-xs font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined text-rose-400">error</span>
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <main class="flex-1 p-6 bg-slate-900 text-slate-100" style="background-color: #0f172a;">
            @if(isset($slot) && trim($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </main>
    </div>

</body>
</html>
