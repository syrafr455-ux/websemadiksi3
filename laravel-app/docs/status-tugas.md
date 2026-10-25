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
- [ ] **Bagian 5: 2FA untuk Peserta Umum dan Juri**
  - Wajib untuk `user_umum` dan `penilai_karya` saat login password. SSO & role lain dilewati.
  - TOTP 6 digit, 30 detik, ±1 jendela. Migrasi kolom `users`.
  - Alur aktivasi, kode pemulihan (8 kode), throttle (5 per 10 menit), reset oleh super admin (audit log).
  - Tes menyeluruh.
- [ ] **Bagian 6: Sapu Bersih Error**
  - Tes rute bernama (controller, method, view ada).
  - Smoke test semua GET route tanpa parameter per role (bebas 500).
  - Perbaikan error JS null di `/admin/berkas` dan `/admin/submissions`, judul Blade putih di atas latar terang, React null safety.
  - Pembersihan route/halaman/kode mati.

## Temuan Belum Ditangani / Catatan
- Pemasangan paket `phpoffice/phpspreadsheet` dan `openspout/openspout` via Composer dist zipball mengalami kegagalan izin penulisan file sementara pada Windows (`vendor/composer/tmp-*.zip: Permission denied`). Sesuai aturan tugas, berkas composer di-revert bersih dan sistem menggunakan cadangan OpenXML .xlsx engine bawaan PHP (`ZipArchive` + XML) dengan CSV cadangan yang tetap aktif.
- Bagian 1, 2, 3, 4 selesai. Lanjut ke Bagian 5 (2FA untuk Peserta Umum dan Juri).
