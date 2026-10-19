<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Fasilitas / Ruangan UNUSA
    |--------------------------------------------------------------------------
    |
    | DATA SEMENTARA. Daftar ini dibaca oleh ConfigUnusaFacilityProvider.
    | Saat API sistem informasi kampus tersedia, cukup buat provider baru yang
    | mengimplementasikan App\Contracts\UnusaFacilityProvider lalu ganti binding
    | di AppServiceProvider -- controller & validasi tidak perlu diubah.
    |
    */

    'facilities' => [
        ['code' => 'AUD-TWR-9', 'name' => 'Auditorium Tower Lantai 9 - Kampus B Jemursari', 'campus' => 'Kampus B', 'capacity' => 500],
        ['code' => 'AUD-A', 'name' => 'Aula Utama Kampus A Wonokromo', 'campus' => 'Kampus A', 'capacity' => 400],
        ['code' => 'SEM-RKT', 'name' => 'Ruang Seminar Gedung Rektorat', 'campus' => 'Kampus B', 'capacity' => 150],
        ['code' => 'RPT-KMH', 'name' => 'Ruang Rapat Biro Kemahasiswaan', 'campus' => 'Kampus B', 'capacity' => 40],
        ['code' => 'LAB-KOM-1', 'name' => 'Laboratorium Komputer 1', 'campus' => 'Kampus B', 'capacity' => 60],
        ['code' => 'GSG', 'name' => 'Gedung Serba Guna (GSG) UNUSA', 'campus' => 'Kampus B', 'capacity' => 800],
    ],

    // Cache hasil provider (detik); 0 = tanpa cache.
    'facilities_cache_ttl' => (int) env('UNUSA_FACILITIES_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Kalender Akademik (Periode Semester)
    |--------------------------------------------------------------------------
    |
    | Bulan awal tiap semester, dihitung dari tanggal 1 pukul 00:00 waktu lokal.
    | Semester berakhir tepat sebelum semester berikutnya dimulai.
    | Default sesuai kalender akademik UNUSA 2026/2027:
    | Gasal = Agustus-Januari, Genap = Februari-Juli.
    |
    */

    'semester' => [
        'gasal_start_month' => 8,
        'genap_start_month' => 2,
        'timezone' => 'Asia/Jakarta',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Email Verifikator Berkas
    |--------------------------------------------------------------------------
    |
    | Akun verifikator (Admin Fakultas / Admin Prodi) wajib memakai email dengan
    | domain persis salah satu dari daftar ini (subdomain tidak otomatis ikut).
    |
    */

    'verifikator_email_domains' => ['unusa.ac.id'],

];
