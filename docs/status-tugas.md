# Status Pengerjaan Tugas SEMADIKSI

Status pelacakan pengerjaan bertahap:

- [x] **Bagian 1: Alur Kegiatan Peserta**
  - Bug (a) diselesaikan: Alur pendaftaran diarahkan ke kegiatan kanonikal dan pendaftaran tersimpan di `kegiatan_registrations` sehingga langsung muncul di "Kegiatan Saya & E-Tiket" beserta tiket dan nomor kursi.
  - Bug (b) diselesaikan: `DashboardController@kegiatan` dan `@index` kini membaca tabel `kegiatan` dengan filter status, sasaran peserta (role KIP-K verified, UNUSA internal, umum), dan pencarian.
  - E2E Test berhasil: `Tests\Feature\AlurKegiatanPesertaTest` memvalidasi pembuatan kegiatan oleh super admin, visibilitas di landing page dan dashboard peserta login sesuai sasaran, pendaftaran gratis berkursi, penampilan di Kegiatan Saya, check-in tiket fase 1, presensi akhir tanda tangan fase 2, penerbitan sertifikat, dan proteksi draft.
- [ ] **Bagian 2: Penyatuan Alur Lama**
  - Pemetaan tabel lama di `docs/penyatuan-alur-lama.md`.
  - Route `/absensi` dan `/verifikasi` dialihkan ke model/layanan baru.
  - Perintah `php artisan semadiksi:migrasi-alur-lama` (idempoten, dry-run bawaan, `--eksekusi`).
  - Pembersihan controller/view/route mati yang terbukti tidak terpakai.
- [ ] **Bagian 3: Halaman Admin Presensi Akhir**
  - Menu "Presensi & Kehadiran" di Admin untuk super_admin.
  - Tabel peserta: fase 1 check-in, fase 2 presensi akhir (WIB, pratinjau tanda tangan via policy khusus super_admin, status sertifikat).
  - Filter, pencarian, dan ekspor.
  - Tes akses per role & WIB.
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
- Bagian 1 selesai dan seluruh tes AlurKegiatanPesertaTest lulus. Lanjut ke Bagian 2 (Penyatuan Alur Lama).
