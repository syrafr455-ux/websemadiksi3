<!DOCTYPE html>
<html lang="id" class="h-full bg-surface text-on-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - SEMADIKSI UNUSA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="h-full flex flex-col items-center justify-center p-6 bg-[#f9f9f9] text-[#1a1c1c] font-sans antialiased">
    <main class="w-full max-w-lg bg-white border border-[#e2e2e2] rounded-2xl p-8 sm:p-10 shadow-sm text-center">
        <div class="w-14 h-14 rounded-xl bg-[#0d631b]/10 text-[#0d631b] mx-auto flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-3xl">@yield('icon', 'error')</span>
        </div>

        <span class="inline-block px-3 py-1 text-xs font-bold tracking-wider uppercase text-[#0d631b] bg-[#0d631b]/10 rounded-full mb-3">
            Galat @yield('code')
        </span>

        <h1 class="text-2xl font-bold tracking-tight text-[#1a1c1c] mb-2">
            @yield('title')
        </h1>

        <p class="text-sm text-[#707a6c] leading-relaxed mb-8">
            @yield('message')
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            @yield('actions')
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#0d631b] hover:bg-[#2e7d32] text-white text-sm font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">home</span>
                Kembali ke Beranda
            </a>
        </div>
    </main>

    <footer class="mt-8 text-xs text-[#707a6c] text-center">
        &copy; {{ date('Y') }} SEMADIKSI Universitas Nahdlatul Ulama Surabaya.
    </footer>
</body>
</html>
