@extends('layouts.app')

@section('content')
<div class="space-y-16 pb-20">
    {{-- Hero Section --}}
    <section class="relative overflow-hidden py-16 md:py-24 bg-gradient-to-b from-primary/10 via-primary/5 to-transparent text-center px-4 sm:px-6 lg:px-8 border-b border-surface-container">
        <div class="max-w-4xl mx-auto space-y-6">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
                <span class="material-symbols-outlined text-sm">stars</span>
                Organisasi Resmi Mahasiswa KIP-Kuliah UNUSA
            </span>
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-on-surface leading-tight">
                Profil <span class="text-primary bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary-container">SEMADIKSI</span> UNUSA
            </h1>
            <p class="text-base sm:text-lg text-on-surface-variant max-w-2xl mx-auto leading-relaxed">
                Serikat Mahasiswa Bidikmisi &amp; KIP-Kuliah Universitas Nahdlatul Ulama Surabaya.
                Wadah persaudaraan, pembinaan prestasi, integritas keilmuan, dan kontribusi nyata penerima beasiswa bagi bangsa.
            </p>
            <div class="flex flex-wrap gap-4 justify-center pt-4">
                <a href="#visi-misi" class="px-8 py-3.5 rounded-2xl bg-primary text-white font-bold text-sm shadow-lg shadow-primary/25 hover:bg-primary/90 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">visibility</span>
                    Visi &amp; Misi
                </a>
                <a href="#pengurus" class="px-8 py-3.5 rounded-2xl border-2 border-primary/30 text-primary hover:bg-primary/5 font-bold text-sm active:scale-95 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">group</span>
                    BPH Organisasi
                </a>
            </div>
        </div>
    </section>

    {{-- Tentang Semadiksi --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-secondary/10 text-secondary text-xs font-bold uppercase tracking-wider">
                    <span class="material-symbols-outlined text-sm">history_edu</span>
                    Tentang Kami
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-on-surface tracking-tight">
                    Mengenal Lebih Dekat SEMADIKSI
                </h2>
                <div class="space-y-4 text-on-surface-variant leading-relaxed text-base">
                    <p>
                        <strong class="text-on-surface">SEMADIKSI</strong> merupakan organisasi kemahasiswaan internal di Universitas Nahdlatul Ulama Surabaya yang mewadahi seluruh mahasiswa penerima beasiswa Bidikmisi dan Kartu Indonesia Pintar Kuliah (KIP-K).
                    </p>
                    <p>
                        Didirikan dengan asas kekeluargaan dan solidaritas tinggi melalui semboyan khas <em class="text-primary font-semibold">"Seduluran Selawase"</em>, SEMADIKSI berkomitmen menjadi pendorong akselerasi potensi akademik dan non-akademik anggotanya untuk melahirkan lulusan berkarakter Islami, profesional, dan berdaya saing global.
                    </p>
                    <p>
                        Kami aktif berkolaborasi menyinergikan program kerja beasiswa dengan pihak Rektorat UNUSA, LLDIKTI Wilayah VII, Kemendikbudristek, serta mitra pengabdian masyarakat demi kemaslahatan bersama.
                    </p>
                </div>
            </div>
            <div class="relative rounded-3xl overflow-hidden shadow-2xl border border-surface-container aspect-video">
                <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1000&auto=format&fit=crop&q=80" class="w-full h-full object-cover" alt="SEMADIKSI Mahasiswa KIP-K UNUSA">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end p-8">
                    <div>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-primary text-white mb-2 shadow">
                            #SeduluranSelawase
                        </span>
                        <h4 class="text-white text-lg font-bold">Kolaborasi &amp; Persaudaraan Erat Mahasiswa KIP-K UNUSA</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Visi & Misi --}}
    <section id="visi-misi" class="py-16 bg-surface-container-low border-y border-surface-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            <div class="text-center max-w-2xl mx-auto">
                <span class="px-3 py-1 rounded-lg bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider">Arah Perjuangan</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-on-surface tracking-tight mt-2">
                    Visi &amp; Misi Organisasi
                </h2>
                <p class="text-sm text-on-surface-variant mt-2">
                    Landasan ideal kepengurusan SEMADIKSI dalam mencetak generasi penerima beasiswa unggul.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
                {{-- Visi --}}
                <div class="lg:col-span-5 bg-surface border border-surface-container rounded-3xl p-8 shadow-sm flex flex-col justify-center text-center space-y-4">
                    <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto shadow-inner">
                        <span class="material-symbols-outlined text-4xl">visibility</span>
                    </div>
                    <h3 class="text-2xl font-extrabold text-on-surface tracking-tight">VISI</h3>
                    <p class="text-on-surface-variant font-medium leading-relaxed italic text-lg">
                        "Menjadi wadah pembinaan mahasiswa beasiswa KIP-K UNUSA yang berkarakter, unggul, berjiwa sosial, dan mandiri berlandaskan nilai-nilai Rahmatan Lil Alamin."
                    </p>
                </div>

                {{-- Misi --}}
                <div class="lg:col-span-7 bg-surface border border-surface-container rounded-3xl p-8 shadow-sm space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center shadow-inner">
                            <span class="material-symbols-outlined text-3xl">my_location</span>
                        </div>
                        <h3 class="text-2xl font-extrabold text-on-surface tracking-tight">MISI</h3>
                    </div>
                    <ul class="space-y-4">
                        @foreach([
                            'Membina karakter mahasiswa KIP-K yang religius, berintegritas, beradab, serta senantiasa berprestasi di kancah akademik maupun non-akademik.',
                            'Mengembangkan kreativitas, inovasi, dan soft skill kepemimpinan melalui program pelatihan berkala dan terstruktur.',
                            'Menyelenggarakan kegiatan sosial kemasyarakatan dan pengabdian demi kebermanfaatan umat serta mengharumkan nama almamater UNUSA.',
                            'Menjaga dan mempererat solidaritas, kerukunan, rasa kekeluargaan, serta kolaborasi sinergis antar sesama mahasiswa beasiswa KIP-K.'
                        ] as $misi)
                            <li class="flex gap-4 items-start">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-outlined text-sm font-bold">check</span>
                                </span>
                                <span class="text-on-surface-variant text-base leading-relaxed">
                                    {{ $misi }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Departemen & Divisi --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center max-w-2xl mx-auto">
            <span class="px-3 py-1 rounded-lg bg-tertiary/10 text-tertiary text-xs font-bold uppercase tracking-wider">Struktur Fungsional</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-on-surface tracking-tight mt-2">
                Departemen &amp; Divisi Kerja
            </h2>
            <p class="text-sm text-on-surface-variant mt-2">
                Empat pilar utama SEMADIKSI dalam merealisasikan program kerja berdampak.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @php
                $divisi = [
                    [
                        'name' => 'Pendidikan & Penalaran',
                        'icon' => 'school',
                        'desc' => 'Mengembangkan kompetensi ilmiah, karya tulis ilmiah (PKM), bimbingan akademik, dan program tutor sebaya untuk seluruh mahasiswa beasiswa KIP-K UNUSA.',
                        'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    ],
                    [
                        'name' => 'Pengabdian Masyarakat',
                        'icon' => 'volunteer_activism',
                        'desc' => 'Wadah kepedulian sosial melalui aksi pengabdian masyarakat nyata, program SEMADIKSI Mengajar di desa binaan, dan aksi tanggap bencana sosial.',
                        'badge' => 'bg-blue-50 text-blue-700 border-blue-200'
                    ],
                    [
                        'name' => 'Minat & Bakat (Soft Skill)',
                        'icon' => 'sports_esports',
                        'desc' => 'Mewadahi pengembangan potensi non-akademik, olahraga, seni budaya, serta rangkaian workshop peningkatan leadership & soft skill mahasiswa.',
                        'badge' => 'bg-amber-50 text-amber-700 border-amber-200'
                    ],
                    [
                        'name' => 'Humas & Media Informasi',
                        'icon' => 'campaign',
                        'desc' => 'Bertanggung jawab atas publikasi informasi resmi, pengelolaan website & media sosial organisasi, serta sinergi relasi eksternal kampus.',
                        'badge' => 'bg-purple-50 text-purple-700 border-purple-200'
                    ]
                ];
            @endphp

            @foreach($divisi as $div)
                <div class="bg-surface border border-surface-container rounded-3xl p-6 shadow-sm flex gap-5 hover:shadow-md hover:border-primary/40 transition-all">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center border shrink-0 {{ $div['badge'] }}">
                        <span class="material-symbols-outlined text-3xl">{{ $div['icon'] }}</span>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-lg text-on-surface">{{ $div['name'] }}</h4>
                        <p class="text-on-surface-variant text-sm leading-relaxed">{{ $div['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- BPH Pengurus --}}
    <section id="pengurus" class="py-16 bg-surface-container-low border-t border-surface-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            <div class="text-center max-w-2xl mx-auto">
                <span class="px-3 py-1 rounded-lg bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider">Struktur Organisasi</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-on-surface tracking-tight mt-2">
                    Badan Pengurus Harian (BPH)
                </h2>
                <p class="text-sm text-on-surface-variant mt-2">
                    Nakhoda utama kepengurusan organisasi SEMADIKSI UNUSA Periode 2024/2026.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
                @php
                    $bph = [
                        [
                            'role' => 'Pembina SEMADIKSI',
                            'name' => 'Dr. Ir. H. Achmad Jazidie, M.Eng.',
                            'univ' => 'Rektor Universitas Nahdlatul Ulama Surabaya',
                            'avatar' => 'AJ'
                        ],
                        [
                            'role' => 'Ketua Umum',
                            'name' => 'Ahmad Fauzan',
                            'univ' => 'Teknik Informatika 2021',
                            'avatar' => 'AF'
                        ],
                        [
                            'role' => 'Wakil Ketua Umum',
                            'name' => 'Siti Nurhaliza',
                            'univ' => 'S1 Keperawatan 2021',
                            'avatar' => 'SN'
                        ],
                        [
                            'role' => 'Sekretaris Umum',
                            'name' => 'Lailatul Fitriah',
                            'univ' => 'S1 Manajemen 2022',
                            'avatar' => 'LF'
                        ],
                        [
                            'role' => 'Bendahara Umum',
                            'name' => 'Muhammad Ilham',
                            'univ' => 'S1 Akuntansi 2021',
                            'avatar' => 'MI'
                        ]
                    ];
                @endphp

                @foreach($bph as $item)
                    <div class="bg-surface border border-surface-container rounded-3xl p-6 shadow-sm text-center flex flex-col items-center hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-primary to-primary-container text-white flex items-center justify-center font-extrabold text-xl mb-4 shadow-md shadow-primary/20">
                            {{ $item['avatar'] }}
                        </div>
                        <span class="text-[11px] uppercase tracking-wider font-extrabold text-primary mb-1 block">
                            {{ $item['role'] }}
                        </span>
                        <h4 class="font-bold text-on-surface text-base line-clamp-2 mb-1">
                            {{ $item['name'] }}
                        </h4>
                        <p class="text-on-surface-variant text-xs line-clamp-2 leading-relaxed">
                            {{ $item['univ'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Ajakan Kerjasama --}}
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-primary/10 via-primary/5 to-surface border border-primary/20 rounded-3xl p-8 sm:p-12 text-center space-y-6 shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-primary text-white flex items-center justify-center mx-auto shadow-lg shadow-primary/30">
                <span class="material-symbols-outlined text-3xl">handshake</span>
            </div>
            <div class="space-y-2 max-w-xl mx-auto">
                <h3 class="text-2xl sm:text-3xl font-extrabold text-on-surface">
                    Ingin Berkolaborasi dengan SEMADIKSI?
                </h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Kami terbuka untuk kerjasama seminar beasiswa, program pengabdian desa binaan, sponsorship, maupun kemitraan positif lainnya.
                </p>
            </div>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="mailto:humas@semadiksi.ac.id" class="px-8 py-3.5 rounded-2xl bg-primary text-white font-bold text-sm shadow-md hover:bg-primary/90 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">mail</span>
                    Hubungi Humas Kami
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
