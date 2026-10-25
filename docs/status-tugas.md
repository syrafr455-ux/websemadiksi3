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

## Temuan Belum Ditangani / Catatan
- Pemasangan paket `phpoffice/phpspreadsheet` dan `openspout/openspout` via Composer dist zipball mengalami kegagalan izin penulisan file sementara pada Windows (`vendor/composer/tmp-*.zip: Permission denied`). Sesuai aturan tugas, berkas composer di-revert bersih dan sistem menggunakan cadangan OpenXML .xlsx engine bawaan PHP (`ZipArchive` + XML) dengan CSV cadangan yang tetap aktif.
- Catatan tugas: Pembuatan sertifikat masih terpicu otomatis oleh rute presensi akhir `PresensiController@submitSelfAttendance` (akan ditangani pada tahap berikutnya).



