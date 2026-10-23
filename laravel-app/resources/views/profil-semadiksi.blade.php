@extends('layouts.app')

@section('content')
<div class="space-y-16 pb-20">
    {{-- Hero Section --}}
    <section class="py-14 md:py-20 bg-surface-container-low text-center px-4 sm:px-6 lg:px-8 border-b border-surface-variant/40">
        <div class="max-w-4xl mx-auto space-y-5">
            <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-semibold tracking-wide bg-primary/10 text-primary border border-primary/20">
                <span class="material-symbols-outlined text-sm">stars</span>
                Organisasi Mahasiswa KIP-Kuliah UNUSA
            </span>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-on-surface leading-tight">
                Profil <span class="text-primary">SEMADIKSI</span> UNUSA
            </h1>
            <p class="text-base text-on-surface-variant max-w-2xl mx-auto leading-relaxed">
                Serikat Mahasiswa Bidikmisi dan KIP-Kuliah Universitas Nahdlatul Ulama Surabaya.
                Wadah pembinaan, kebersamaan, dan kontribusi mahasiswa penerima beasiswa KIP-K.
            </p>
            <div class="flex flex-wrap gap-3.5 justify-center pt-2">
                <a href="#visi-misi" class="px-6 py-2.5 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary-container transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">visibility</span>
                    Visi &amp; Misi
                </a>
                <a href="#pengurus" class="px-6 py-2.5 rounded-xl border border-primary/30 text-primary hover:bg-primary/5 font-semibold text-sm transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">group</span>
                    Pengurus Harian
                </a>
            </div>
        </div>
    </section>

    {{-- Tentang Semadiksi --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div class="space-y-5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-primary/10 text-primary text-xs font-semibold uppercase tracking-wider">
                    <span class="material-symbols-outlined text-sm">history_edu</span>
                    Tentang Kami
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">
                    Mengenal SEMADIKSI UNUSA
                </h2>
                <div class="space-y-3.5 text-on-surface-variant leading-relaxed text-sm sm:text-base">
                    <p>
                        <strong class="text-on-surface">SEMADIKSI</strong> adalah organisasi kemahasiswaan di lingkungan Universitas Nahdlatul Ulama Surabaya yang menaungi mahasiswa penerima beasiswa Kartu Indonesia Pintar Kuliah (KIP-K) dan Bidikmisi.
                    </p>
                    <p>
                        Dengan asas kekeluargaan melalui semboyan <em class="text-primary font-semibold">"Seduluran Selawase"</em>, SEMADIKSI mendampingi mahasiswa dalam kegiatan akademik, pengembangan keterampilan, kepemimpinan, dan kegiatan sosial kemasyarakatan.
                    </p>
                    <p>
                        Organisasi ini berkoordinasi secara aktif dengan Biro Kemahasiswaan UNUSA untuk memastikan kelancaran evaluasi studi, pelaporan berkas, dan penyaluran hak-hak mahasiswa penerima beasiswa.
                    </p>
                </div>
            </div>
            <div class="bg-surface border border-surface-variant rounded-2xl p-8 shadow-sm space-y-4">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">diversity_3</span>
                </div>
                <h3 class="text-lg font-bold text-on-surface">Kebersamaan &amp; Pembinaan Mahasiswa</h3>
                <p class="text-sm text-outline leading-relaxed">
                    Setiap anggota didorong untuk aktif berprestasi di tingkat regional maupun nasional, menjaga nilai akademik, serta berkontribusi nyata bagi almamater dan masyarakat.
                </p>
                <div class="pt-2 border-t border-surface-variant/40 flex items-center justify-between text-xs text-outline">
                    <span>Semboyan Organisasi:</span>
                    <span class="font-bold text-primary">#SeduluranSelawase</span>
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
                <div class="lg:col-span-5 bg-surface border border-surface-variant rounded-2xl p-7 shadow-sm flex flex-col justify-center text-center space-y-4">
                    <div class="w-14 h-14 rounded-xl bg-primary/10 text-primary flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-3xl">visibility</span>
                    </div>
                    <h3 class="text-xl font-bold text-on-surface tracking-tight">VISI</h3>
                    <p class="text-on-surface-variant font-medium leading-relaxed italic text-base">
                        "Menjadi wadah pembinaan mahasiswa beasiswa KIP-K UNUSA yang berkarakter, unggul, berjiwa sosial, dan mandiri berlandaskan nilai-nilai Rahmatan Lil Alamin."
                    </p>
                </div>

                {{-- Misi --}}
                <div class="lg:col-span-7 bg-surface border border-surface-variant rounded-2xl p-7 shadow-sm space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">my_location</span>
                        </div>
                        <h3 class="text-xl font-bold text-on-surface tracking-tight">MISI</h3>
                    </div>
                    <ul class="space-y-3.5">
                        @foreach([
                            'Membina karakter mahasiswa KIP-K yang religius, berintegritas, beradab, serta senantiasa berprestasi di kancah akademik maupun non-akademik.',
                            'Mengembangkan kreativitas, inovasi, dan soft skill kepemimpinan melalui program pelatihan berkala dan terstruktur.',
                            'Menyelenggarakan kegiatan sosial kemasyarakatan dan pengabdian demi kebermanfaatan umat serta mengharumkan nama almamater UNUSA.',
                            'Menjaga dan mempererat solidaritas, kerukunan, rasa kekeluargaan, serta kolaborasi sinergis antar sesama mahasiswa beasiswa KIP-K.'
                        ] as $misi)
                            <li class="flex gap-3 items-start">
                                <span class="w-5 h-5 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-outlined text-xs font-bold">check</span>
                                </span>
                                <span class="text-on-surface-variant text-sm leading-relaxed">
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
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        <div class="text-center max-w-2xl mx-auto">
            <span class="px-3 py-1 rounded-md bg-primary/10 text-primary text-xs font-semibold uppercase tracking-wider">Struktur Organisasi</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight mt-2">
                Divisi Kerja
            </h2>
            <p class="text-sm text-on-surface-variant mt-1.5">
                Bidang pengembangan dan pelaksanaan program kerja SEMADIKSI.
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
                        'desc' => 'Mewadahi pengembangan potensi non-akademik, olahraga, seni budaya, serta rangkaian workshop peningkatan kepemimpinan mahasiswa.',
                        'badge' => 'bg-amber-50 text-amber-700 border-amber-200'
                    ],
                    [
                        'name' => 'Humas & Media Informasi',
                        'icon' => 'campaign',
                        'desc' => 'Bertanggung jawab atas publikasi informasi resmi, pengelolaan website dan media sosial organisasi, serta komunikasi publik.',
                        'badge' => 'bg-purple-50 text-purple-700 border-purple-200'
                    ]
                ];
            @endphp

            @foreach($divisi as $div)
                <div class="bg-surface border border-surface-variant rounded-2xl p-6 shadow-sm flex gap-4 hover:border-primary/40 transition-all">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center border shrink-0 {{ $div['badge'] }}">
                        <span class="material-symbols-outlined text-2xl">{{ $div['icon'] }}</span>
                    </div>
                    <div class="space-y-1.5">
                        <h4 class="font-bold text-base text-on-surface">{{ $div['name'] }}</h4>
                        <p class="text-on-surface-variant text-xs sm:text-sm leading-relaxed">{{ $div['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- BPH Pengurus --}}
    <section id="pengurus" class="py-14 bg-surface-container-low border-t border-surface-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="text-center max-w-2xl mx-auto">
                <span class="px-3 py-1 rounded-md bg-primary/10 text-primary text-xs font-semibold uppercase tracking-wider">Kepengurusan</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight mt-2">
                    Pengurus Harian
                </h2>
                <p class="text-sm text-on-surface-variant mt-1.5">
                    Pengurus harian organisasi SEMADIKSI UNUSA.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
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
                    <div class="bg-surface border border-surface-variant rounded-2xl p-5 shadow-sm text-center flex flex-col items-center hover:border-primary/40 transition-all">
                        <div class="w-16 h-16 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-lg mb-3">
                            {{ $item['avatar'] }}
                        </div>
                        <span class="text-[11px] uppercase tracking-wider font-semibold text-primary mb-1 block">
                            {{ $item['role'] }}
                        </span>
                        <h4 class="font-bold text-on-surface text-sm line-clamp-2 mb-1">
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
        <div class="bg-surface border border-surface-variant rounded-2xl p-8 sm:p-10 text-center space-y-5 shadow-sm">
            <div class="w-14 h-14 rounded-xl bg-primary/10 text-primary flex items-center justify-center mx-auto">
                <span class="material-symbols-outlined text-3xl">handshake</span>
            </div>
            <div class="space-y-2 max-w-xl mx-auto">
                <h3 class="text-xl sm:text-2xl font-bold text-on-surface">
                    Hubungi Pengurus SEMADIKSI
                </h3>
                <p class="text-on-surface-variant text-sm leading-relaxed">
                    Kami terbuka untuk informasi kegiatan kemahasiswaan, agenda pengabdian, maupun koordinasi program beasiswa KIP-K.
                </p>
            </div>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="mailto:kemahasiswaan@unusa.ac.id" class="px-6 py-2.5 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary-container transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">mail</span>
                    Kirim Email
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
