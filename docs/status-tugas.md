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

## Temuan Belum Ditangani / Catatan
- Pemasangan paket `phpoffice/phpspreadsheet` dan `openspout/openspout` via Composer dist zipball mengalami kegagalan izin penulisan file sementara pada Windows (`vendor/composer/tmp-*.zip: Permission denied`). Sesuai aturan tugas, berkas composer di-revert bersih dan sistem menggunakan cadangan OpenXML .xlsx engine bawaan PHP (`ZipArchive` + XML) dengan CSV cadangan yang tetap aktif.
- Seluruh 6 bagian tugas telah selesai dikerjakan, diuji, dan diverifikasi end-to-end.
