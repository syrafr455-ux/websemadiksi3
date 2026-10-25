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
- [ ] **Bagian 4: Penilaian Karya Format Excel (.xlsx)**
  - Ekspor/impor .xlsx (CSV sebagai cadangan).
  - Kolom: id karya, judul, nama peserta, nilai, catatan.
  - Validasi: ukuran 2 MB, max 2000 baris, rentang nilai numerik, cegah formula injection (`=`, `+`, `-`, `@`).
  - Otorisasi juri vs super admin.
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
- Bagian 1, 2, 3 selesai. Lanjut ke Bagian 4 (Penilaian Karya Format Excel .xlsx).
