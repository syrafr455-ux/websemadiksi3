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

- [x] **Bagian 10: Larangan Cache Tombol Back & Batas Sesi 15 Menit Inaktivitas**
  - **Latar Belakang & Masalah**:
    - Setelah pengguna logout dan mendarat di `/masuk`, menekan tombol *Back* pada browser menampilkan kembali halaman dasbor lengkap dengan data akun yang tersimpan di bfcache/cache browser.
    - Sesi pengguna di semua peran (mahasiswa, admin, verifikator, juri) harus otomatis keluar setelah 15 menit tanpa aktivitas untuk melindungi keamanan akun di perangkat bersama.
  - **Bagian 1: Larangan Cache (Anti-Back History Cache)**:
    - Middleware `App\Http\Middleware\PreventBackHistoryCache`:
      - Diterapkan pada grup middleware `web` di `bootstrap/app.php` membungkus seluruh respons.
      - Menambahkan header `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, dan `Expires: Fri, 01 Jan 1990 00:00:00 GMT` pada:
        - Semua halaman terautentikasi (semua peran: peserta, admin, juri, verifikator).
        - Respons Inertia (`X-Inertia: true` atau header `X-Inertia`).
        - Halaman login (`/masuk`, `/admin/masuk`, form 2FA, form pendaftaran dan aktivasi).
      - Mengecualikan berkas biner/unduhan (`BinaryFileResponse`), streaming (`StreamedResponse`), aset statis, dan halaman publik (landing `/`, `/verifikasi*`, sertifikat publik).
      - Menjaga header `no-store` yang sudah ditentukan controller spesifik (misal `bukaNomor` pada KIP-K).
    - Sisi Klien / Browser:
      - Ditambahkan skrip `resources/js/session-guard.js` yang dimuat pada entri app Blade (`app.js`) dan Inertia (`app.jsx`).
      - Pada event `pageshow` dengan `event.persisted === true` (halaman dipulihkan dari bfcache browser) di halaman terautentikasi (`meta[name="user-authenticated"]`), melakukan `window.location.reload()`.
      - Pada logout via Inertia di layout `ParticipantLayout.jsx` dan `AdminLayout.jsx`, memanggil `router.clearHistory()` untuk mengosongkan riwayat kunjungan dan prefetched cache Inertia di sisi klien.
      - Alur logout di `AuthController::logout`: menghapus remember token pengguna (`$user->setRememberToken(null)->saveQuietly()`), memanggil `Auth::logout()`, meng-invalidate sesi (`$request->session()->invalidate()`), dan me-regenerate token CSRF (`$request->session()->regenerateToken()`).
  - **Bagian 2: Batas 15 Menit Inaktivitas & Penghapusan Remember-Me**:
    - Konfigurasi:
      - Menambahkan `idle_timeout_minutes` di `config/session.php` bernilai `(int) env('SESSION_IDLE_TIMEOUT_MINUTES', 15)`.
      - Menambahkan `SESSION_IDLE_TIMEOUT_MINUTES=15` di `.env.example`.
      - Menjelaskan hubungan di komentar `config/session.php`: `SESSION_LIFETIME` (bawaan 120 menit) harus selalu `>= SESSION_IDLE_TIMEOUT_MINUTES` (15 menit) agar garbage collection sesi PHP/Laravel tidak memotong sesi sebelum batas inaktivitas tercapai.
    - Middleware Sisi Server `App\Http\Middleware\EnforceSessionTimeout`:
      - Berjalan pada pengguna terautentikasi di grup `web`.
      - Melacak `last_activity_time` dan `last_activity_user_id` di sesi.
      - Bila inaktivitas melebihi batas (15 menit):
        - Melakukan logout penuh: invalidasi sesi, regenerasi token CSRF, dan penghapusan remember token.
        - Staf dialihkan ke `/admin/masuk`, peserta dialihkan ke `/masuk` dengan flash message: *"Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali."*.
        - Permintaan Inertia mendapatkan respons 409 dengan header `X-Inertia-Location` (Inertia::location) ke halaman login terkait.
        - Permintaan JSON / API / ping mendapatkan respons 401 Unauthorized dengan JSON `{"message": "Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali."}`.
        - Di `bootstrap/app.php`, exception handler menangani 401 dan 419 (CSRF token mismatch) untuk permintaan Inertia secara ramah dengan mengalihkan via `Inertia::location` ke halaman login beserta pesan peringatan, mencegah dialog modal galat mentah.
    - Keputusan Desain Fitur "Ingat Saya":
      - Fitur "Ingat saya di perangkat ini" secara inheren bertentangan dengan batas keras 15 menit tanpa aktivitas.
      - Kotak centang "Ingat saya" dihapus dari formulir login peserta (`resources/views/auth/login.blade.php`).
      - Nilai `remember` pada permintaan login diabaikan di `AuthController::login` dan `AuthController::adminLogin` (`Auth::login($user, false)`).
      - Di `EnforceSessionTimeout`, setiap permintaan yang terautentikasi via cookie remember-me (`Auth::viaRemember()`) langsung dilogout, token remember di-null-kan, dan dialihkan ke login.
    - Klien / Pelacak Aktivitas (`resources/js/session-guard.js`):
      - Mendengarkan event aktivitas pengguna (`mousemove`, `mousedown`, `keydown`, `scroll`, `touchstart`, `input`), dibatasi (throttled) per 1 detik.
      - Pengguna yang aktif secara otomatis memicu ping setiap 2.5 menit ke endpoint `POST /sesi/ping` (terautentikasi, di-throttle 60/menit, mengembalikan `{"ok": true, "status": "active"}` tanpa membocorkan data pribadi). Jika pengguna menganggur, tidak ada ping yang dikirim sehingga sesi di server akan kedaluwarsa tepat pada batas 15 menit.
      - Mendeteksi proses unggahan berkas (form `enctype="multipart/form-data"` atau input berkas yang sedang diunggah) untuk menunda hitung mundur inaktivitas agar formulir panjang/unggah berkas tidak terputus di tengah jalan.
      - Pada menit ke-14 tanpa aktivitas: menampilkan modal peringatan ramah *"Sesi akan berakhir dalam 1 menit"* dengan tombol *"Tetap masuk"* (tanpa data pribadi). Mengklik tombol ini memicu ping dan memperpanjang sesi.
      - Pada menit ke-15 tanpa aktivitas: secara otomatis memicu redirect ke halaman login yang sesuai dengan pesan sesi berakhir.
  - **Pengujian & Verifikasi**:
    - Dibuat suite pengujian `Tests\Feature\SessionTimeoutAndCachePreventionTest` (8 tes, 51 assertions) yang memverifikasi:
      1. Respons terautentikasi dan halaman login memuat header `no-store, no-cache, must-revalidate, max-age=0` serta `Expires` lampau; landing publik dan `/verifikasi` tidak memuat `no-store`.
      2. Setelah logout, akses ke dasbor dialihkan ke login dan token sesi lama tidak valid.
      3. Permintaan pada menit ke-14 berhasil dan memperpanjang sesi; permintaan pada menit ke-16 dialihkan ke login dengan pesan Indonesia; staf diarahkan ke `/admin/masuk`.
      4. Permintaan Inertia yang kedaluwarsa menerima 409 dengan `X-Inertia-Location`; permintaan JSON/ping menerima 401.
      5. Endpoint ping memperpanjang aktivitas tanpa membocorkan data pribadi.
      6. Form login tidak lagi memuat elemen checkbox `remember`.
      7. Autentikasi via `Auth::viaRemember()` langsung dilogout.
    - Seluruh test suite `php artisan test` (370 tes, 3094 assertions) lulus 100%.
    - `npm run build` dan `php vendor/bin/pint` lulus tanpa galat.

- [x] **Bagian 11: Pemindai QR Tiket (Presensi Awal) & Sesi Presensi Akhir di Dasbor Admin**
  - **Temuan Bagian 0 (Analisis Kode Eksisting)**:
    1. **Format Kode Tiket & Tampilan QR Peserta**:
       - Kode tiket dibuat di `App\Models\KegiatanRegistration::generateTicketNumber(int $kegiatanId, ?string $seatCode): string` dengan format `TKT-{kegiatan_id}-{random_6_karakter}-{seat_code_atau_GEN}` (contoh: `TKT-12-X7Q2M9-B4` atau `TKT-12-X7Q2M9-GEN`).
       - Disimpan di kolom `kegiatan_registrations.nomor_tiket`.
       - Di dashboard peserta (`KegiatanSaya.jsx`), QR code tiket sebelumnya menggunakan URL eksternal `api.qrserver.com/?size=...` yang bergantung pada koneksi internet. Kini diganti dengan rute mandiri offline `/tiket/{nomorTiket}/qr` yang merender SVG menggunakan `SimpleSoftwareIO\QrCode\Facades\QrCode`.
    2. **Tabel & Kolom Presensi Dua Fase**:
       - **Fase 1 (Check-In Awal)**: Tabel `kegiatan_registrations`, kolom `status_kehadiran` (`registered`, `checked_in`, `absent`), `checked_in_at` (timestamp), `nomor_tiket`, dan `status_pembayaran`.
       - **Fase 2 (Presensi Akhir Mandiri)**: Tabel `kegiatan_presensi`, kolom `kegiatan_id`, `user_id`, `registration_id`, `nama_lengkap`, `nim`, `prodi`, `email`, `nomor_telepon`, `signature_path`, dan `check_in_at`.
    3. **Alur Presensi Mandiri Fase 2 & Tanda Tangan Digital Saat Ini**:
       - Peserta mengakses route `/kegiatan/{kegiatan}/presensi` atau `/presensi/{kegiatan}` (`PresensiController@showSelfAttendance`) yang merender `presensi.self-attendance`.
       - Peserta mengisi identitas dan membubuhkan tanda tangan canvas HTML5 (dikirim sebagai base64 PNG).
       - Controller `PresensiController@submitSelfAttendance` menyimpan tanda tangan ke storage public, memperbarui `kegiatan_registrations.status_kehadiran = 'checked_in'`, menyimpan baris `KegiatanPresensi`, dan menerbitkan sertifikat otomatis melalui `CertificateGeneratorService`.
    4. **Controller, View, dan Policy untuk `/admin/absensi`**:
       - Controller: `App\Http\Controllers\Admin\DashboardController::attendances()` dan `exportAttendances()`.
       - View: `resources/views/admin/attendances/index.blade.php` (Blade admin layout gelap dengan ringkasan 4 kartu statistik, pencarian, dan tabel rekapitulasi peserta).
       - Policy: `App\Policies\KegiatanPresensiPolicy` membatasi akses `viewAny`, `viewSignature`, `scanTicket`, `managePresensiAkhir`, dan `export` hanya untuk `super_admin` (`$user->isSuperAdmin()`).
    5. **Enum Status Tiket dan Pembayaran**:
       - `App\Enums\AttendanceStatus`: `Registered = 'registered'`, `CheckedIn = 'checked_in'`, `Absent = 'absent'`.
       - `App\Enums\RegistrationPaymentStatus`: `Free = 'free'`, `Pending = 'pending'`, `Paid = 'paid'`, `Expired = 'expired'`, `Failed = 'failed'`. Status sah tiket yang diakui check-in adalah `Free` dan `Paid` (`isConfirmed()`).
       - `App\Enums\KegiatanStatus`: `Draft`, `Published`, `Ongoing`, `Completed`, `Cancelled`. Kegiatan sah untuk check-in/presensi harus bertaraf terbit (`Published` atau `Ongoing`).
    6. **Ketersediaan Middleware Sesi & Header Cache**:
       - `App\Http\Middleware\PreventBackHistoryCache` aktif di grup `web` dan menyetel header `no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, dan `Expires` lampau pada seluruh halaman terautentikasi dan login.
       - `App\Http\Middleware\EnforceSessionTimeout` aktif di grup `web` membaca batas `SESSION_IDLE_TIMEOUT_MINUTES` (bawaan 15 menit), logout otomatis saat inaktivitas tercapai, dan menolak cookie `Auth::viaRemember()`.
  - **Implementasi Bagian 1: Pemindai QR Tiket (Presensi Awal)**:
    - Service class `App\Services\Kegiatan\PresensiTicketScannerService`:
      - Ekstraksi kode tiket mentah maupun dari tautan URL.
      - Validasi dalam database transaction: tiket ada, milik kegiatan terkait, status sah (lunas/gratis), kegiatan sudah terbit, berada dalam jendela check-in (bawaan 2 jam sebelum mulai s.d. selesai, dari config `kegiatan.checkin_early_hours`), belum pernah check-in.
      - Hasil terstruktur: `DITERIMA`, `SUDAH_CHECK_IN` (menyertakan waktu WIB dan nama petugas), atau `DITOLAK` dengan pesan jelas berbahasa Indonesia.
      - Pembaruan atomik (`UPDATE kegiatan_registrations SET ... WHERE status_kehadiran != 'checked_in'`) untuk mencegah race condition pencatatan ganda pada pemindaian bersamaan.
      - Pencatatan ke `audit_logs` dengan aksi `ticket_checkin`, `ticket_checkin_manual`, dan `ticket_checkin_cancelled`, memuat waktu UTC, ID petugas, metode (kamera, keyboard, manual), IP, dan alasan pembatalan/check-in manual tanpa mengekspos PII rahasia.
    - Halaman Pemindai Blade `resources/views/admin/attendances/scanner.blade.php`:
      - Kamera adaptif: Memanfaatkan API `BarcodeDetector` bawaan browser bila tersedia, dengan fallback otomatis ke `jsQR` (v1.4.0) via HTML5 Canvas.
      - Pilihan kamera depan/belakang (`facingMode`).
      - Peringatan HTTPS bila diakses dari peramban ponsel non-HTTPS dan non-localhost.
      - Pesan informatif bila izin kamera ditolak (`NotAllowedError` / `PermissionDeniedError`).
      - Cadangan input teks kode tiket selalu fokus (refocus otomatis) untuk pemindai barcode USB/Bluetooth.
      - Kartu hasil visual besar dengan ikon & teks jelas, Web Audio synthesizer beep (nada tinggi untuk berhasil, nada rendah untuk galat, nada ganda untuk peringatan), haptic vibration, auto-reset 2 detik, counter live "Check-in X dari Y", 10 riwayat pindai terakhir dengan tombol batalkan check-in (alasan wajib), serta tabel cadangan check-in manual (alasan wajib).
      - Tombol akses "Pindai Tiket" disematkan di halaman Presensi & Kehadiran (`/admin/absensi`) dan di detail kegiatan admin (`/admin/kegiatan/{id}`).
  - **Implementasi Bagian 2: Sesi Presensi Akhir (Fase 2) di Dasbor Admin**:
    - Kolom baru pada tabel `kegiatan`: `presensi_akhir_dibuka` (boolean), `presensi_akhir_mulai` (datetime UTC), dan `presensi_akhir_selesai` (datetime UTC).
    - Service class `App\Services\Kegiatan\PresensiAkhirTokenService`:
      - Token HMAC-SHA256 ditandatangani dengan kunci turunan dari `APP_KEY` (`hash_hmac('sha256', 'presensi_akhir_slot_key', config('app.key'))`), memuat ID kegiatan dan slot waktu 30 detik.
      - Verifikasi token menerima slot saat ini dan slot sebelumnya (toleransi rotasi 30 detik).
    - Panel "Presensi Akhir" di `/admin/absensi`:
      - Indikator status sesi (Belum Dibuka, Sesi Dibuka, Sesi Ditutup).
      - Tombol "Buka Presensi Akhir" dengan modal waktu mulai & selesai (bawaan: 15 menit sebelum selesai s.d. 60 menit sesudahnya dalam input `datetime-local` WIB).
      - Tombol "Tutup Sekarang" dan "Perpanjang" durasi menit.
      - Tombol "Tandai presensi akhir manual" per peserta di tabel kehadiran dengan modal alasan wajib yang tercatat di audit log.
    - Halaman Layar Proyektor `resources/views/admin/attendances/proyektor.blade.php`:
      - Tampilan layar penuh gelap elegan untuk proyektor/layar aula.
      - QR SVG dinamis berotasi tiap 30 detik dengan progress bar dan angka hitung mundur.
      - Counter live kehadiran akhir: "Presensi akhir masuk n dari m peserta check-in".
      - Polling asinkron tiap 5 detik untuk memperbarui jumlah kehadiran secara langsung tanpa reload halaman.
      - Bebas dari kode angka manual (kode akses tidak digunakan).
    - Alur Peserta Memindai QR Proyektor:
      - Membuka `/kegiatan/{kegiatan}/presensi-akhir?token=...`.
      - Middleware `auth` mengalihkan ke login jika belum login dan kembali ke URL asal via redirect intended.
      - Validasi server: token HMAC sah & segar, sesi presensi akhir sedang dibuka, tiket peserta terkonfirmasi, dan SUDAH check-in fase 1 (bila belum: *"Check-in awal belum tercatat. Hubungi panitia."*).
      - Mengarahkan peserta ke langkah tanda tangan digital mandiri yang sudah ada.
  - **Implementasi Bagian 3: Keamanan dan Sesi**:
    - Mode Kios 4 Jam (`config/session.php` & `.env.example`):
      - Variabel baru: `SESSION_KIOSK_IDLE_TIMEOUT_MINUTES=240` (bawaan: 4 jam / 240 menit).
      - `App\Http\Middleware\EnforceSessionTimeout` mendeteksi rute pemindai (`admin/kegiatan/*/pindai-tiket*`) dan layar proyektor (`admin/kegiatan/*/presensi-proyektor*`), menerapkan batas inaktivitas 4 jam sebelum meminta login ulang; halaman lain tetap 15 menit.
    - Header `no-store, no-cache, must-revalidate, max-age=0` tetap berlaku pada seluruh halaman baru melalui `PreventBackHistoryCache`.
    - Token QR HMAC dan data pribadi (NIM lengkap, nomor telepon, tanda tangan) tidak dicatat ke log aplikasi.
    - Tidak ada perubahan pada logika penerbitan sertifikat. Mesin kelayakan sertifikat membaca integritas data check-in fase 1 dan presensi fase 2 ini.
  - **Pengujian & Verifikasi**:
    - Dibuat suite pengujian lengkap `Tests\Feature\PresensiScannerAndSessionTest` (10 tes, 43 assertions) yang memverifikasi:
      1. Tiket valid diterima dan pindai ulang menghasilkan `SUDAH_CHECK_IN`.
      2. Tiket kegiatan lain ditolak dengan pesan jelas.
      3. Tiket belum lunas / gagal ditolak.
      4. Pindai di luar jendela waktu check-in ditolak.
      5. URL dan kode mentah sama-sama diterima dan diekstraksi kodenya.
      6. Bukan super_admin mendapatkan HTTP 403 Forbidden; tamu dialihkan ke login.
      7. Check-in manual wajib alasan dan tercatat di `audit_logs`; pembatalan check-in wajib alasan dan tercatat di `audit_logs`.
      8. Panel presensi akhir membuka dan menutup sesi, verifikasi token HMAC slot saat ini dan sebelumnya.
      9. Peserta ditolak jika belum check-in fase 1 (*"Check-in awal belum tercatat. Hubungi panitia."*).
      10. Presensi akhir manual oleh admin wajib alasan dan idempoten.
    - Suite `Tests\Feature\SessionTimeoutAndCachePreventionTest` diperluas dengan pengujian mode kios 4 jam (tetap aktif pada menit ke-60, kedaluwarsa setelah menit ke-240).
    - Seluruh rangkaian pengujian sistem `php artisan test` (**381 tes, 3168 assertions**) lulus sempurna 100%.
    - `npm run build` berhasil tanpa galat dengan paket `jsqr@1.4.0`.
    - Format kode rapi sesuai standar via `php vendor/bin/pint`.
    - `git diff --stat composer.json composer.lock` kosong (tidak ada dependensi composer baru).

- [x] **Bagian 12: Penguncian Presensi Akhir Lewat Pindai QR Layar & Aturan Skrip Sementara**
  - **Pembuktian Dugaan Masalah**:
    - Terbukti bahwa rute presensi mandiri lama (`PresensiController@showSelfAttendance` dan `@submitSelfAttendance` pada `/kegiatan/{kegiatan}/presensi` dan `/presensi/{kegiatan}`) sebelumnya dapat dibuka dan dikirim langsung oleh peserta tanpa melewati pendaratan QR layar proyektor.
    - Pembuktian diawali dengan pengujian yang gagal (`Expected 302 but received 200`) di `PresensiAkhirQrLockTest`.
  - **Penguncian Akses Sisi Server**:
    - Tampilan dan pengiriman presensi akhir fase 2 kini diwajibkan memenuhi 3 syarat:
      1. Sesi presensi akhir dibuka dan dalam jendela aktif (`isPresensiAkhirOpen()`).
      2. Peserta terdaftar dengan tiket sah (lunas/gratis) dan telah check-in fase 1 (`checked_in`).
      3. Ada izin sisi server di sesi per kegiatan (`presensi_akhir_unlocked_{id}`) yang dibuat secara eksklusif oleh pendaratan QR layar dengan token HMAC valid (`PresensiScannerController@scanPresensiAkhir`). Izin memiliki masa berlaku 15 menit dan langsung dihapus dari sesi setelah pengiriman presensi tersimpan.
    - Tanpa izin yang valid, permintaan dialihkan ke `dashboard.kegiatan.saya` dengan flash error: *"Pindai QR di layar acara untuk presensi akhir."*.
    - Presensi akhir manual oleh super admin tidak terpengaruh dan tetap berfungsi penuh.
    - Pada modal E-Tiket halaman Kegiatan Saya (`KegiatanSaya.jsx`), ditambahkan banner petunjuk: *"Presensi Akhir Acara: Pindai QR di layar acara untuk presensi akhir dan penerbitan e-sertifikat resmi."*.
    - **Catatan Pembuatan Sertifikat Otomatis**: Logika penerbitan e-sertifikat resmi masih terpicu secara otomatis oleh rute presensi akhir ini (tahap berikutnya untuk modularisasi/pemisahan alur).
  - **Aturan Skrip Sementara di CLAUDE.md**:
    - Menambahkan klausul aturan data di `CLAUDE.md`: skrip sementara seperti `php -r`, `tinker`, dan perintah artisan ad hoc tidak boleh memakai factory atau menulis ke database pengembangan; verifikasi perilaku hanya lewat PHPUnit dengan SQLite in-memory.
  - **Pengujian & Verifikasi**:
    - Suite baru `Tests\Feature\PresensiAkhirQrLockTest` (7 tes, 41 assertions) memverifikasi: penolakan tanpa izin QR, keberhasilan via token QR valid, penolakan izin kedaluwarsa / beda kegiatan, penolakan pemakaian ulang (one-time use), penolakan sesi tertutup, penolakan jika belum check-in fase 1 dengan pesan yang sama seperti jalur QR, dan kelancaran presensi manual admin.
    - Tes lama yang menguji alur presensi mandiri (`TwoPhaseAttendanceAndCertificateTest`, `AlurKegiatanPesertaTest`, `TimezoneConsistencyTest`, `LandingPageCleanupAndAdminStatsTest`, `AdminPresensiAkhirTest`) disesuaikan agar menyimulasikan pendaratan QR yang sah.
    - Seluruh rangkaian tes PHPUnit lulus 100%.

- [x] **Bagian 13: Presensi Mandiri Dashboard Hanya untuk Kegiatan yang Diikuti**
  - **Temuan Jalur Presensi**:
    - Formulir legacy dashboard `/dashboard/absensi` (`DashboardController@absensi` dan `@storeAbsensi`) menulis ke tabel `kegiatan_presensi` dan memperbarui `kegiatan_registrations.status_kehadiran = 'attended'`. Jalur ini **tidak memicu pembuatan sertifikat otomatis** (sertifikat hanya dipicu oleh `PresensiController@submitSelfAttendance` pada alur presensi akhir ber-QR).
    - Dropdown agenda sebelumnya mengambil seluruh kegiatan berstatus Published/Ongoing tanpa memfilter kepemilikan registrasi user.
  - **Penguncian & Validasi Sisi Server**:
    - Dropdown `/dashboard/absensi` kini hanya memuat kegiatan yang diikuti oleh peserta yang login dengan status pembayaran terkonfirmasi (`isConfirmed()`, Free/Paid), tidak dibatalkan, status Published/Ongoing, dan belum pernah presensi.
    - Menampilkan *empty state* informatif jika tidak ada kegiatan yang memenuhi syarat: *"Belum ada kegiatan yang sedang membuka presensi untuk Anda"* lengkap dengan tombol tautan ke halaman Kegiatan Saya.
    - Pada `DashboardController@storeAbsensi`, input `kegiatan_id` dari klien tidak dipercaya: registrasi dicek dari user login. Jika kegiatan tidak diikuti (IDOR), registrasi belum lunas / dibatalkan, kegiatan berstatus draft/cancelled, atau peserta sudah pernah presensi (presensi ganda), server menolak dengan HTTP 403 / 422 dengan pesan bahasa Indonesia yang ramah.
    - Ditambahkan banner panduan di formulir absensi dashboard bahwa Presensi Akhir Acara wajib melalui pemindaian QR di layar acara.
  - **Pengujian & Verifikasi**:
    - Suite `Tests\Feature\AbsensiDashboardPesertaTest` (7 tes, 20 assertions) lulus 100%.

- [x] **Bagian 14: Pembatasan Kursi VIP, Panitia, dan Diblokir untuk Peserta**
  - **Fungsi Pemeriksa Bersama Sisi Server**:
    - Menambahkan method pembantu di model `KegiatanSeat`:
      - `isNonPeserta()`: mengembalikan true jika kursi berstatus Blocked, ber-tier Panitia, atau ber-tier VIP.
      - `isAvailableForParticipant()`: mengembalikan true hanya jika status Available dan bukan non-peserta (reguler).
      - `isAvailableForAdmin()`: mengembalikan true jika status Available dan bukan diblokir (VIP dan Panitia diizinkan untuk admin).
      - `pastikanBisaDipesan(KegiatanSeat $seat, bool $isAdmin = false, string $field = 'seat_id')`: fungsi sentral bersama di dalam transaksi / lock database yang melempar `ValidationException` (HTTP 422) dengan pesan netral bahasa Indonesia: *"Kursi ini tidak tersedia. Silakan pilih kursi lain."* tanpa membocorkan alasan status internalnya.
    - Seluruh jalur peserta memesan/memindahkan kursi (`KegiatanBookingController@checkout`, `@bookSeats`, `@reselectSeat`) kini menggunakan fungsi sentral ini.
  - **Pencegahan Kebocoran Data & UI Peserta**:
    - Pada `PetaKursiService@untukPeserta` dan `@statusRingkas`, kursi VIP, panitia, dan diblokir dipetakan ke status netral `'tidak_tersedia'`, dan properti `tier` diset `null` agar kategori/tier internal tidak bocor ke klien peserta.
    - Komponen UI `PetaKursi.jsx` dan `Show.jsx`: kursi `tidak_tersedia` dinonaktifkan (`disabled`, `aria-disabled="true"`, `tabIndex="-1"`, tidak bisa difokus atau diklik). Teks 'VIP' dihapus dari aria-label peserta.
    - Legenda denah peserta disesuaikan: hanya menampilkan 'Tersedia', 'Pilihan Anda', 'Kursi saya / delegasi', dan 'Tidak tersedia'.
  - **Jalur Admin Tetap Berfungsi**:
    - Admin (Super Admin) tetap dapat memesankan/menempatkan peserta di kursi VIP maupun Panitia melalui jalur booking admin (`bookSeats` dengan `$isAdmin = true`), dan peta admin (`untukAdmin`) tidak berubah sehingga status asli (`vip`, `panitia`, `blocked`) dan pemegang kursi tetap transparan bagi pengurus.
  - **Perilaku Registrasi Ber-kursi Eksisting & Kapasitas**:
    - Registrasi lama yang sudah memegang kursi VIP/panitia tidak diubah datanya.
    - Pada peta peserta, kursi VIP, panitia, dan diblokir berstatus `tidak_tersedia` sehingga tidak dihitung sebagai kursi yang `'tersedia'`.
  - **Pengujian & Verifikasi**:
    - Suite baru `Tests\Feature\SeatNonPesertaRestrictionTest` (8 tes, 53 assertions) memvalidasi seluruh skenario penolakan, netralitas pesan, pencegahan kebocoran payload, kapasitas denah peserta, penempatan oleh admin, dan proteksi balapan (race condition).
    - Seluruh rangkaian pengujian sistem `php artisan test` (**403 tes, 3295 assertions**) lulus 100%.

- [x] **Bagian 15: Tinjau Ulang Hasil Sebelumnya & Perbaikan Keamanan (Bagian 0)**
  - **Audit Entitas & Relasi (Anti-Halusinasi)**:
    - Dilakukan verifikasi kode menyeluruh via Grep pada seluruh repositori. Terbukti bahwa `KegiatanMahasiswaController`, tabel `kegiatan_pendaftaran`, dan relasi `kegiatanPendaftarans` **tidak pernah ada** di dalam codebase.
    - Entitas kanonikal yang sah dan terbukti ada di kode adalah: `DashboardController` & `KegiatanBookingController`, tabel `kegiatan_registrations`, dan relasi `Kegiatan::registrations()`.
  - **Penguatan Keamanan SimpleXlsxService**:
    - **Daftar Putih Entri**: Hanya membaca entri terdaftar (`[Content_Types].xml`, `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/worksheets/sheet*.xml`, `xl/sharedStrings.xml`) langsung di dalam memori tanpa pernah mengekstrak ke disk.
    - **Pencegahan Bom Zip**: Memeriksa jumlah entri (maksimal 100) serta total ukuran tak terkompresi per entri dan akumulasi total (maksimal 10 MB) *sebelum* berkas XML dibaca.
    - **Proteksi DOCTYPE & XXE**: Menolak berkas XML yang mengandung deklarasi `<!DOCTYPE` atau `<!ENTITY` dengan melempar `RuntimeException`.
    - **Dukungan Format Sel Excel Lengkap**: Mendukung sel teks bersama `t="s"` (sharedStrings teks biasa & rich text `<r><t>`), string sebaris `t="inlineStr"`, numerik (tanpa `t`), string hasil formula `t="str"`, dan sel kosong `<c r="..."/>`.
    - Unit test baru `Tests\Unit\SimpleXlsxServiceTest` (4 tes, 14 assertions) memvalidasi skenario di atas.
  - **Pencegahan Replay Attack pada TOTP (TotpService)**:
    - Menambahkan kolom `two_factor_last_used_step` (unsignedBigInteger, nullable) pada tabel `users` via migrasi `2026_10_25_000002_add_two_factor_last_used_step_to_users_table.php`.
    - `TotpService@verifyCode` kini memeriksa `lastUsedStep` dan menolak kode apa pun pada time step yang sama atau lebih lampau (`$step <= $lastUsedStep`), serta membandingkan token dengan `hash_equals`.
    - `TwoFactorController@confirm` dan `@verify` menyimpan `two_factor_last_used_step` saat kode berhasil diverifikasi sehingga kode tidak dapat dipakai dua kali dalam jendela waktu yang sama.
    - Uji unit `Tests\Unit\TotpServiceTest` dan fitur `Tests\Feature\TwoFactorAuthenticationTest` berhasil memvalidasi penolakan replay attack.
  - **Konsolidasi Lokasi Dokumentasi**:
    - Folder `docs/` di root repositori ditetapkan sebagai lokasi kanonik tunggal seluruh dokumentasi (`status-tugas.md`, `penyatuan-alur-lama.md`, `panduan-bahasa-dan-tampilan.md`, `api-unusa.md`).
    - Seluruh berkas duplikat dan usang di `laravel-app/docs/` telah dihapus bersih.
    - Aturan dokumentasi kanonik dicatat di `CLAUDE.md`.

- [x] **Bagian 16: Date Picker di Form Kegiatan (Bagian 1)**
  - **Standarisasi Input datetime-local Bawaan Browser**:
    - Seluruh isian tanggal-waktu di form tambah dan edit kegiatan (`tanggal_mulai`, `tanggal_selesai`, `registration_deadline`, `submission_deadline`, `booking_opens_at`, `booking_closes_at`) menggunakan `<input type="datetime-local">` native tanpa paket baru.
    - Konversi WIB-UTC yang konsisten dipertahankan: input pengguna dalam jam WIB dikonversi ke UTC oleh `App\Support\Wib::keUtc()` saat disimpan, dan data dari server dikirim dalam UTC lalu diformat ke nilai input WIB oleh `keInputWib()` di `waktuWib.js`.
  - **Aturan Validasi & Pesan Bahasa Indonesia Sisi Klien & Server**:
    - Waktu selesai harus strictly setelah waktu mulai (`tanggal_selesai` > `tanggal_mulai`). Bila tidak memenuhi, pesan galat: *"Waktu selesai harus setelah waktu mulai."*.
    - Batas pendaftaran tidak boleh melewati waktu mulai (`registration_deadline` <= `tanggal_mulai`). Bila lewat, pesan galat: *"Batas pendaftaran tidak boleh melewati waktu mulai kegiatan."*.
    - Jendela booking kursi konsisten dengan aturan `isSeatBookingOpen`: penutupan booking harus setelah pembukaan booking (`booking_closes_at` > `booking_opens_at`). Pesan galat: *"Waktu batas akhir booking kursi harus setelah waktu buka."*.
    - Pesan galat divalidasi langsung di klien (inline dan penahan submit form) serta ditegakkan ketat di server (`KegiatanController@store`, `@update`, dan `UpdateVenueConfigRequest`).
  - **Pengisian Otomatis Waktu Selesai (Mulai + 2 Jam)**:
    - Di sisi klien: saat pengguna mengisi atau mengubah `tanggal_mulai` pada form, jika `tanggal_selesai` masih kosong, sistem otomatis mengisinya dengan waktu `mulai + 2 jam` via fungsi deterministik `tambahDuaJamWib` di `waktuWib.js` (pengguna tetap leluasa mengubahnya).
    - Di sisi server: pada `KegiatanController@store`, jika `tanggal_selesai` dikirim kosong, sistem otomatis menyimpannya sebagai `tanggal_mulai + 2 jam` dalam UTC.
  - **Pengujian Fitur**:
    - Suite `Tests\Feature\TimezoneConsistencyTest` diperluas dengan 5 pengujian baru: auto-fill selesai +2 jam, penolakan selesai <= mulai, penolakan batas pendaftaran > mulai, validasi tanggal update, dan penolakan jendela booking tidak valid. Seluruh 15 tes lulus 100%.

## Jalur Presensi dan Pemicuan Sertifikat Otomatis
1. **Rute `/dashboard/absensi` (`DashboardController@storeAbsensi`)**:
   - Jalur presensi mandiri dashboard (bukti kehadiran/swafoto).
   - Menulis ke tabel: `kegiatan_presensi`, memperbarui status kehadiran di `kegiatan_registrations`.
   - **TIDAK** memicu pembuatan sertifikat otomatis.
2. **Rute `/kegiatan/{kegiatan}/presensi` dan `/presensi/{kegiatan}` (`PresensiController@submitSelfAttendance`)**:
   - Jalur presensi akhir fase 2 setelah sesi acara berakhir.
   - Menulis ke tabel: `kegiatan_presensi`, `kegiatan_registrations`, dan **YA** menerbitkan sertifikat di tabel `sertifikat`.
   - Jalur ini **SUDAH** dikunci rapat oleh gerbang izin QR layar acara (`presensi_akhir_unlocked_{id}`) dengan masa berlaku 15 menit dan sekali pakai (one-time use).
3. **Rute `/kegiatan/{kegiatan}/presensi-akhir` (`PresensiScannerController@scanPresensiAkhir`)**:
   - Pendaratan pemindaian QR proyektor layar acara.
   - Menulis ke sesi browser: `presensi_akhir_unlocked_{kegiatan_id}` selama 15 menit.
   - **TIDAK** memicu pembuatan sertifikat secara langsung.

- [x] **Bagian 17: Alur Persetujuan Kegiatan oleh Seluruh Super Admin (Bagian 2)**
  - **Siklus Status & Aturan Penerbitan**:
    - Status alur: `draft` &rarr; `diajukan` &rarr; `published` (`terbit`); penolakan dengan catatan wajib mengembalikan status ke `draft` dan menghapus seluruh persetujuan yang ada.
    - Setiap kegiatan menyimpan pembuatnya di kolom `created_by` (migrasi `2026_10_25_000003_add_created_by_and_approvals_to_kegiatan_table.php`). Pembuat otomatis dianggap setuju dan tidak dapat menyetujui kegiatannya sendiri.
    - Penyetuju wajib didefinisikan sebagai **SEMUA** pengguna ber-role `super_admin` yang aktif (`is_blocked = false`) selain pembuat, dihitung ulang secara dinamis saat pengecekan.
    - Kegiatan otomatis menjadi terbit (`published`) segera setelah seluruh penyetuju wajib memberikan suara setuju. Bila tidak ada Super Admin lain yang aktif di sistem, kegiatan tidak dapat diterbitkan dan UI memberikan penjelasan yang ramah dan jelas.
    - Mengedit kegiatan yang sedang berstatus `diajukan` otomatis membatalkan dan menghapus seluruh persetujuan yang telah masuk.
  - **Penguncian Field Kritis pada Kegiatan Terbit**:
    - Pada kegiatan yang berstatus terbit (`published` / `ongoing`), field-field kritis (jadwal waktu mulai & selesai, batas pendaftaran, batas karya, metode pelaksanaan, biaya, sasaran peserta, kuota, konfigurasi sertifikat, dan instruksi karya) dikunci rapat dan tidak dapat diubah secara langsung baik di form UI (`disabled`) maupun di validasi server dengan pesan galat jelas: *"Field kritis (jadwal, metode, biaya, kuota, atau aturan kehadiran & sertifikat) pada kegiatan yang sudah terbit telah dikunci dan tidak dapat diubah secara langsung."*.
    - Field non-kritis (deskripsi, daftar narasumber) tetap dapat diperbarui secara langsung.
    - Perubahan denah dan kapasitas venue tetap dilayani secara aman lewat alur `venue-config` yang sudah ada (dengan mekanisme reset kursi dan notifikasi pilih ulang).
    - **Catatan**: Fitur "ajukan perubahan" untuk kegiatan terbit belum ada.
  - **Penyimpanan Suara Persetujuan & Audit Log**:
    - Tabel `kegiatan_persetujuan` menyimpan keputusan (`setuju` / `tolak`), catatan penolakan/persetujuan, waktu keputusan, dan sidik jari versi data kegiatan yang disetujui (`data_version`).
    - Aturan satu suara per pengguna ditegakkan dengan indeks unik `['kegiatan_id', 'user_id']`.
    - Setiap pengajuan, persetujuan, dan penolakan tercatat lengkap di `audit_logs` bersama alamat IP pengguna (`request()->ip()`).
  - **Antarmuka Pengguna (UI)**:
    - Di halaman detail kegiatan admin (`resources/js/Pages/Admin/Kegiatan/Show.jsx`): disematkan panel *"Alur Persetujuan Kegiatan"* yang menampilkan pembuat, daftar seluruh penyetuju wajib beserta badge status keputusannya (Menunggu, Disetujui, Ditolak), tombol "Ajukan Persetujuan" untuk pembuat, serta tombol "Setujui Kegiatan" dan "Tolak Kegiatan" (dengan modal input catatan penolakan wajib) untuk penyetuju wajib lain.
    - Di halaman kelola kegiatan (`resources/js/Pages/Admin/Kegiatan/Index.jsx`): banner peringatan interaktif *"Terdapat X kegiatan yang sedang menunggu persetujuan Anda sebagai Super Admin"* ditampilkan di atas tabel dengan tautan filter langsung.
  - **Email Notifikasi Antrean**:
    - Mailable `App\Mail\KegiatanDiajukanMail` mengimplementasikan `Illuminate\Contracts\Queue\ShouldQueue` untuk mengirimkan pemberitahuan ke seluruh penyetuju wajib segera setelah kegiatan diajukan, menggunakan Blade template bahasa Indonesia `resources/views/emails/kegiatan_diajukan.blade.php`.
  - **Pengujian & Integritas**:
    - Suite pengujian baru `Tests\Feature\KegiatanApprovalTest` (10 tes, 57 assertions) lulus 100% mencakup: skenario 1, 2, dan 3 super admin, pencegahan self-approval oleh pembuat, penolakan dengan catatan wajib, reset persetujuan saat edit, penyisihan akun diblokir, proteksi draft/diajukan dari publik, serta penguncian field kritis pada kegiatan terbit.
    - Seluruh pengujian sistem Feature suite (**411 tes, 3334 assertions**) lulus tanpa ada kegagalan.

- [x] **Bagian 18: Pengamanan Pengiriman Email & Klien API UNUSA (Bagian 3)**
  - **Pengamanan Pengiriman Email**:
    - **Antrean & Rate Limiting**: Seluruh email dikirim melalui antrean (`SendProtectedEmailJob` atau mailable berantrean) dengan pembatas laju standar 20 email per menit (`EMAIL_RATE_LIMIT_PER_MINUTE` di `config/unusa.php`). Bila laju terlampaui di worker antrean, job dilepas kembali (`release`) secara otomatis. Sistem kompatibel penuh dan tetap beroperasi normal pada `QUEUE_CONNECTION=sync` untuk pengujian dan lokal.
    - **Percobaan Ulang Bertahap (Backoff)**: Menggunakan percobaan maksimal 3 kali (`tries = 3`) dengan jeda bertahap 1 menit, 5 menit, dan 15 menit (`[60, 300, 900]` detik).
    - **Pencatatan Kegagalan Akhir Bersih PII**: Method `failed()` mencatat galat teknis tanpa memuat alamat email, nama, token, atau data pribadi apa pun ke log aplikasi.
    - **Kuota Harian & Kategori Email**: Kuota harian bawaan 400 email (`EMAIL_DAILY_QUOTA`). Email dibedakan antara:
      - *Kritis*: `ActivationLinkMail`, `ResetKataSandiMail`, `TicketIssuedMail`, dan `SeatReselectionMail`.
      - *Massal*: `KegiatanUpdatedMail`, `KegiatanDiajukanMail`.
      - Peringatan kuota mendekati $\ge 80\%$ (320 email) muncul di perintah `semadiksi:preflight` dan banner peringatan di dashboard Super Administrator.
      - Bila kuota habis, email massal otomatis ditunda ke hari berikutnya (pukul 00:01 WIB), sedangkan email kritis tetap diizinkan terkirim.
  - **Pengamanan Klien API UNUSA**:
    - **Batas Laju Sisi Klien**: Membatasi frekuensi pemanggilan ke gateway UNUSA (standar 60 request/menit, `UNUSA_API_RATE_LIMIT_PER_MINUTE`).
    - **Penanganan HTTP 429 & Retry-After**: Klien membaca header `Retry-After` saat menerima respons 429 dan mengunci panggilan selama durasi tersebut untuk mencegah pemblokiran gateway.
    - **Pemutus Sirkuit (Circuit Breaker)**: Aktif setelah 3 kali kegagalan beruntun (`UNUSA_API_CB_THRESHOLD`) dengan waktu cooldown 5 menit (`UNUSA_API_CB_COOLDOWN_SECONDS` = 300 detik). Selama sirkuit terbuka, panggilan API dialihkan seketika ke data CSV lokal (*graceful fallback*) tanpa menampilkan galat teknis ke pengguna.
    - **Cache Singkat Pencarian NIM**: Hasil pencarian NIM di-cache selama 5 menit (`UNUSA_API_CACHE_TTL_SECONDS` = 300 detik) menggunakan kunci hash `sha256` untuk mencegah kebocoran NIM. Log sistem tidak mencatat data pribadi mahasiswa.
  - **Preflight & Dokumentasi**:
    - Perintah `php artisan semadiksi:preflight` diperluas dengan baris status: `EMAIL_RATE_LIMIT`, `EMAIL_DAILY_QUOTA` (beserta persentase & status WARN jika $\ge 80\%$), `EMAIL_RETRY_BACKOFF`, dan `UNUSA_API_PROTECTION` (rate limit, circuit breaker, cache TTL).
    - Dibuat panduan teknis `docs/pengamanan-pengiriman.md` (1 halaman) yang merangkum seluruh arsitektur dan variabel pengamanan transmisi.
  - **Pengujian Fitur**:
    - Suite `Tests\Feature\PengamananPengirimanTest` (11 tes, 42 assertions) lulus 100%: rate limit antrean, backoff bertahap, kuota harian menunda email massal tetapi tetap mengirim email kritis, logging tanpa PII, antrean palsu (`Queue::fake()`), `sync` driver, banner dashboard & preflight 80%, rate limit klien API UNUSA, penanganan 429 Retry-After, circuit breaker & fallback CSV, serta cache singkat NIM.
    - Seluruh pengujian sistem Feature suite (**422 tes, 3376 assertions**) dan Unit suite (**13 tes, 68 assertions**) lulus 100%.

- [x] **Bagian 19: Perbaikan Tampilan dan Perilaku Pemilih Tanggal-Waktu pada Form Admin**
  - **Penyatuan Komponen**: Dibuat komponen bersama tunggal `resources/js/Components/Form/InputWaktuWib.jsx` yang membungkus input datetime-local standar HTML5 dengan `step="60"` sehingga detik tidak pernah tampil (`--:--` bukan `--:--:--`).
  - **Normalisasi Nilai**: Nilai input selalu dinormalisasi ke format `YYYY-MM-DDTHH:mm` tanpa detik (`normalisasiWaktuWib`), serta fungsi `keInputWib()` dan `tambahDuaJamWib()` di `lib/waktuWib.js` dinormalisasi tanpa detik tanpa mengubah konversi UTC &harr; WIB.
  - **Interaksi Picker & Tema Gelap**:
    - Klik atau fokus pada kolom memicu `showPicker()` native di dalam blok `try/catch` aman (diabaikan bila browser tidak mendukung).
    - Tema gelap (`color-scheme: dark`) diterapkan sehingga ikon kalender bawaan browser kontras dan terlihat jelas di atas latar gelap.
    - Indikator kalender diberi kontras tinggi, efek hover, kursor pointer, dan ukuran memadai untuk perangkat seluler.
  - **Aksesibilitas & Fitur Form**:
    - Label "WIB" dipertahankan jelas.
    - Teks bantu ringkas *"Klik kolom atau ikon kalender untuk memilih"* disematkan di bawah kolom.
    - Tombol kecil *"Kosongkan"* otomatis muncul pada kolom opsional (seperti waktu selesai, batas pendaftaran, batas karya, dan batas booking) ketika nilai terisi untuk memudahkan reset nilai tanpa tombol keyboard backspace.
    - Atribut `aria-label`, `aria-invalid`, dan `aria-describedby` tersambung ke teks bantu dan pesan galat.
    - Mendukung `min` dan `max` dari props.
    - Format 12 atau 24 jam mengikuti locale dan pengaturan OS/browser pengguna (standar shadow DOM HTML5).
  - **Cakupan Penggantian**:
    - `resources/js/Pages/Admin/Kegiatan/Index.jsx`: waktu mulai, waktu selesai, batas pendaftaran kegiatan, dan tenggat waktu pengumpulan karya lomba. Logika auto-isi selesai = mulai + 2 jam dan validasi tetap utuh.
    - `resources/js/Components/Admin/DenahBookingKegiatan.jsx`: jadwal buka booking kursi dan tutup booking kursi.
    - `resources/views/admin/attendances/index.blade.php`: modal buka sesi presensi akhir Blade diperbarui dengan `step="60"`, `color-scheme: dark`, `showPicker()`, dan teks bantuan yang setara.
  - **Pengujian**:
    - Seluruh tes round-trip WIB (`KegiatanEditFormRoundTripTest` 8 tes, `TimezoneConsistencyTest` 16 tes) lulus 100%.
    - Ditambahkan tes `test_payload_from_form_without_seconds_is_stored_as_correct_utc` yang memvalidasi bahwa payload form tanpa detik (`YYYY-MM-DDTHH:mm`) diterima dan disimpan sebagai UTC yang benar di database.
    - Total suite PHPUnit: **436 passed (3458 assertions)**. Kompilasi `npm run build` dan linter `vendor/bin/pint` sukses.

- [x] **Panduan Uji Manual**: Panduan pengujian manual langkah demi langkah yang terbukti dari kode untuk pemilik proyek tersedia di [`docs/panduan-uji-manual.md`](docs/panduan-uji-manual.md).
- [x] **Penerapan Anti-Slop Skills & Desain Otentik**: Mengintegrasikan sistem skill `anti-slop` (core, UI, copywriting, human a11y, mobile layout, code comments) dari `miqdadbadjuber/anti-slop` ke `.agents/skills/`, membuat panduan arah visual di [`DESIGN.md`](DESIGN.md), membersihkan elemen AI slop pada portal (menghilangkan kartu hero statis dekoratif palsu dan menjadikannya menu layanan interaktif fungsional, menghapus placeholder stock photo Unsplash pada kartu kegiatan, menghapus blur blob `.organic-blob` di CSS, merapikan navbar branding), serta memastikan 436 tes PHPUnit dan build frontend `npm run build` lulus 100%.

- [x] **Bagian 20: Alur Persetujuan Transparan & Mekanisme Kegiatan Daring/Hybrid**
  - **Temuan Bagian 0 (Investigasi & Pembuktian Kode Eksisting)**:
    - **a. Alur Persetujuan (Approval Flow)**:
      - **Akar Masalah Tombol Tidak Terlihat & Status Draft**:
        1. Pada `resources/js/Pages/Admin/Kegiatan/Index.jsx` langkah 5 wizard, tombol submit baru sebelumnya bertuliskan `'Terbitkan Kegiatan'` padahal form mengirim `status: 'draft'`, membingungkan pengurus. Kini dipisahkan menjadi dua tombol jelas: *"Simpan sebagai Draft"* dan *"Simpan & Ajukan Persetujuan"*.
        2. Pada `KegiatanController@store`, parameter `aksi_submit` diproses tegas (`draft` -> Draft, `ajukan` -> Diajukan), dan pengajuan divalidasi memerlukan Super Admin aktif lain serta mewajibkan tautan rapat bila daring/hybrid.
        3. Pada `Index.jsx` daftar kegiatan, kolom status menyajikan lencana status informatif dengan rincian progres (*"Menunggu persetujuan x dari y"*, tooltip alasan penolakan), serta tombol aksi cepat *"Ajukan"* (untuk pembuat), *"Tolak"*, dan *"Setujui"* langsung di baris tabel untuk Super Admin yang berhak.
        4. Pada halaman detail `Show.jsx`, pembuat kegiatan kini dikirim sebagai objek profil lengkap (`id`, `name`, `email`), status alur diperjelas pada seluruh status (`draft`, `diajukan`, `published`), dan pembuat dapat menarik kembali pengajuan ke draft (*"Tarik Kembali ke Draft"*).
        5. Notifikasi dalam aplikasi: Kartu ringkasan interaktif *"Menunggu persetujuan Anda (n)"* disematkan di Ringkasan Utama Dasbor Admin (`admin.dashboard`) dan lencana counter disematkan di menu *"Kelola Kegiatan"* baik pada tata letak Blade maupun Inertia React.
        6. Pengiriman email antrean pengajuan dipusatkan melalui `SendProtectedEmailJob` dan `EmailProtectionService`.
    - **b. Mekanisme Kegiatan Daring & Hybrid**:
      - **Migrasi & Penyimpanan Terenkripsi**:
        - Migrasi `2026_10_26_000001_add_daring_and_approval_columns_to_kegiatan_and_presensi_tables.php` menambahkan kolom: `catatan_penolakan`, `platform_daring`, `tautan_rapat` (terenkripsi `encrypted`), `meeting_id`, `passcode_rapat` (terenkripsi `encrypted`), `join_button_mode` (otomatis/manual), `menit_buka_gabung`, `batas_tutup_gabung`, `is_gabung_manual_open`, `izin_tautan_presensi_akhir`, dan `wajib_tangkapan_layar` pada tabel `kegiatan`.
        - Tabel baru `kegiatan_gabung` (`kegiatan_id`, `registration_id`, `user_id`, `pertama_pada`, `terakhir_pada`, `jumlah`).
        - Kolom `tangkapan_layar_path` pada tabel `kegiatan_presensi`.
      - **Kerahasiaan & Keamanan Tautan Rapat**:
        - Tautan dan passcode rapat disimpan terenkripsi di database dan tidak pernah dibocorkan pada payload awal halaman publik, detail kegiatan, atau kartu Kegiatan Saya.
        - Validasi whitelist host rapat terpusat (`App\Rules\ValidMeetingUrl`): hanya mengizinkan domain sah Zoom (`*.zoom.us`, `zoom.us`), Google Meet (`meet.google.com`), dan Microsoft Teams (`teams.microsoft.com`, `teams.live.com`).
        - Perubahan tautan rapat dicatat pada `audit_logs` tanpa mengekspos nilai rahasia (hanya mencatat penanda diperbarui).
      - **Endpoint & Interaksi Gabung Daring**:
        - Endpoint aman `POST /kegiatan/{kegiatan}/gabung` (`KegiatanGabungController@gabung`):
          - Memvalidasi tiket terdaftar dan terkonfirmasi (Free/Paid), akun aktif (tidak diblokir), dan jendela akses buka (`isJoinWindowOpen()`).
          - Menolak akses dengan pesan informatif jika tombol belum dibuka atau kegiatan berstatus draft/cancelled.
          - Mencatat rekam jejak pada `kegiatan_gabung` (pertama_pada, terakhir_pada, increment jumlah klik) secara atomik.
          - Mengembalikan JSON kredensial rapat (`tautan_rapat`, `meeting_id`, `passcode`) untuk dibuka di tab baru atau modal rincian rapat.
        - Di Kegiatan Saya (`KegiatanSaya.jsx`) dan Detail Kegiatan (`Kegiatan/Show.jsx`): disematkan panel/kartu rapat daring dengan tombol interaktif *"Gabung Sekarang"*, penghitung mundur/jadwal buka WIB, serta modal detail kredensial rapat.
        - Pada kegiatan hybrid, klik gabung daring diakui sebagai check-in awal alternatif selain pemindaian QR tiket fisik di pintu aula.
      - **Presensi Akhir & Tangkapan Layar**:
        - Tautan presensi akhir daring ber-HMAC dapat diizinkan melalui sakelar `izin_tautan_presensi_akhir` dan disalin langsung dari halaman proyektor atau panel presensi admin.
        - Formulir presensi mandiri (`presensi.self-attendance`) mendukung pengunggahan bukti tangkapan layar rapat daring bila sakelar `wajib_tangkapan_layar` diaktifkan, disimpan privat di disk `local` (`private/presensi/screenshots`), dan hanya dapat diakses oleh Super Admin lewat route terotorisasi `GET /admin/presensi/{id}/screenshot`.
      - **Kontrol Akses Admin di Panel Presensi**:
        - Di halaman Presensi & Kehadiran (`admin.attendances`): tombol manual *"Buka Akses Gabung"* dan *"Tutup Akses Gabung"*, kartu statistik total bergabung daring, kolom riwayat gabung peserta, serta tautan unduh/lihat bukti layar bagi peserta daring.
  - **Pengujian & Verifikasi**:
    - Suite `Tests\Feature\AlurPersetujuanKegiatanTerlihatTest` (6 tes, 42 assertions) lulus 100%.
    - Suite `Tests\Feature\MekanismeKegiatanDaringDanHybridTest` (9 tes, 65 assertions) lulus 100%.
    - Seluruh rangkaian pengujian sistem `php artisan test` (**451 tes, 3579 assertions**) lulus 100% tanpa ada kegagalan.
    - Build aset Vite `npm run build` dan linter gaya kode `php vendor/bin/pint` lulus sempurna.

- [x] **Bagian 21: Perbaikan Login SSO Menolak Akun Terdaftar & Tombol Reset 2FA di Data Pengguna**
  - **BAGIAN 1. TEMUAN AKAR MASALAH (Ditulis Sebelum Mengubah Kode)**:
    - **Gejala & Bukti Log**:
      - Terdata 4 baris log serupa di `laravel-app/storage/logs/laravel.log` pada 26 Okt 2026 (03:32:02 UTC dan 04:45:27 UTC / sekitar 10:32 & 11:45 WIB):
        `local.WARNING: Keycloak SSO gagal: pengguna belum teraktivasi`
      - Mahasiswa dengan NIM `4330024012` telah dibuat oleh Admin lewat menu Data Pengguna (`/admin/pengguna`), autentikasi SSO UNUSA berhasil (OTP lolos di IdP), namun aplikasi SEMADIKSI menolak masuk dengan pesan *"Akun belum terdaftar/teraktivasi"*.
    - **Pemetaan SEMUA Jalur Callback SSO**:
      1. `KeycloakController@callback` baris 58-61:
         - **Kode alasan**: `state tidak cocok`
         - **Kondisi**: `$storedState === null || $storedState === '' || !hash_equals($storedState, $state)`
         - **Respon**: Abort HTTP 403 `Sesi autentikasi tidak valid atau telah kedaluwarsa.`
      2. `KeycloakController@callback` baris 72-89:
         - **Kode alasan**: `kode penukaran gagal`
         - **Kondisi**: Query param `code === ''` atau `$codeVerifier` hilang dari sesi, ATAU `$tokenData['access_token']` kosong/gagal diperoleh dari IdP.
         - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Otorisasi gagal. Silakan coba kembali."* atau *"Gagal memperoleh token akses dari server SSO UNUSA."*.
      3. `KeycloakController@callback` baris 91-99 & `KeycloakService@matchOrCreateUser` baris 211-215 / 235-240:
         - **Kode alasan**: `klaim wajib hilang`
         - **Kondisi**: Klaim `sub` kosong/hilang dari respon `userinfo`, ATAU pada login pertama kali klaim NIM (`preferred_username`) kosong setelah di-`trim`.
         - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Gagal membaca profil pengguna dari server SSO UNUSA."* / *"Klaim subjek IdP tidak valid."* / *"Akun belum terdaftar. Hubungi admin."*.
      4. `KeycloakService@matchOrCreateUser` baris 242-247:
         - **Kode alasan**: `NIM tidak ada di data master`
         - **Kondisi**: Login pertama kali (belum ada user dengan `keycloak_sub = $sub`), klaim NIM ada tetapi `$academicProvider->cariMahasiswaByNim($nim)` menghasilkan `null` atau `empty($mahasiswa['nim'])`.
         - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Akun belum terdaftar. Hubungi admin."*.
      5. `KeycloakService@matchOrCreateUser` baris 219-223 & 259-262:
         - **Kode alasan**: `akun diblokir`
         - **Kondisi**: User ditemukan (baik lewat `keycloak_sub` maupun pencarian NIM `is_unusa`), dan kolom `is_blocked == true`.
         - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Akun Anda dinonaktifkan oleh administrator. Hubungi admin SEMADIKSI."*.
      6. `KeycloakService@matchOrCreateUser` baris 254-257:
         - **Kode alasan**: `role tidak diizinkan SSO`
         - **Kondisi**: Akun ditemukan lewat NIM master, tetapi peran adalah staf: `$existing->isStaff() || in_array($existing->role, [SuperAdmin, VerifikatorBerkas, PenilaiKarya], true)`.
         - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Staf wajib masuk menggunakan kata sandi."*.
      7. `KeycloakService@matchOrCreateUser` (Dua Jalur Menghasilkan Kode Alasan `pengguna belum teraktivasi`):
         - **Jalur 7A (Pencocokan via `keycloak_sub`, baris 218-228)**:
           - **Kondisi**: `User::where('keycloak_sub', $sub)->first()` ditemukan, `is_blocked == false`, TETAPI `email_verified_at === null`.
           - **Kode log**: `Keycloak SSO gagal: pengguna belum teraktivasi`
           - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Akun belum teraktivasi. Hubungi admin SEMADIKSI."*.
         - **Jalur 7B (Pencocokan via NIM Master, baris 249-301)**:
           - **Kondisi**: Belum ada `keycloak_sub`, NIM ada di master akademik, `$existing = User::where('nim', $nim)->where('is_unusa', true)->first()` DITEMUKAN, `is_blocked == false`, bukan staf, `email_verified_at === null`, TETAPI `$existing->role !== UserRole::MahasiswaKipk`.
           - **Kode log**: `Keycloak SSO gagal: pengguna belum teraktivasi`
           - **Respon**: Redirect `route('login')` dengan flash error `sso`: *"Akun belum teraktivasi. Hubungi admin SEMADIKSI."*.
    - **Hasil Pengujian Kelima Hipotesis**:
      - **Hipotesis a (Peran Selain mahasiswa_kipk)**: **TERBUKTI**. Di `resources/js/lib/penggunaForm.js`, nilai bawaan form tambah pengguna adalah `role = 'user_umum'`. Bila Admin membuat akun mahasiswa dengan role `user_umum` (atau akun belum diubah status KIP-nya ke `mahasiswa_kipk`), akun disimpan dengan `role = UserRole::UserUmum`, `verification_status = 'Verified'`, namun `email_verified_at = null`. Saat login SSO, akun lolos dari filter staf (karena `isStaff()` bernilai false), masuk ke blok `email_verified_at === null`, namun karena perbaikan lama hanya mengecek `if ($existing->role === UserRole::MahasiswaKipk)`, blok aktivasi terlewati dan eksekusi jatuh langsung ke baris 299 melempar `pengguna belum teraktivasi`.
      - **Hipotesis b (Kondisi Akun Tidak Dicakup Aturan Aktivasi)**: **TERBUKTI**. Form Admin di `UserManagementController@store` (`MahasiswaKipkRequest` / `UserUmumRequest`) membuat akun dengan `password` ter-hash dan `verification_status = 'Verified'`, namun `email_verified_at` dibiarkan `null`. Model `User` tidak memiliki kolom `is_active` atau `status` (hanya `verification_status` dan `is_blocked`) serta tidak menggunakan `SoftDeletes`.
      - **Hipotesis c (Pencocokan NIM)**: **TERBUKTI SEBAGAI FAKTOR KONSISTENSI**. Belum ada normalisasi NIM satu pintu di sistem. Form Data Pengguna hanya melakukan `trim()`, master CSV melakukan regex `^[A-Za-z0-9.\-]+$`, dan SSO melakukan `trim((string)$claims['preferred_username'])`. Bila format NIM berbeda karakter/spasi atau `is_unusa` bernilai false, akun tidak cocok di query `$existing = User::where('nim', $nim)->where('is_unusa', true)->first()` dan jatuh ke blok pembuatan akun baru yang melempar Integrity Constraint Violation jika email bertabrakan.
      - **Hipotesis d (Sub Tertaut ke Akun Lama / Bentrok)**: **TERBUKTI**. Bila `keycloak_sub` mahasiswa pernah tertaut ke baris akun lain/lama yang belum terverifikasi (`email_verified_at = null`), SSO berhenti di langkah 1 (Jalur 7A) tanpa pernah memeriksa baris akun mahasiswa yang baru. Sistem sebelumnya juga menautkan `keycloak_sub` secara diam-diam tanpa mengecek apakah sub sudah dipakai akun lain.
      - **Hipotesis e (Urutan Pengecekan di Callback)**: **TERBUKTI KRUSIAL**. Pada langkah 1 (pencocokan `keycloak_sub` baris 218-231), pemeriksaan `if ($user->email_verified_at === null)` dilakukan SEBELUM blok aktivasi otomatis langkah 4. Akibatnya, jika akun tertunda sudah sempat memiliki `keycloak_sub` (misal login pertama gagal parsial sesudah `keycloak_sub` disimpan atau tertaut sebelum aktivasi), seluruh percobaan login berikutnya (termasuk 4 percobaan berturut-turut pada log 26 Okt) PASTI terantuk di langkah 1 dan tidak pernah mencapai blok aktivasi langkah 4!
    - **Mengapa Perbaikan Sebelumnya (`4a510f2`) Tidak Mencakup Kasus Ini?**:
      1. Perbaikan sebelumnya HANYA meletakkan logika aktivasi pada langkah 4 (pencocokan NIM) dan secara ketat hanya mengevaluasi `if ($existing->role === UserRole::MahasiswaKipk)`. Akun tertunda yang dibuat admin sebagai `user_umum` (atau belum diubah ke KIP-K) langsung jatuh ke `Log::warning('Keycloak SSO gagal: pengguna belum teraktivasi')`.
      2. Langkah 1 (pencocokan `keycloak_sub`) sama sekali TIDAK memiliki blok aktivasi otomatis untuk akun mahasiswa KIP-K tertunda yang sudah memiliki `keycloak_sub`.
      3. Belum ada penolakan tegas untuk role `user_umum` dan staf dengan pesan informatif, sehingga alur salah menganggap akun `user_umum` sebagai akun belum aktif daripada memberi tahu bahwa akun tersebut tidak terdaftar sebagai penerima KIP-K.
      4. Belum ada kode referensi kegagalan `SSO-XXXX` dan pencatatan log dengan NIM tersamar.

  - **BAGIAN 2. PERBAIKAN (Mulai Dari Tes Yang Gagal)**:
    - **Normalisasi NIM Satu Pintu (`App\Support\NormalisasiNim`)**:
      - Dibuat class helper `NormalisasiNim` dengan method `bersihkan(?string $nim): string`.
      - Menghapus invisible whitespace, control characters, karakter zero-width, trim, dan uppercase.
      - Dipakai konsisten pada:
        - Pencocokan SSO di `KeycloakService::evaluasiLoginSso`
        - Formulir Data Pengguna di `PenggunaRequest`, `MahasiswaKipkRequest`, `UserUmumRequest`
        - Formulir Pendaftaran di `PendaftaranController::lookupNim`
    - **Struktur Evaluasi Bersih (`App\Services\Auth\SsoEvaluasi`)**:
      - Dibuat DTO immutable `SsoEvaluasi` yang memuat hasil evaluasi SSO: status kelulusan, instance `User`, kode alasan, pesan ramah pengguna, kode referensi `SSO-XXXX`, log context tersamar (NIM tersamar via `App\Support\Masking`), dan flag `harus_diaktivasi`.
      - Dipakai bersama oleh callback `KeycloakService` dan perintah diagnosa CLI `semadiksi:diagnosa-sso`.
    - **Perbaikan Alur di `KeycloakService`**:
      - **Aktivasi Mahasiswa KIP-K Otomatis**:
        - Bekerja baik pada pencocokan via `keycloak_sub` maupun pencocokan via `NIM master`.
        - Menyetel `email_verified_at = now()`, `verification_status = 'Verified'`, menautkan `keycloak_sub = $sub`.
        - Mengganti kata sandi dengan hash acak (`Hash::make(Str::random(40))`) sehingga kata sandi lama tidak dapat digunakan lagi.
        - Membatalkan token aktivasi tertunda di `AktivasiMahasiswa`.
        - Menautkan `PenerimaKipk` bila belum tertaut.
        - Mencatat audit log aksi `sso_activation` tanpa data pribadi.
      - **Pencegahan Sub Bentrok & Akun Ganda**:
        - Bila `keycloak_sub` sudah tertaut ke akun lain dengan NIM berbeda, sistem menolak dan mengembalikan kode alasan `sub bentrok` dengan kode referensi `SSO-XXXX`.
        - Bila akun dengan NIM tersebut sudah ada tetapi memiliki `keycloak_sub` berbeda dari klaim SSO, sistem menolak dan mengembalikan kode alasan `sub bentrok` dengan kode referensi.
      - **Penolakan Staf dan Akun Non-KIP-K**:
        - Staf (`super_admin`, `verifikator_berkas`, `penilai_karya`, dll.) ditolak dengan pesan: *"Akun staf masuk lewat halaman login kata sandi."* (kode alasan: `role tidak diizinkan SSO`).
        - Akun `user_umum` / mahasiswa yang bukan penerima KIP-K ditolak dengan pesan: *"NIM Anda belum terdaftar sebagai penerima KIP-K, hubungi admin."* (kode alasan: `bukan penerima KIP-K`).
        - Akun diblokir ditolak dengan pesan: *"Akun Anda dinonaktifkan, hubungi admin."* (kode alasan: `akun diblokir`).
        - Kasus tidak terduga ditolak dengan pesan: *"Login SSO gagal. Sebutkan kode SSO-XXXX kepada admin."*.
      - **Logging Bersih & Masking Data**:
        - Seluruh log kegagalan SSO mencatat `warning` yang memuat kode alasan, kode referensi `SSO-XXXX`, waktu, dan NIM tersamar via `Masking::maskNim()`, tanpa membocorkan token, kode otorisasi, atau klaim lengkap.
    - **Pengujian & Verifikasi Fitur**:
      - Suite pengujian baru `Tests\Feature\SsoAktivasiAkunTerdaftarTest` (7 tes, 48 assertions) mereproduksi kasus nyata NIM 4330024012 (dibuat admin tertunda), akun mahasiswa tertunda dengan sub, penolakan akun user umum/bukan KIP-K, penolakan staf, penolakan akun diblokir, penolakan sub bentrok, dan uji normalisasi NIM satu pintu.
      - Suite pengujian `Tests\Feature\KeycloakSsoAndSecurityTest` (32 tes, 178 assertions) diperbarui agar selaras dengan kode alasan baru dan kebijakan pembatasan penerima KIP-K.
      - Suite `PendaftaranMahasiswaTest` (27 tes) dan `PenggunaFormPerRoleTest` (14 tes) tetap lulus 100%.

  - **BAGIAN 3. ALAT DIAGNOSA READ-ONLY (`semadiksi:diagnosa-sso`)**:
    - **Implementasi Command (`App\Console\Commands\DiagnosaSsoCommand`)**:
      - Signature: `semadiksi:diagnosa-sso {nim}`.
      - 100% read-only tanpa manipulasi database (`SELECT` only) dan tanpa panggilan HTTP eksternal.
      - Normalisasi NIM satu pintu via `NormalisasiNim::bersihkan`.
      - Menampilkan penyamaran data pribadi: NIM tersamar (`Masking::nim`), tanpa mencetak kata sandi (*hash*), token aktivasi, atau token sesi.
      - Memanfaatkan logika evaluasi tunggal `KeycloakService::evaluasiLoginSso` sehingga hasil diagnosa identik dengan callback SSO asli.
      - Menyajikan parameter lengkap: status keberadaan akun, peran, status aktif (blokir), verifikasi email, penautan `keycloak_sub`, keberadaan di master mahasiswa UNUSA, status penerima KIP-K, kode alasan evaluasi, pesan ramah ke pengguna, dan prediksi kelayakan login SSO.
    - **Pengujian & Verifikasi Fitur**:
      - Suite pengujian baru `Tests\Feature\DiagnosaSsoCommandTest` (7 tes, 24 assertions) lulus 100%:
        1. Kasus akun tertunda (terdeteksi `aktivasi via SSO` & pesan ramah).
        2. Kasus staf (terdeteksi `role tidak diizinkan SSO`).
        3. Kasus akun diblokir (terdeteksi `akun diblokir`).
        4. Kasus akun tidak ada tapi ada di master & penerima KIP-K (terdeteksi `aktivasi via SSO` untuk pembuatan akun baru).
        5. Kasus NIM tidak ada di master (terdeteksi `NIM tidak ada di data master`).
        6. Kasus NIM dengan spasi (terbukti dinormalisasi).
        7. Pencegahan kebocoran kata sandi dan remember token.
    - **Dokumentasi Manual**:
      - Panduan pengujian manual dan tabel parameter telah ditambahkan ke `docs/panduan-uji-manual.md` pada Seksi 8.

  - **BAGIAN 4. TOMBOL RESET 2FA DI DATA PENGGUNA**:
    - **Model `App\Models\User`**:
      - Menambahkan atribut ter-append `$appends = ['two_factor_enabled']` dengan accessor `getTwoFactorEnabledAttribute(): bool`.
      - Menjamin privasi data sensitif: `two_factor_secret`, `two_factor_recovery_codes`, dan `password` tetap terlindungi di `$hidden` sehingga tidak pernah bocor ke Inertia frontend.
    - **Backend Protection di `UserManagementController::resetTwoFactor`**:
      - Route: `POST /admin/pengguna/{id}/reset-2fa` (`admin.users.reset-2fa`).
      - Otorisasi: `Gate::authorize('update', $user)` (khusus `super_admin`).
      - Proteksi Akun Sendiri: Menolak aksi jika Super Admin mencoba mereset akunnya sendiri (`$user->id === auth()->id()`) dengan pesan error flash: *"Anda tidak dapat mereset 2FA untuk akun Anda sendiri."*.
      - Validasi: `reason` wajib minimal 5 karakter, maksimal 500 karakter dengan pesan galat bahasa Indonesia.
      - Menghapus rahasia 2FA (`two_factor_secret = null`, `two_factor_confirmed_at = null`, `two_factor_recovery_codes = null`).
      - Mencatat `audit_logs` aksi `reset_two_factor` beserta alasannya.
    - **Frontend di `resources/js/Pages/Admin/Pengguna/Index.jsx`**:
      - Tombol ikon `"Reset 2FA"` (`lock_reset`) pada baris tabel pengguna:
        - Hanya tampil bila pengguna yang login adalah Super Admin (`auth.user.is_super_admin`).
        - Hanya tampil bila akun target memiliki 2FA aktif (`u.two_factor_enabled`).
        - Tidak tampil untuk akun Super Admin itu sendiri (`auth.user.id !== u.id`).
      - Modal konfirmasi interaktif `"Reset 2FA: [Nama Pengguna]"`:
        - Teks penjelasan: *"Pengguna harus mengatur ulang 2FA saat login berikutnya. Masukkan alasan pengaturan ulang (minimal 5 karakter)."*
        - Kolom textarea alasan wajib dengan `minLength={5}` dan validasi realtime galat Indonesia (`formReset2Fa.errors.reason`).
        - Tombol Batal untuk menutup modal dan membersihkan input.
        - Status tombol memproses (`formReset2Fa.processing`, teks *"Memproses..."* dan disabled) agar tidak terkirim dua kali.
        - Notifikasi hasil sukses lewat mekanisme flash banner `AdminLayout`.
    - **Pengujian & Verifikasi Fitur**:
      - Suite `Tests\Feature\TwoFactorAuthenticationTest` diperluas (10 tes, 65 assertions) lulus 100%:
        1. Role non-super_admin (mahasiswa, verifikator, penilai) ditolak 403.
        2. Alasan kosong dan alasan < 5 karakter ditolak dengan pesan validasi Indonesia.
        3. Super admin dilarang mereset 2FA akunnya sendiri.
        4. Reset berhasil menghapus rahasia, stempel konfirmasi, kode pemulihan, dan mencatat audit log.
        5. Payload Inertia `admin.users` memuat boolean `two_factor_enabled` dan tidak memuat `two_factor_secret` maupun `two_factor_recovery_codes`.
    - **Dokumentasi Manual**:
      - Panduan uji manual di `docs/panduan-uji-manual.md` diperbarui mencakup alur tombol UI dan modal reset 2FA, serta status pada Matriks Rekapitulasi diubah menjadi **Tersedia di UI**.

- [x] **Bagian 22: Perapian Kebijakan SSO, Aktivasi Akun, Keamanan Reset 2FA, dan Audit .claude**
  - **BAGIAN A. PEMULIHAN KEBIJAKAN SSO LAMA UNTUK MAHASISWA NON-KIP-K**:
    - **Bukti Riwayat Git**:
      - Commit sebelum perubahan: `48d1b09^` yaitu commit `74e33ce8dccb350cdff8556ad8f69ae72fe06a2b` (short: `74e33ce`).
      - Pembuktian via `git show 74e33ce:laravel-app/app/Services/Auth/KeycloakService.php` dan `git show 74e33ce:laravel-app/tests/Feature/KeycloakSsoAndSecurityTest.php`.
    - **Perilaku Lama yang Tepat (Terbukti Dari Kode 74e33ce)**:
      - Bila NIM ada di data master mahasiswa UNUSA tetapi bukan penerima KIP-K:
        - Jika akun belum ada di tabel `users`: akun dibuat otomatis lewat SSO (identitas sudah dibuktikan IdP Keycloak).
        - Peran akun yang dibuat: `UserRole::UserUmum` (`role = 'user_umum'`), `kip_status = 'Umum'`, `is_unusa = true`, `jalur_pendaftaran = User::JALUR_UNUSA`.
        - Status verifikasi: `verification_status = 'Verified'`, `email_verified_at = now()`.
        - Penautan keycloak_sub: ditautkan langsung ke `$sub` (`keycloak_sub = $sub`).
        - Kata sandi: diisi hash acak (`Hash::make(Str::random(32))`).
        - Audit log: **TIDAK** mencatat audit log `sso_activation` (aksi `sso_activation` hanya diperuntukkan bagi aktivasi mahasiswa KIP-K). Log info aplikasi mencatat: `Keycloak SSO: buat akun user umum via SSO [NIM: {nimTersamar}]`.
        - Respon callback: Login sukses dan diarahkan ke `route('dashboard.index')`.
    - **Pemulihan pada KeycloakService**:
      - Logika evaluasi di `KeycloakService::evaluasiLoginSso` memulihkan pembuatan akun baru `UserUmum` bagi mahasiswa non-KIP-K (`kodeAlasan: 'buat akun baru via SSO'`, `pesan: 'Siap dibuat sebagai User Umum via SSO'`, `perluDibuat: true`).
      - Pada `KeycloakService::matchOrCreateUser`, akun non-KIP-K dibuat sebagai `UserRole::UserUmum` dengan `email_verified_at = now()` dan `keycloak_sub = $sub`.
      - Mempertahankan seluruh perbaikan lain: aktivasi mahasiswa KIP-K tertunda yang dibuat admin, normalisasi NIM satu pintu (`NormalisasiNim::bersihkan`), deteksi sub bentrok & akun ganda ber-kode referensi `SSO-XXXX`, logging tersamar (`Masking::nim`), dan urutan pengecekan aman.
    - **Pengembalian Ekspektasi Tes Lama di `KeycloakSsoAndSecurityTest`**:
      - `test_callback_sukses_membuat_mahasiswa_umum_bila_bukan_penerima_kipk`: menguji pendaftaran otomatis mahasiswa non-KIP-K ke peran `UserUmum` terverifikasi.
      - `test_role_tidak_bisa_naik_lewat_sso`: menguji bahwa klaim peran dari IdP diabaikan dan akun tetap dibuat sebagai `UserRole::UserUmum`.
      - `test_logging_alasan_kegagalan_sso_pengguna_belum_teraktivasi_tanpa_data_pribadi`: menguji pencatatan log alasan saat akun di users belum teraktivasi (`email_verified_at === null`).
    - **Penyelarasan Alat Diagnosa (`semadiksi:diagnosa-sso`)**:
      - Prediksi untuk NIM non-KIP-K yang ada di master dan belum memiliki akun diselaraskan menjadi: `Dapat Login (Akun Baru User Umum)`.
      - Diuji pada `DiagnosaSsoCommandTest::test_diagnosa_kasus_mahasiswa_non_kipk_dapat_login_sebagai_user_umum`.

  - **BAGIAN B. PERJELAS AKTIVASI AKUN user_umum DAN PERAN LAIN LEWAT SSO**:
    - **Jawaban Poin 1 (Terbukti dari Kode Baris demi Baris)**:
      - *Apakah perannya berubah?* **TIDAK**. Pada `KeycloakService.php` baris 519-526, akun yang sudah ada hanya diperbarui `keycloak_sub`-nya (`forceFill(['keycloak_sub' => $sub])->saveQuietly()`). Baris penetapan peran lama telah dibersihkan sehingga peran akun `user_umum` tidak pernah diubah menjadi `mahasiswa_kipk` lewat klaim SSO.
      - *Apakah email_verified_at diisi?*
        - Jika akun `user_umum` sudah terverifikasi: tetap utuh.
        - Jika akun `user_umum` belum terverifikasi (`email_verified_at === null`): login SSO **DITOLAK** di baris 270-276 dan baris 380-388 dengan kode alasan `pengguna belum teraktivasi` dan pesan *"Akun belum teraktivasi. Hubungi admin SEMADIKSI."*. Nilai `email_verified_at` **TIDAK** diisi.
      - *Apakah kata sandi ditimpa hash acak?* **TIDAK**. Penimpaan kata sandi (`Hash::make(Str::random(40))`) di baris 491-493 secara eksklusif hanya berjalan untuk akun `UserRole::MahasiswaKipk` yang tertunda (`$evaluasi->perluAktivasi`). Kata sandi akun `user_umum` utuh 100%.
      - *Apakah 2FA yang sudah aktif dinonaktifkan?* **TIDAK**. Kolom `two_factor_secret`, `two_factor_confirmed_at`, dan `two_factor_recovery_codes` sama sekali tidak dimodifikasi oleh SSO.
      - *Apakah keycloak_sub ditautkan?* **YA**, jika akun terverifikasi dan belum memiliki `keycloak_sub` (atau cocok), ditautkan secara aman tanpa menimpa data lain.
    - **Penegasan dan Pembuktian dengan Tes**:
      - (a) Peran akun tidak pernah berubah oleh SSO: Diuji di `SsoAktivasiAkunTerdaftarTest::test_peran_akun_user_umum_tidak_pernah_berubah_oleh_sso_meskipun_nim_ada_di_penerima_kipk`.
      - (b) Staf ditolak dan data tidak berubah sedikit pun: Diuji di `SsoAktivasiAkunTerdaftarTest::test_staf_ditolak_sso_dan_data_akun_tidak_berubah_sedikitpun` untuk `SuperAdmin`, `VerifikatorBerkas`, dan `PenilaiKarya`.
      - (c) Keputusan dan alasan aktivasi `user_umum`: Akun `user_umum` tertunda tidak diaktivasi otomatis lewat SSO karena peran umum tidak memiliki hak istimewa KIP-K. Jika admin keliru membuat akun mahasiswa KIP-K sebagai `user_umum`, admin harus mengoreksi perannya melalui menu Data Pengguna atau impor KIP-K.
    - **Peningkatan Diagnosa SSO (`semadiksi:diagnosa-sso`)**:
      - Menampilkan parameter `Peran Akun` (`$user?->role?->value ?? '-'`).
      - Menampilkan kalimat `Petunjuk` untuk kasus yang ditolak SSO:
        - `role tidak diizinkan SSO`: *"gunakan login kata sandi (atau ubah peran lewat Data Pengguna jika akun mahasiswa)"*.
        - `pengguna belum teraktivasi`: *"ubah peran lewat Data Pengguna jika mahasiswa KIP-K, atau hubungi admin"*.
        - `akun diblokir`: *"hubungi admin untuk membuka blokir akun di Data Pengguna"*.
        - `NIM tidak ada di data master`: *"pastikan NIM terdaftar di data master atau ubah peran lewat Data Pengguna"*.
      - Diuji di `DiagnosaSsoCommandTest::test_diagnosa_menampilkan_petunjuk_untuk_staf_dan_role_yang_ditolak_sso`.

  - **BAGIAN C. PEMERIKSAAN KEAMANAN TOMBOL RESET 2FA**:
    - **Bukti Kode**:
      - `User.php`: Properti `$hidden` menyembunyikan `password`, `remember_token`, `two_factor_secret`, dan `two_factor_recovery_codes`. Properti `$appends = ['two_factor_enabled']` menyediakan accessor `getTwoFactorEnabledAttribute(): bool` (`hasEnabledTwoFactor()`). Payload Inertia hanya membawa boolean `two_factor_enabled` dan bersih dari secret atau kode recovery.
      - `UserManagementController::resetTwoFactor`: Dilindungi `Gate::authorize('update', $user)` yang diverifikasi oleh `UserPolicy::update` (`$user->isSuperAdmin()`). Role non-super_admin mendapatkan HTTP 403 Forbidden.
      - Proteksi Akun Sendiri: `if ($user->id === auth()->id())` menolak tindakan dan mengembalikan error: *"Anda tidak dapat mereset 2FA untuk akun Anda sendiri."*. Di `Pengguna/Index.jsx`, tombol reset 2FA tidak dirender untuk `auth?.user?.id === u.id`.
    - **Pengujian Fitur**:
      - `TwoFactorAuthenticationTest::test_super_admin_can_reset_user_2fa_with_mandatory_reason` menguji 403 untuk `mahasiswa_kipk`, `user_umum`, `verifikator_berkas`, dan `penilai_karya`, penolakan reset akun sendiri, validasi alasan min 5 karakter, pembersihan secret/confirmed_at/recovery_codes, dan audit log.
      - `TwoFactorAuthenticationTest::test_inertia_user_list_payload_contains_two_factor_enabled_and_no_secrets` memvalidasi payload Inertia bebas dari secret dan recovery codes.

  - **BAGIAN D. PEMERIKSAAN COMMIT .claude (74e33ce)**:
    - **Daftar Berkas Terdeteksi**:
      - `git show --stat 74e33ce` & `git ls-files .claude`:
        - `.claude/skills/anti-hallucination.md` (16 baris, 763 bytes)
        - `.claude/skills/anti-slop-ui.md` (16 baris, 993 bytes)
    - **Hasil Audit**: Tidak ditemukan berkas lokal atau sensitif (tidak ada `settings.local.json`, kredensial, token, izin, atau riwayat). Folder hanya memuat berkas skill murni.
    - **Tindakan Pengamanan**: Pola `.claude/*.local.*` dan `.claude/settings.local.json` ditambahkan ke `.gitignore` di root repositori untuk mencegah kebocoran konfigurasi lokal Claude di masa mendatang.
    - **Catatan Git**: Riwayat commit `74e33ce` tidak ditulis ulang dan tidak ada force push. Sesuai instruksi, perubahan belum boleh di-push sebelum persetujuan pemilik proyek.






- [x] **Bagian 23: Tombol Edit Kegiatan Halaman Putih dan Tombol Setujui/Tolak di Samping Edit**
  - **Temuan akar masalah (ditulis sebelum kode diubah)**:
    - Gejala: `/admin/kegiatan`, klik ikon pensil, halaman putih kosong tanpa pesan galat. Ikon mata berfungsi.
    - Penyebab pasti: `laravel-app/resources/js/Pages/Admin/Kegiatan/Index.jsx` baris 522 memakai `<form onSubmit={handleSubmit}>`, padahal `handleSubmit` tidak didefinisikan di berkas itu (`grep handleSubmit` hanya menemukan baris 522; yang ada hanya `simpanDenganAksi`, baris 174). Modal dirender hanya saat `modalOpen === true`. Begitu `openEditModal` memanggil `setModalOpen(true)`, React mengevaluasi JSX modal dan melempar `ReferenceError: handleSubmit is not defined`. Tidak ada error boundary, sehingga seluruh pohon React dilepas dan layar putih.
    - Asal kesalahan: `git log -S"handleSubmit"` menunjuk `c86283b` (`git show c86283b` menghapus `const handleSubmit = async (e) => {` di baris 23 diff dan menggantinya dengan `simpanDenganAksi`), tetapi atribut `onSubmit={handleSubmit}` pada form tidak ikut diganti.
    - Dampak: bukan hanya Edit. Tombol "Tambah Kegiatan Baru" membuka modal yang sama, jadi kemungkinan besar putih juga. Kegiatan terbit lama (misalnya "anniversary semadiksi") tidak spesifik rusak: penyebabnya tidak bergantung pada data baris, sehingga semua baris terkena. `npm run build` tidak menangkapnya karena bundler tidak memeriksa identifier yang tidak dideklarasikan.
    - `kegiatanKeForm()` (`lib/kegiatanForm.js`) dan `keInputWib()`/`keDate()` (`lib/waktuWib.js`) aman terhadap `null`/`undefined` (semua properti memakai `??`, `||`, atau `Array.isArray`), jadi bukan penyebab tampilan putih.
  - **Cacat kedua yang ditemukan lewat pembacaan kode (kehilangan data)**:
    - `Kegiatan::$hidden` memuat `tautan_rapat`, `meeting_id`, `passcode_rapat`, sehingga `$item->toArray()` di `KegiatanController@index` tidak membawanya. `kegiatanKeForm()` lalu mengisi ketiganya dengan `''`. Saat form disimpan, `tautan_rapat: ''` dikonversi middleware menjadi `null`, lolos `nullable`, dan `kegiatanUpdatePayload()` menulis `null` ke kolom: tautan, meeting ID, dan passcode tersimpan terhapus tanpa disadari.
    - Perbaikan yang direncanakan: payload daftar membawa penanda boolean `tautan_rapat_tersimpan`, `meeting_id_tersimpan`, `passcode_rapat_tersimpan` (tanpa nilai rahasia); form menampilkan "tersimpan" dengan placeholder untuk mengganti; klien tidak mengirim isian rahasia yang kosong; server mengabaikan isian rahasia yang kosong pada update.
  - **Fakta soal commit c86283b (diminta pada tugas ini)**: `git show c86283b --stat` tidak memuat `MenuAdminPerRoleTest`, `KegiatanApprovalTest`, maupun `EventEngineAndBookingTest`. Di `Kegiatan.php` dan `KegiatanBookingController.php` commit itu tidak mengubah `hasSeatLayout` ataupun logika `is_seat_booking_active` (hanya menambah kolom daring/persetujuan; di controller, `is_seat_booking_active` hanya berpindah indentasi di dalam `Arr::only`). Rinciannya ada di laporan akhir.
  - **Perbaikan Edit (Bagian 2)**:
    - `Index.jsx`: `handleSubmit` didefinisikan (Enter pada langkah 1-4 pindah ke langkah berikutnya, pada langkah 5 menyimpan lewat `simpanDenganAksi('draft')`), jadi modal tambah dan edit terbuka lagi.
    - Rahasia rapat: `KegiatanController::barisDaftar()` mengirim `tautan_rapat_tersimpan`, `meeting_id_tersimpan`, `passcode_rapat_tersimpan` (boolean). Form menampilkan lencana "Tersimpan" dan placeholder "Kosongkan untuk mempertahankan, isi untuk mengganti". `kegiatanKePayload()` tidak mengirim isian rahasia yang kosong, dan `kegiatanUpdatePayload()` mengabaikan isian rahasia yang kosong. Mengosongkan passcode atau meeting ID tetap bisa lewat Pengaturan Daring di halaman detail (`updatePengaturanDaring`).
    - `Components/ErrorBoundary.jsx` dipasang di `app.jsx` di atas komponen halaman Inertia (galat yang dilempar halaman itu sendiri tidak tertangkap boundary di dalam `AdminLayout`). Menampilkan kartu "Halaman gagal dimuat", tombol Muat Ulang, tautan kembali, dan `console.error`.
    - Aturan lama tidak berubah: kolom kritis terkunci setelah terbit, penyetuju wajib, zona waktu WIB.
  - **Tombol persetujuan (Bagian 3)**:
    - `App\Support\AksiPersetujuan::untuk()` menjadi satu sumber `can_ajukan`, `can_tarik_draft`, `can_setujui`, `can_tolak`, `keputusan_saya`, `alasan_nonaktif`, dipakai daftar (`barisDaftar`) dan `approval` di halaman detail. Nama pembuat sudah ada sebagai `creator_name` di kedua payload, jadi tidak diduplikasi sebagai `nama_pembuat`.
    - `Components/Admin/AksiPersetujuanKegiatan.jsx` dipakai di sel Aksi (tepat setelah ikon pensil) dan di header `Admin/Kegiatan/Show.jsx` bersama tombol baru "Edit Kegiatan" (`/admin/kegiatan?edit={id}`; server mengirim baris itu sebagai `kegiatanEdit` karena belum tentu ada di halaman daftar). Tombol memakai `flex-wrap`.
    - Setelah memberi keputusan tombol Setujui/Tolak hilang dan diganti lencana. Sebelumnya `can_reject` di halaman detail tetap aktif setelah menyetujui; server tidak berubah dan masih menerima penolakan, hanya tombolnya yang disembunyikan sesuai spesifikasi.
  - **Tes**:
    - Baru: `KegiatanEditDanTombolPersetujuanTest` (14 tes: payload empat kasus, penanda tersimpan, simpan dengan rahasia kosong, ganti rahasia, `?edit=`, matriks tombol pembuat/penyetuju/yang sudah memutuskan/draft/terbit, non-super_admin, penolakan server untuk non-penyetuju, pembuat sendiri, status bukan diajukan, dan keputusan ganda) dan `tests/js/kegiatanForm.test.mjs` (7 tes `node --test tests/js`: `kegiatanKeForm` empat kasus, payload rahasia, dan pemeriksaan statis bahwa pengendali event di `Index.jsx` terdeklarasi; pemeriksaan terakhir gagal pada `handleSubmit` sebelum perbaikan).
    - Diubah: tiga asersi di `AlurPersetujuanKegiatanTerlihatTest` mengikuti nama kunci baru (`perlu_tindakan_anda` menjadi `can_setujui` dan `can_tolak`, `bisa_diajukan_oleh_anda` menjadi `can_ajukan`, `can_withdraw` menjadi `can_tarik_draft`). Maknanya sama.
    - Catatan perilaku nyata server: Setujui dua kali oleh orang yang sama tidak menambah suara (`updateOrCreate`) dan tidak menerbitkan kegiatan; server tidak menolak dengan galat, jadi tes memeriksa hasilnya (satu baris suara, status tetap diajukan).
    - Peserta (non-staf) yang membuka rute admin dialihkan (302), staf non-super_admin memperoleh 403; tes menerima keduanya.
  - **Uji manual di browser**: lihat laporan akhir tugas ini.

- [ ] **Bagian 24: TUGAS T2a - Kelayakan dan Penerbitan Sertifikat, Penandatangan, dan Halaman Verifikasi Dinamis**
  - **BAGIAN 0: Tutup Celah Pengamanan Email [SELESAI]**:
    - Mailable lama (`ResetKataSandiMail`, `TicketIssuedMail`, `SeatReselectionMail`) dialihkan ke satu pintu terpusat `EmailProtectionService::send()` / `kirim()` yang menyalurkan pengiriman via `SendProtectedEmailJob`.
    - Klasifikasi:
      - Kritis: `ActivationLinkMail`, `ResetKataSandiMail`, `TicketIssuedMail` (tetap terkirim saat kuota harian tercapai).
      - Massal: `KegiatanDiajukanMail`, `KegiatanUpdatedMail`, `SeatReselectionMail`, `SertifikatTerbitMail` (ditunda bila kuota terlampaui).
    - Menghapus pemanggilan duplikat `recordSent()` pada listener dan job.
    - Ditambahkan tes penjaga statis `test_penjaga_seluruh_pengiriman_email_di_app_lewat_layanan_terpusat()` di `PengamananPengirimanTest` yang memindai seluruh direktori `app/` dan gagal jika ada pemanggilan langsung `Mail::` di luar `SendProtectedEmailJob` dan `TesEmailCommand`.
    - Kompatibel penuh dengan `QUEUE_CONNECTION=sync`.
  - **BAGIAN 0B: Rapikan Form Edit Kegiatan [SELESAI]**:
    - Pada `Admin/Kegiatan/Index.jsx`, langkah 5 footer dikondisikan:
      - Mode edit (`editingItem` aktif): hanya menampilkan tombol tunggal *"Simpan Perubahan"* (memanggil `simpanDenganAksi('draft')`) disertai teks petunjuk kecil *"Untuk mengajukan persetujuan, gunakan tombol Ajukan di daftar kegiatan"*.
      - Mode tambah baru (`editingItem === null`): menampilkan dua tombol *"Simpan sebagai Draft"* dan *"Simpan & Ajukan Persetujuan"*.
    - Diverifikasi lewat tes SSR `tests/js/kegiatanModalButtons.test.mjs` menggunakan `renderToStaticMarkup` bawaan React dan asersi AST string. `node --test tests/js` 10/10 lulus.
  - **BAGIAN 1: Temuan Investigasi Kode Nyata (Sebelum Perubahan Domain Sertifikat)**:
    - **1. CertificateGeneratorService**:
      - Memeriksa apakah kegiatan memiliki template PDF (`$kegiatan->file_sertifikat_template_pdf`). Jika ada, menggunakan `stampPdfTemplateContent` via FPDI untuk mencetak teks penerima dan matriks QR vektor.
      - Jika tidak ada template, melakukan fallback ke DomPDF HTML view (`certificates.template` atau `certificates.dynamic`).
      - Menghasilkan SHA-256 berkas PDF fisik via `hash_file('sha256', $storagePath)` dan menyimpan hasilnya di kolom `document_hash`.
      - QR Code berisi URL publik: `url('/verifikasi?code='.$nomorSertifikat)`.
      - Nomor sertifikat: format `CERT-{KODE_KEGIATAN}-{TAHUN}-{RANDOM_OR_INC}`.
      - Berkas PDF disimpan ke disk `public` di direktori `certificates/{kegiatan_id}/{nomor_sertifikat}.pdf`.
    - **2. Pemicu Pembuatan Sertifikat Saat Ini (Semua Ditemukan via Grep)**:
      - `App\Http\Controllers\PresensiController::submitSelfAttendance()` (baris 362): otomatis menerbitkan sertifikat saat peserta mengisi presensi mandiri fase 2!
      - `App\Http\Controllers\Admin\PresensiScannerController::markPresensiAkhirManual()` (baris 389): otomatis menerbitkan sertifikat saat admin menandai presensi akhir manual peserta!
      - `App\Http\Controllers\Admin\DashboardController::storeCertificate()` (baris 308): formulir penerbitan manual di `/admin/sertifikat`.
      - `App\Console\Commands\MigrasiAlurLamaCommand::handle()`: penerbitan saat migrasi batch data lama.
    - **3. Halaman Admin `/admin/sertifikat`**:
      - Controller: `Admin\DashboardController::certificates()`, view: `admin.certificates.index`.
      - Masalah performa & privasi: form penerima memuat seluruh pengguna ber-role `mahasiswa_kipk` dan `user_umum` ke dalam `<datalist id="studentList">` (`$students = User::whereIn('role', ...)->get()`).
      - Penghapusan saat ini bersifat keras (`$certificate->delete()` dan file PDF langsung dihapus dari disk).
    - **4. Halaman Publik `/verifikasi`**:
      - Controller: `App\Http\Controllers\CertificateController::verify()`, view: `resources/views/verifikasi-sertifikat.blade.php`.
      - Sumber Data Penandatangan: **MOCK ARRAY STATIS DI DALAM CONTROLLER** (`$penandatangan = [...]` dengan Dr. Wahyu Utomo NIP `197508122002121001` dan Muhammad Fatih NIM `3130021045`). Belum membaca tabel database master penandatangan apa pun!
      - Makna Kolom *Certified* dan *Validity*: Di `verifikasi-sertifikat.blade.php` baris 226-231, ikon centang hijau kedua kolom tersebut **100% HARDCODED STATIS** (`<span ... title="Tersertifikasi Sah">check_circle</span>`) tanpa pemeriksaan status database maupun validitas hash berkas!
    - **5. Kegiatan Presensi & Status 'attended'**:
      - `AttendanceStatus` enum hanya mendefinisikan `Registered = 'registered'`, `CheckedIn = 'checked_in'`, `Absent = 'absent'`.
      - Di `DashboardController::storeAbsensi` (baris 659-663), kode sesungguhnya menulis `AttendanceStatus::CheckedIn->value`. Istilah `'attended'` di `docs/status-tugas.md` baris 277 adalah ketidakakuratan penulisan dokumentasi lama, bukan nilai enum yang hilang di kode.
    - **6. Tabel `kegiatan_gabung`**:
      - Dibuat pada migrasi `2026_10_26_000001_create_kegiatan_gabung_table.php`. Kolom: `id`, `kegiatan_id`, `registration_id` (unique foreignId), `user_id` (foreignId), `pertama_pada` (timestamp), `terakhir_pada` (timestamp), `jumlah` (integer counter).
      - Dicatat saat peserta mengklik tombol "Gabung" di `KegiatanGabungController::gabung`. Pada kegiatan hybrid, klik gabung diakui sebagai check-in awal alternatif.
    - **7. Juri & Penilaian Lomba**:
      - Pivot table `kegiatan_penilai` (`kegiatan_id`, `user_id`).
      - Data karya lomba di `kegiatan_submissions`: `nilai` (skor numerik 0-100), `catatan_juri`, `judged_by`, `judged_at`.
    - **8. Narasumber / Pembicara**:
      - Tersimpan pada kolom `kegiatan.narasumber` berformat JSONB array `[{nama, gelar, instansi, foto}]`.
    - **9. Halaman Presensi Admin Yang Ada**:
      - Rute `/admin/presensi` (`Admin\PresensiScannerController::index()`), view `admin.presensi.index`. Sudah memiliki rekap gabung daring, status check-in tiket fase 1, dan status presensi akhir fase 2.


