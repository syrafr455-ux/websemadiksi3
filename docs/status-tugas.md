# Status Pengerjaan Tugas SEMADIKSI

Status pelacakan pengerjaan bertahap:

- [x] **Bagian 1: Alur Kegiatan Peserta**
  - Bug (a) diselesaikan: Alur pendaftaran diarahkan ke kegiatan kanonikal dan pendaftaran tersimpan di `kegiatan_registrations` sehingga langsung muncul di "Kegiatan Saya & E-Tiket" beserta tiket dan nomor kursi.
  - Bug (b) diselesaikan: `DashboardController@kegiatan` dan `@index` kini membaca tabel `kegiatan` dengan filter status, sasaran peserta (role KIP-K verified, UNUSA internal, umum), dan pencarian.
  - E2E Test berhasil: `Tests\Feature\AlurKegiatanPesertaTest` memvalidasi pembuatan kegiatan oleh super admin, visibilitas di landing page dan dashboard peserta login sesuai sasaran, pendaftaran gratis berkursi, penampilan di Kegiatan Saya, check-in tiket fase 1, presensi akhir tanda tangan fase 2, penerbitan sertifikat, dan proteksi draft.
- [x] **Bagian 2: Penyatuan Alur Lama**
  - Pemetaan tabel lama di `docs/penyatuan-alur-lama.md` mencakup `activities -> kegiatan`, `attendances -> kegiatan_presensi`, dan `certificates -> sertifikat`. Data ambigu (tanpa activity_id yang valid) dibiarkan sebagai arsip baca-saja.
  - Route `/absensi` publik dan dashboard dialihkan ke `Kegiatan` dan `KegiatanPresensi`.
  - Route `/verifikasi` memprioritaskan tabel `sertifikat` alur baru dan mempertahankan fallback aman ke tabel legacy `certificates`.
  - Dibuat perintah `php artisan semadiksi:migrasi-alur-lama` dengan dry-run bawaan (tanpa ubah data) dan opsi `--eksekusi` yang idempoten tanpa menimpa data baru.
  - Kode mati `HomeController@verifikasiSertifikat` yang tidak dirujuk telah dibersihkan.
  - Tes fitur `Tests\Feature\PenyatuanAlurLamaTest` (4 tes, 37 assertions) lulus.
- [x] **Bagian 3: Halaman Admin Presensi Akhir**
  - Menu "Presensi & Kehadiran" di Admin ditingkatkan khusus super_admin: pemilih kegiatan, rekapitulasi check-in fase 1, presensi mandiri fase 2, tanda tangan digital, dan status sertifikat.
  - Format waktu presensi disajikan dalam zona waktu WIB (`Asia/Jakarta`).
  - Tanda tangan digital dilindungi ketat lewat `KegiatanPresensiPolicy@viewSignature` dan endpoint khusus `/admin/presensi/{id}/signature` (hanya dapat diakses oleh super admin, tidak pernah terekspos ke publik atau staf lain).
  - Filter (status check-in, presensi akhir, sertifikat), pencarian multifield, dan ekspor CSV/Excel (`admin.attendances.export`) selesai.
  - Tes fitur `Tests\Feature\AdminPresensiAkhirTest` (5 tes, 63 assertions) lulus mencakup pengujian otorisasi per role, validasi policy, dan format WIB.
- [x] **Bagian 4: Penilaian Karya Format Excel (.xlsx)**
  - Implementasi ekspor dan impor spreadsheet OpenXML (.xlsx) native via `SimpleXlsxService` menggunakan `ZipArchive` dan XML standard tanpa dependensi eksternal yang rentan permission lock pada Windows.
  - CSV dipertahankan sepenuhnya sebagai format cadangan dan kompatibilitas mundur.
  - Kolom ekspor/impor: `id karya`, `judul`, `nama peserta`, `nilai`, `catatan`. Hanya kolom nilai dan catatan yang diubah saat impor; kolom lain diabaikan atau diverifikasi.
  - Validasi lengkap: batas ukuran berkas 2 MB, batas maksimal 2000 baris data, nilai numerik valid dalam rentang 0 - 100, penolakan transaksi jika ID karya bukan milik kegiatan tersebut.
  - Pencegahan formula injection: sel teks berawalan `=`, `+`, `-`, atau `@` dinetralkan dengan awalan petik tunggal saat ekspor, dan dibersihkan dari karakter trigger formula saat impor.
  - Otorisasi ketat: Juri (`penilai_karya`) hanya dapat mengekspor dan mengimpor kegiatan lomba yang ditugaskan padanya (pivot `kegiatan_penilai`), sedangkan `super_admin` dapat mengakses semua lomba.
  - Tes fitur `Tests\Feature\PenilaianKaryaExcelTest` (8 tes, 21 assertions) dan seluruh tes CSV lama `Tests\Feature\PenilaianAndWebhookTest` & `Tests\Feature\CakupanVerifikatorDanJuriTest` lulus 100%.
- [x] **Bagian 5: 2FA untuk Peserta Umum dan Juri**
  - Implementasi TOTP RFC 6238 native (`TotpService`) dengan HMAC-SHA1, Base32 decoding, periode 30 detik, dan toleransi jendela ±1, terbukti lulus vektor uji resmi RFC 6238 Appendix B.
  - Migrasi `database/migrations/2026_10_25_000001_add_two_factor_columns_to_users_table.php` menambahkan kolom `two_factor_secret` (terenkripsi), `two_factor_confirmed_at` (datetime), dan `two_factor_recovery_codes` (terenkripsi array hash).
  - Konfigurasi `config/unusa.php` membaca `two_factor_roles` dari environment `TWO_FACTOR_ROLES` (default: `user_umum,penilai_karya`). `.env.example` telah diperbarui.
  - Alur aktivasi 2FA: formulir setup dengan kunci manual dan URI otpauth (serta QR SVG otomatis jika pustaka QR tersedia), konfirmasi kode 6 digit, lalu menampilkan 8 kode pemulihan satu kali.
  - Alur login kata sandi di `AuthController`: mewajibkan penyelesaian 2FA untuk role target. Login SSO (Keycloak) dan role lain (`mahasiswa_kipk`, `verifikator_berkas`, `super_admin`) otomatis dilewati.
  - Middleware `EnsureTwoFactorAuthenticated` memastikan seluruh rute terautentikasi terkunci hingga verifikasi 2FA sukses, kecuali rute whitelist (2FA dan logout). Mendukung respon JSON 403 untuk API/XHR.
  - Rate limiting (5 percobaan per 10 menit per akun dan IP) dengan pesan galat netral serta regenerasi sesi saat lolos.
  - Fitur reset 2FA oleh Super Admin di `UserManagementController@resetTwoFactor` dengan validasi alasan wajib (`reason`) dan tercatat rapi di `audit_logs`.
  - Tes unit `Tests\Unit\TotpServiceTest` (2 tes, 32 assertions) dan tes fitur `Tests\Feature\TwoFactorAuthenticationTest` (8 tes, 43 assertions) lulus 100%.
- [x] **Bagian 6: Sapu Bersih Error**
  - Tes `Tests\Feature\NamedRoutesAndControllersTest` (1 tes, 310 assertions) memvalidasi seluruh rute bernama: controller class ada, method ada, dan semua view Blade maupun komponen Inertia JSX/JS yang dirujuk terbukti ada di filesystem.
  - Smoke test `Tests\Feature\RoleSmokeTest` (6 tes, 282 assertions) memvalidasi seluruh rute GET tanpa parameter bebas dari HTTP 500 untuk seluruh role: tamu/guest, mahasiswa_kipk, user_umum, verifikator_berkas, penilai_karya, dan super_admin.
  - Perbaikan error JS null Alpine.js di `/admin/berkas` dan `/admin/submissions`: mengganti evaluasi langsung properti bersarang pada objek `null` dengan state modal mandiri (`openModal`, `openDetail`, `openStatus`) dan fallback object aman, mencegah `TypeError: Cannot read properties of null` saat parsing, unmount, atau penutupan modal.
  - Penambahan null-safety dan optional chaining pada React komponen `PenilaianIndex` (`filters?.q`, `kegiatans || []`, `submissions?.data`, `e.target.files?.[0]`).
  - Pembersihan dan penyesuaian uji rute lama serta regenerasi aset Vite `npm run build` yang sukses 100%.
  - Seluruh rangkaian pengujian `php artisan test` (340 tes, 2890 assertions) lulus sempurna tanpa kegagalan.

- [x] **Bagian 7: Perbaikan Halaman Login dan Logout**
  - Galat SSO (`sso`) ditampilkan sebagai banner di atas formulir (`#sso-error-banner`), bukan di bawah isian email, dan tidak membawa isian lama (`_old_input` dibersihkan).
  - Menekan tombol SSO (`GET /auth/login`) membersihkan galat dan isian formulir kata sandi sebelumnya.
  - Galat formulir kata sandi tetap di bawah isian (`identity`), dan pesan keduanya netral tanpa membocorkan keberadaan akun ("Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.").
  - Jalur login staf:
    - Route staf: `admin.login` (`GET /admin/masuk`) dan `admin.login.post` (`POST /admin/masuk`) untuk `super_admin`, `verifikator_berkas`, dan `penilai_karya`.
    - Bila email staf diketik di `/masuk`: jika sandi salah, pesan galat tetap netral tanpa membocorkan akun staf; jika sandi benar, otomatis diautentikasi dan diarahkan ke beranda admin perannya masing-masing.
    - Halaman `/masuk` menyediakan tautan jelas dan ramah "Masuk sebagai staf" mengarah ke `/admin/masuk`.
  - Logout per role:
    - Seluruh role staf (`super_admin`, `verifikator_berkas`, `penilai_karya`) diarahkan ke `/admin/masuk` (`admin.login`).
    - Seluruh role peserta (`mahasiswa_kipk`, `user_umum`) diarahkan ke `/masuk` (`login`).
  - SSO Full Logout:
    - Menambahkan dukungan OIDC RP-initiated logout ke Keycloak `end_session_endpoint` yang dibaca dari discovery OIDC, dengan parameter `post_logout_redirect_uri` (dari `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`) dan `id_token_hint`.
    - Bawaan nonaktif via `KEYCLOAK_LOGOUT_ENABLED=false` (dalam `.env.example` dan `config/services.php`). Flag nonaktif tidak mengubah alur logout standar. Sesi langsung diinvaliasi dan token tidak disimpan lebih lama dari yang diperlukan.
  - Investigasi & Solusi Kode Masalah "Halaman Login Tampil Mengecil Setelah Logout Super Admin":
    - **Penyebab Terbukti dari Kode**: Super admin logout dari halaman Inertia (seperti `/admin/kegiatan`, `/admin/pengguna`) lewat `AdminLayout.jsx` yang memanggil `router.post('/keluar')` dengan header `X-Inertia: true`. Sebelumnya, controller mengembalikan HTTP 302 redirect biasa ke `/admin/masuk`. Fetch Inertia mengikuti 302 tersebut dan menerima respon HTML Blade 200 tanpa header `X-Inertia`. Akibatnya, method `handleNonInertiaResponse()` pada `@inertiajs/core` menganggap respon tersebut sebagai non-Inertia modal dan memanggil `dialog_default.show(html)` yang merender halaman login ke dalam `<dialog id="inertia-error-dialog">` berisi `<iframe>` berukuran `calc(100vw - 100px)` x `calc(100vh - 100px)`. Ini menyebabkan halaman login tampil mengecil di dalam modal dialog di atas dashboard admin.
    - **Perbaikan**: `AuthController@logout` kini mendeteksi request Inertia (`$request->header('X-Inertia')`) dan mengembalikan `Inertia::location($targetRoute)` (HTTP 409 dengan header `X-Inertia-Location`). Client Inertia secara native mengeksekusi `window.location.href = targetRoute`, menghasilkan navigasi browser penuh (full-page reload) ke `/admin/masuk` tanpa iframe modal dialog.
  - Pengujian Fitur:
    - `Tests\Feature\HalamanLoginDanLogoutTest` (8 tes, 67 assertions) memvalidasi banner galat SSO, form bersih tanpa isian lama, pesan netral, tautan staf, logout tiap role, pencegahan halaman mengecil via 409 Inertia location, dan flag logout Keycloak.
    - `Tests\Feature\KeycloakSsoAndSecurityTest` (18 tes, 99 assertions) diperbarui agar galat SSO dipetakan ke key `sso`.
    - Seluruh rangkaian uji `php artisan test` (348 tes, 2961 assertions) lulus 100%.

- [x] **Bagian 8: Perilaku Tombol SSO Setelah Logout & Logging Kegagalan SSO**
  - Masalah: Sesi Keycloak di browser tetap hidup setelah logout dari aplikasi SEMADIKSI, sehingga klik "Masuk dengan akun UNUSA" tidak menampilkan form login UNUSA dan otomatis masuk menggunakan sesi sebelumnya.
  - Penambahan konfigurasi `KEYCLOAK_FORCE_LOGIN_PROMPT` (bawaan `true`) di `config/services.php` dan `.env.example`.
  - Pada `KeycloakService::getAuthorizationUrl`, jika flag aktif, URL otorisasi menyertakan parameter `prompt=login` sehingga form login UNUSA selalu dimunculkan oleh IdP Keycloak tanpa merusak alur PKCE dan state yang ada.
  - Logging kegagalan SSO: Pada setiap cabang kegagalan callback di `KeycloakController` dan `KeycloakService`, sistem mencatat satu baris log `warning` dengan kode alasan spesifik tanpa menyertakan NIM, email, token, atau data pribadi:
    1. `state tidak cocok`
    2. `kode penukaran gagal`
    3. `klaim wajib hilang`
    4. `NIM tidak ada di data master`
    5. `pengguna belum teraktivasi`
    6. `akun diblokir`
    7. `role tidak diizinkan SSO`
  - Pesan galat ke pengguna tetap netral dan aman tanpa membocorkan rincian internal akun.
  - Pengujian Fitur:
    - Tes `Tests\Feature\KeycloakSsoAndSecurityTest` (26 tes, 126 assertions) memvalidasi `prompt=login` ada saat flag aktif dan hilang saat nonaktif, serta memvalidasi setiap 7 kode alasan tercatat di log tanpa data pribadi.
    - Seluruh rangkaian uji `php artisan test` (356 tes, 2988 assertions) dan build aset `npm run build` lulus sempurna 100%.

- [x] **Bagian 9: Aktivasi Akun Mahasiswa KIP-K Tertunda Lewat Login SSO**
  - Latar Belakang & Analisis Kode:
    - (a) Kapan baris pengguna dibuat: Pada pendaftaran mandiri UNUSA (`PendaftaranController::selesaikanAktivasi`), baris `User` baru dibuat *sesudah* aktivasi email/token divalidasi dan kata sandi diisi (`email_verified_at = now()`). Sebaliknya, pada pendaftaran umum (`PendaftaranController::daftarUmum`), `User` dibuat *sebelum* verifikasi email (`email_verified_at = null`). Pada pembuatan pengguna oleh Admin di `UserManagementController::store`, akun `mahasiswa_kipk` langsung dibuat dengan `verification_status = 'Verified'`, namun `email_verified_at` bernilai `null` (tertunda).
    - (b) Kolom kata sandi sebelum aktivasi: *Bisa sudah terisi*. Khususnya akun yang dibuat Admin (`UserManagementController::store` + `MahasiswaKipkRequest`) di mana kata sandi wajib diisi saat pembuatan akun, akun dari pendaftaran umum, seeder demo (`DatabaseSeeder`), dan testing factory (`UserFactory::unverified()`).
    - (c) Jalur lain yang membuat akun tertunda: Pembuatan akun oleh Super Admin (`UserManagementController::store`), seeder demo (`DatabaseSeeder::seedDemoAccount`), dan factory testing (`UserFactory::unverified()`).
  - Aturan Baru di `KeycloakService::matchOrCreateUser`:
    - Bila Keycloak berhasil mengautentikasi dan NIM dari klaim ada di data master serta cocok dengan akun `mahasiswa_kipk` yang belum teraktivasi (`email_verified_at === null`):
      - SSO menyelesaikan aktivasi akun tersebut: mengisi `email_verified_at = now()`, `verification_status = 'Verified'`, dan menautkan `keycloak_sub = $sub`.
      - Mengganti kata sandi lama yang terpasang sebelum aktivasi dengan hash acak yang tidak diketahui siapa pun (`Hash::make(Str::random(40))`) sehingga kata sandi lama tidak dapat digunakan kembali.
      - Membatalkan token aktivasi yang masih tertunda di `AktivasiMahasiswa` (`dipakai_pada = now()`).
      - Menghubungkan penerima KIP jika ada data bebas yang belum terhubung (`PenerimaKipk`).
      - Mencatat peristiwa aktivasi ke `AuditLog` dengan aksi `sso_activation` tanpa data pribadi (tanpa NIM, email, atau nama).
      - Mencatat log aplikasi info dengan kode alasan `aktivasi via SSO` tanpa data pribadi.
    - Perlindungan Keamanan:
      - Pencocokan hanya melalui `sub` dan `nim`, tidak pernah melalui email.
      - Akun diblokir (`is_blocked = true`) tetap ditolak (`akun diblokir`).
      - Peran selain `mahasiswa_kipk` tetap ditolak untuk penautan pertama via SSO (staf ditolak `role tidak diizinkan SSO`; `user_umum` tertunda ditolak `pengguna belum teraktivasi`).
      - NIM yang tidak ada di master tetap ditolak (`NIM tidak ada di data master`).
  - Pengujian Fitur:
    - 6 tes baru ditambahkan ke `Tests\Feature\KeycloakSsoAndSecurityTest`:
      1. `test_akun_mahasiswa_kipk_tertunda_teraktivasi_lewat_sso`
      2. `test_kata_sandi_lama_tidak_bisa_dipakai_lagi_setelah_aktivasi_sso`
      3. `test_token_aktivasi_lama_tidak_berlaku_setelah_aktivasi_sso`
      4. `test_akun_mahasiswa_kipk_tertunda_yang_diblokir_tetap_ditolak_sso`
      5. `test_akun_tertunda_user_umum_dan_staf_tidak_terpengaruh_oleh_sso`
      6. `test_akun_tertunda_nim_tidak_ada_di_master_tetap_ditolak_sso`
    - Seluruh pengujian (32 tes, 179 assertions pada berkas pengujian Keycloak; 362 tes, 3041 assertions pada seluruh test suite `php artisan test`) serta build frontend `npm run build` lulus sempurna 100%.

## Temuan Belum Ditangani / Catatan
- Pemasangan paket `phpoffice/phpspreadsheet` dan `openspout/openspout` via Composer dist zipball mengalami kegagalan izin penulisan file sementara pada Windows (`vendor/composer/tmp-*.zip: Permission denied`). Sesuai aturan tugas, berkas composer di-revert bersih dan sistem menggunakan cadangan OpenXML .xlsx engine bawaan PHP (`ZipArchive` + XML) dengan CSV cadangan yang tetap aktif.
- Seluruh tugas perbaikan alur login, logout, SSO prompt login, logging kegagalan SSO, dan aktivasi akun mahasiswa KIP-K tertunda via login SSO telah selesai dikerjakan, diuji, dan diverifikasi end-to-end.
