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

    /*
    |--------------------------------------------------------------------------
    | Formulir yang Diproses Verifikator Berkas
    |--------------------------------------------------------------------------
    |
    | form_key CustomForm yang boleh dilihat & diproses verifikator, tetap dibatasi
    | cakupan fakultas/prodinya. "pencairan" SELALU hanya untuk super_admin dan
    | diabaikan bila dicantumkan di sini (lihat CustomFormSubmission::formVerifikator()).
    |
    */

    'verifikator_form_keys' => ['monev', 'pelaporan'],

    // Aturan email:rfc,dns (cek MX domain email). Selalu aktif di luar tes; phpunit.xml mematikannya
    // (VALIDASI_EMAIL_DNS=false) agar tes tidak membutuhkan koneksi internet.
    'validasi_email_dns' => (bool) env('VALIDASI_EMAIL_DNS', true),

    /*
    |--------------------------------------------------------------------------
    | Sumber Data Akademik & Status KIP-K
    |--------------------------------------------------------------------------
    |
    | "database" = tabel lokal hasil impor CSV oleh super_admin (saat ini).
    | "api"      = integrasi API (kerangka kosong, belum diimplementasikan).
    |
    */

    'academic_provider' => env('UNUSA_ACADEMIC_PROVIDER', 'database'),

    'kip_recipient_provider' => env('KIP_RECIPIENT_PROVIDER', 'database'),

    // Masa berlaku link aktivasi pendaftaran jalur Mahasiswa UNUSA (menit).
    'aktivasi_berlaku_menit' => 60,

    // Kirim ulang link aktivasi: jeda antar kiriman (detik) dan batas per hari (24 jam) per NIM.
    'aktivasi_jeda_detik' => 60,
    'aktivasi_maks_per_hari' => 3,

    /*
    |--------------------------------------------------------------------------
    | Domain Email Mahasiswa yang Diizinkan
    |--------------------------------------------------------------------------
    |
    | Link aktivasi hanya dikirim ke email master yang domainnya (persis) ada di daftar ini.
    | Env UNUSA_STUDENT_EMAIL_DOMAINS dipisah koma.
    |
    */

    'student_email_domains' => array_values(array_filter(array_map(
        function (string $domain): string {
            $domain = strtolower(trim($domain));
            if (str_contains($domain, '@')) {
                $domain = substr(strrchr($domain, '@') ?: '', 1);
            }

            return $domain;
        },
        explode(',', (string) env('UNUSA_STUDENT_EMAIL_DOMAINS', 'student.unusa.ac.id'))
    ))),

    // Jalur pendaftaran "Mahasiswa Umum" disembunyikan sementara. Role, tabel, dan form admin tetap ada.
    'registrasi_umum_aktif' => (bool) env('REGISTRASI_UMUM_AKTIF', false),

    // Klaim Keycloak yang dipakai sebagai NIM mahasiswa (default preferred_username).
    'keycloak_nim_claim' => env('KEYCLOAK_NIM_CLAIM', 'preferred_username'),

    /*
    |--------------------------------------------------------------------------
    | Klien API UNUSA (dipakai bila UNUSA_ACADEMIC_PROVIDER=api)
    |--------------------------------------------------------------------------
    |
    | Lihat docs/api-unusa.md. Verifikasi sertifikat TLS tidak boleh dimatikan.
    |
    */

    'api' => [
        'base_url' => env('UNUSA_API_BASE_URL'),
        // Issuer OIDC (mis. https://sso.contoh.ac.id/realms/unusa). Endpoint token diambil dari discovery.
        'issuer' => env('UNUSA_API_ISSUER'),
        'client_id' => env('UNUSA_API_CLIENT_ID'),
        'client_secret' => env('UNUSA_API_CLIENT_SECRET'),
        'scope' => env('UNUSA_API_SCOPE'),
        // Path data mahasiswa; {nim} diganti NIM.
        'path_mahasiswa' => env('UNUSA_API_PATH_MAHASISWA', '/mahasiswa/{nim}'),
        'timeout' => 5,
        // Percobaan ulang setelah gagal tersambung (total percobaan = 1 + retry).
        'retry' => 2,
        // Pengamanan klien API: batas laju per menit sisi klien, circuit breaker, dan cache TTL.
        'rate_limit_per_minute' => (int) env('UNUSA_API_RATE_LIMIT_PER_MINUTE', 60),
        'circuit_breaker_threshold' => (int) env('UNUSA_API_CB_THRESHOLD', 3),
        'circuit_breaker_cooldown_seconds' => (int) env('UNUSA_API_CB_COOLDOWN_SECONDS', 300),
        'cache_ttl_seconds' => (int) env('UNUSA_API_CACHE_TTL_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengamanan Pengiriman Email
    |--------------------------------------------------------------------------
    |
    | Batas laju antrean, kuota harian, percobaan ulang bertahap (1, 5, 15 menit),
    | dan penundaan email massal saat kuota harian tercapai.
    |
    */
    'email_protection' => [
        'rate_limit_per_minute' => (int) env('EMAIL_RATE_LIMIT_PER_MINUTE', 20),
        'daily_quota' => (int) env('EMAIL_DAILY_QUOTA', 400),
        'retry_backoff' => [60, 300, 900], // 1, 5, 15 menit
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Wajib 2FA (Two Factor Authentication) Saat Login Kata Sandi
    |--------------------------------------------------------------------------
    |
    | Login SSO otomatis melewati kewajiban 2FA ini.
    |
    */
    'two_factor_roles' => array_values(array_filter(array_map('trim', explode(',', env('TWO_FACTOR_ROLES', 'user_umum,penilai_karya'))))),

];
