# Panduan Uji Manual SEMADIKSI Portal
*(Berdasarkan Verifikasi Kode Nyata pada `laravel-app/`)*

Dokumen ini disusun untuk pemilik proyek dan penguji sistem sebagai panduan verifikasi fungsional fitur-fitur utama SEMADIKSI Portal. Seluruh nama menu, label tombol, tautan, parameter, hak akses (*role*), dan pesan galat (*error message*) di dalam dokumen ini **terbukti 100% dari kode sumber aplikasi** (tanpa rekayasa atau perkiraan).

---

## Ringkasan Cepat Akses & Peran Sistem (*Roles*)

| Peran (*UserRole*) | Area Akses Utama | URL Beranda | Keterangan |
| :--- | :--- | :--- | :--- |
| `super_admin` | Panel Administrator | `/admin` | Kontrol penuh, kelola kegiatan, pengguna, presensi, sertifikat |
| `penilai_karya` | Panel Administrator | `/admin/penilaian` | Juri lomba (akses terbatas pada penilaian karya lomba yang ditugaskan) |
| `verifikator_berkas` | Panel Administrator | `/admin/berkas` | Verifikator berkas & pelaporan mahasiswa KIP-K |
| `mahasiswa_kipk` | Dashboard Mahasiswa | `/dashboard` | Mahasiswa penerima beasiswa KIP-K UNUSA |
| `user_umum` | Dashboard Peserta | `/dashboard` | Peserta umum (mahasiswa UNUSA reguler / peserta luar kampus) |

---

## Menyiapkan Data Uji

Pengujian seluruh fitur dapat dilakukan dengan menyiapkan akun uji langsung melalui antarmuka (UI) **Data Pengguna** tanpa perlu menjalankan skrip *factory* atau manipulasi database manual.

### Prasyarat Awal
- Telah login sebagai **Super Administrator** awal (misal akun bawaan pengembang `admin@unusa.ac.id`) di URL `/admin/masuk`.

---

### Urutan Pembuatan Akun di UI Data Pengguna (`/admin/pengguna`)

#### 1. Membuat Akun Super Admin Kedua
Dibutuhkan untuk menguji alur persetujuan ganda (*four-eyes principle*).
1. Buka sidebar admin, klik menu **"Data Pengguna"** (`/admin/pengguna`).
2. Klik tombol **"Tambah Pengguna Baru"** di sudut kanan atas.
3. Pada modal formulir yang muncul, isi kolom berikut:
   - **Peran (Role)**: Pilih `"Super Administrator"`
   - **Nama Lengkap**: Ketik nama, misal `"Super Admin Dua"`
   - **Email**: Masukkan email unik, misal `"admin2@unusa.ac.id"`
   - **Kata Sandi**: Masukkan kata sandi (minimal 8 karakter kombinasi huruf dan angka) atau klik tombol **"Buat Otomatis"** lalu klik **"Salin"** pada kotak kuning.
4. Klik tombol **"Simpan Akun"**.
5. **Hasil**: Akun Super Admin kedua terdaftar dengan *badge* ungu `"Super Admin"` dan status `"Aktif"`.

#### 2. Membuat Akun Juri (*Penilai Karya*)
1. Masih di halaman `/admin/pengguna`, klik tombol **"Tambah Pengguna Baru"**.
2. Isi formulir:
   - **Peran (Role)**: Pilih `"Juri / Penilai Karya"`
   - **Nama Lengkap**: Ketik nama juri, misal `"Dr. Ir. Juri Lomba, M.Kom."`
   - **Email**: Masukkan email unik, misal `"juri@unusa.ac.id"`
   - **Nomor Handphone**: Masukkan nomor HP (opsional), misal `"081234567890"`
   - **Kata Sandi**: Masukkan kata sandi (min. 8 karakter) atau gunakan **"Buat Otomatis"**.
   - **Lomba yang Dijuri**: Centang kotak lomba yang hendak ditugaskan (bila lomba sudah dibuat). Jika lomba belum dibuat, lewati bagian ini; penugasan dapat dilakukan kemudian dengan mengklik ikon **"edit"** pada baris akun juri.
3. Klik tombol **"Simpan Akun"**.
4. **Hasil**: Akun juri terbuat dengan *badge* kuning `"Juri Penilai"`.

#### 3. Membuat Dua Akun Peserta Mahasiswa KIP-K
1. Masih di halaman `/admin/pengguna`, klik tombol **"Tambah Pengguna Baru"**.
2. Isi formulir untuk **Peserta 1**:
   - **Peran (Role)**: Pilih `"Mahasiswa KIP-K"`
   - **Nama Lengkap**: Masukkan nama, misal `"Ahmad Mahasiswa KIPK"`
   - **Email**: Masukkan email kampus, misal `"ahmad@student.unusa.ac.id"`
   - **NIM / Identitas**: Masukkan NIM unik UNUSA, misal `"2240020001"`
   - **Nomor Telepon**: Masukkan nomor telepon (opsional)
   - **Kata Sandi**: Minimal 8 karakter (atau klik **"Buat Otomatis"**).
3. Klik tombol **"Simpan Akun"**.
4. Di tabel data pengguna, cari baris akun Ahmad. Pada kolom **Detail Peran**, bila muncul tanda `"Prodi belum diatur"`, klik tombol **"Atur fakultas & prodi"**, pilih Fakultas dan Program Studi terkait, lalu klik **"Simpan"**.
5. Ulangi langkah di atas untuk **Peserta 2**:
   - **Nama Lengkap**: `"Budi Mahasiswa KIPK"`
   - **Email**: `"budi@student.unusa.ac.id"`
   - **NIM / Identitas**: `"2240020002"`
6. **Hasil**: Dua akun mahasiswa KIP-K siap digunakan untuk mendaftar kegiatan dan mengunggah karya.

---

## 1. Ekspor dan Impor Penilaian Karya Format Excel (.xlsx)

Fitur ini digunakan oleh dewan juri dan super admin untuk menilai karya peserta secara massal menggunakan lembar sebar Excel (.xlsx) tanpa bergantung pada aplikasi pihak ketiga.

### Otorisasi & Hak Akses
- **Role yang Berhak**: `super_admin` dan `penilai_karya` (dilindungi middleware `role:super_admin,penilai_karya` dan policy `nilaiKarya`).
- **Role Lain**: Ditolak dengan respon HTTP 403 (*Forbidden*).
- **Aturan Juri**: Juri **hanya** dapat mengakses lomba yang secara eksplisit telah ditugaskan kepadanya di tabel pivot `kegiatan_penilai`. Super Admin dapat mengakses seluruh lomba.

### Cara Penugasan Juri ke Kegiatan Lomba
1. Login sebagai `super_admin` di `/admin/masuk`.
2. Buka menu **"Data Pengguna"** (`/admin/pengguna`).
3. Temukan akun juri, lalu klik tombol ikon pensil **"Edit Pengguna"** (`edit`).
4. Pada form edit yang terbuka, gulir ke kotak **"Lomba yang Dijuri"**.
5. Centang kotak nama kegiatan lomba yang ditugaskan kepada juri tersebut.
6. Klik tombol **"Simpan Perubahan"**.

### Prasyarat Data Minimal
1. Terdapat kegiatan dengan kategori `"lomba"` (`kategori = 'lomba'`).
2. Opsi pengumpulan karya telah aktif (`is_submission_active = true`).
3. Minimal ada 1 peserta yang telah terdaftar sah dan sudah mengumpulkan karya (memiliki baris di `kegiatan_submissions`).
4. Akun juri telah ditugaskan ke lomba tersebut.

### Navigasi & Tampilan Tombol
- **URL Awal**: `/admin/penilaian`
- **Menu Sidebar**: Klik menu **"Penilaian Karya"** (ikon `rate_review`).
- **Dropdown Pemilihan**: Pilih nama lomba pada pilihan **"Pilih Kegiatan Kompetisi / Karya"**.
- **Label Tombol di UI**:
  1. Tombol hijau: **"Ekspor Excel (.xlsx)"** (ikon `download`) &rarr; mengunduh berkas `.xlsx`.
  2. Tombol abu-abu: **"Ekspor CSV"** (ikon `description`) &rarr; mengunduh berkas `.csv`.
  3. Tombol oranye: **"Impor Nilai (.xlsx / .csv)"** (ikon `upload_file`) &rarr; membuka modal impor massal.
  4. Tombol pada baris tabel: **"Beri Nilai"** (belum dinilai) atau **"Ubah Nilai"** (sudah dinilai) &rarr; membuka modal form penilaian manual.

### Langkah Uji Berurutan (Kasus Positif)
1. Login sebagai juri di `/admin/masuk` (lakukan verifikasi 2FA bila diminta).
2. Di sidebar admin, klik menu **"Penilaian Karya"** (`/admin/penilaian`).
3. Pada dropdown **"Pilih Kegiatan Kompetisi / Karya"**, pilih kegiatan lomba yang ditugaskan.
4. Klik tombol **"Ekspor Excel (.xlsx)"**.
5. Buka berkas yang terunduh (bernama `nilai_karya_<slug-lomba>_<timestamp>.xlsx`).
6. Periksa struktur kolom pada lembar kerja Excel:
   - Kolom A: `ID_KARYA` (jangan diubah)
   - Kolom B: `JUDUL_KARYA`
   - Kolom C: `NAMA_PESERTA`
   - Kolom D: `NILAI`
   - Kolom E: `CATATAN`
7. Isi nilai peserta pada kolom `NILAI` (contoh: `92.5`) dan ketik evaluasi pada kolom `CATATAN` (contoh: `Inovasi sangat baik, lanjutkan ke prototipe`).
8. Simpan berkas Excel tersebut.
9. Kembali ke halaman browser, klik tombol **"Impor Nilai (.xlsx / .csv)"**.
10. Pada modal yang terbuka, klik **"Pilih Berkas Nilai (.xlsx atau .csv) *"**, lalu pilih berkas yang baru disimpan.
11. Klik tombol **"Unggah & Perbarui Nilai"**.
12. **Hasil yang Diharapkan**:
    - Banner hijau sukses muncul: `"Berhasil memperbarui X nilai karya dari berkas."`
    - Kolom **Nilai Akhir** pada tabel menampilkan angka `92.5 / 100`.
    - Kolom **Status** berubah menjadi badge hijau `"Selesai Dinilai"`.

### Langkah Uji Kasus Negatif & Pesan Galat yang Terbukti di Kode

| No | Kasus Negatif | Prosedur Uji | Pesan Galat yang Diharapkan |
| :--- | :--- | :--- | :--- |
| 1 | **Berkas > 2 MB** | Unggah berkas Excel dengan ukuran lebih dari 2048 KB (2 MB). | Validasi form Laravel: `"The file field must not be greater than 2048 kilobytes."` |
| 2 | **Berkas > 2000 Baris** | Unggah berkas Excel dengan jumlah baris data melebihi 2000 baris. | Banner merah: `"Jumlah baris data melebihi batas maksimal 2000 baris."` |
| 3 | **Formula Injection Nilai** | Isi sel kolom `NILAI` diawali karakter formula trigger (`=SUM(1,2)`, `+CMD`, `@CALC`) yang bukan angka murni. | Transaksi di-rollback, pesan galat: `"Nilai untuk ID karya {id} tidak valid (terdeteksi formula atau format tidak didukung)."` *(Catatan: pada kolom CATATAN, karakter trigger dinetralkan otomatis tanpa error demi keamanan).* |
| 4 | **Nilai Di Luar Rentang** | Isi kolom `NILAI` dengan angka negatif (`-5`) atau lebih dari 100 (`120`). | Transaksi di-rollback, pesan galat: `"Nilai untuk ID karya {id} di luar rentang yang berlaku (harus 0 - 100)."` |
| 5 | **Nilai Non-Numerik** | Isi kolom `NILAI` dengan teks (misal: `"Sangat Bagus"`). | Transaksi di-rollback, pesan galat: `"Nilai untuk ID karya {id} harus berupa angka numerik."` |
| 6 | **Format Bukan XLSX/Header Rusak** | Unggah berkas teks/gambar yang diubah ekstensi, atau hapus baris header pertama. | Pesan galat: `"Header berkas harus memiliki kolom ID_KARYA dan NILAI."` atau `"Gagal membaca berkas Excel: ..."` |
| 7 | **ID Karya Milik Lomba Lain** | Ubah `ID_KARYA` menjadi nomor ID karya yang berasal dari lomba berbeda. | Pesan galat: `"ID karya {id} bukan bagian dari kegiatan ini atau tidak ditemukan."` |
| 8 | **Juri Tidak Ditugaskan** | Juri yang belum ditugaskan mencoba membuka `/admin/penilaian?kegiatan_id={id}` atau mengirim POST import langsung. | HTTP 403 Forbidden: `"403 \| This action is unauthorized."` |

---

## 2. Aktivasi 2FA & Reset 2FA

Autentikasi dua faktor berbasis waktu (TOTP) diwajibkan oleh sistem untuk mengamankan akun luar kampus dan dewan juri.

### Aturan Role Wajib 2FA
Berdasarkan `config('unusa.two_factor_roles')` dan method `User::requiresTwoFactor()`:
- Role yang **WAJIB 2FA**: `user_umum` dan `penilai_karya`.
- Role yang bebas 2FA: `super_admin` dan `mahasiswa_kipk` (menggunakan kredensial internal UNUSA).

---

### Alur Aktivasi 2FA (untuk `user_umum` & `penilai_karya`)

#### Prasyarat Data
- Akun `user_umum` atau `penilai_karya` baru dibuat dan belum pernah mengaktifkan 2FA (`two_factor_confirmed_at = null`).
- Gawai penguji memiliki aplikasi autentikator (Google Authenticator, Aegis, Authy, atau Bitwarden).

#### Langkah Uji Berurutan:
1. Akses halaman masuk:
   - Untuk juri: `/admin/masuk`
   - Untuk user umum: `/masuk`
2. Masukkan email dan kata sandi, lalu klik **"Masuk ke Panel Admin"** atau **"Masuk ke Portal"**.
3. Sistem secara otomatis mendeteksi kewajiban 2FA dan mengalihkan layar ke URL `/two-factor/setup` dengan judul **"Aktivasi Autentikasi Dua Faktor (2FA)"**.
4. Halaman menampilkan:
   - Gambar QR Code SVG ukuran 200x200 px.
   - Kotak **"Kunci Manual (Base32)"** berisi string rahasia (contoh: `JBSWY3DPEHPK3PXP`).
   - Tautan **"Buka langsung di aplikasi autentikator"** (`otpauth://...`).
   - Kolom input berlabel **"Masukkan 6 Digit Kode Konfirmasi"** (placeholder: `123456`).
5. Buka aplikasi autentikator di ponsel, pindai kode QR (atau masukkan kunci manual secara manual).
6. Masukkan 6 digit kode OTP yang dihasilkan aplikasi ke dalam kolom input.
7. Klik tombol berlabel persis: **"Konfirmasi & Aktifkan 2FA"** (ikon `verified_user`).
8. Halaman otomatis berpindah ke URL `/two-factor/recovery-codes` dengan judul **"Simpan Kode Pemulihan 2FA"**:
   - Muncul peringatan penting: *"PENTING: Simpan kode ini sekarang. Kode ini hanya ditampilkan sekali!"*
   - Ditampilkan 8 buah kode pemulihan unik (format: `XXXX-XXXX`).
9. Catat/salin kode tersebut, lalu klik tombol hijau: **"Saya Sudah Menyimpan Kode Pemulihan"** (ikon `check_circle`).
10. **Hasil yang Diharapkan**: Sesi autentikasi berhasil penuh; juri diarahkan ke `/admin/penilaian` dan peserta umum diarahkan ke `/dashboard`.

#### Kasus Negatif Aktivasi:
- **Kode Salah**: Masukkan kode selain 6 digit yang valid.
  - Pesan galat merah: `"Kode autentikasi tidak valid."`
- **Rate Limiting**: Salah memasukkan kode sebanyak 5 kali berturut-turut.
  - Pesan galat: `"Terlalu banyak percobaan. Silakan coba lagi dalam X menit."`

---

### Alur Login Berikutnya (*2FA Challenge*)
1. Login kembali menggunakan email dan kata sandi di `/admin/masuk` atau `/masuk`.
2. Pengguna langsung dihadapkan pada layar tantangan di URL `/two-factor/challenge` (**"Verifikasi Dua Langkah"**).
3. **Menggunakan Kode OTP**:
   - Ketik 6 digit angka dari aplikasi autentikator pada kolom **"Kode Autentikator (6 Digit)"**.
   - Klik tombol **"Verifikasi Masuk"** (ikon `arrow_forward`).
4. **Menggunakan Kode Pemulihan (*Emergency Recovery Code*)**:
   - Bila ponsel tertinggal/hilang, klik tautan tombol di bagian bawah: **"Gunakan kode pemulihan darurat"**.
   - Kolom berubah menjadi **"Kode Pemulihan (Recovery Code)"** (placeholder: `ABCD-EFGH`).
   - Masukkan salah satu dari 8 kode pemulihan yang telah disimpan.
   - Klik tombol **"Verifikasi Masuk"**.
   - Sistem berhasil login dan menghanguskan kode tersebut sehingga tersisa 7 kode cadangan.

---

### Reset 2FA oleh Super Admin
> [!NOTE]
> **STATUS FITUR PADA KODE: TERSEDIA PENUH DI UI & BACKEND**  
> Tombol ikon *"Reset 2FA"* (`lock_reset`) dan modal konfirmasi interaktif kini tersedia langsung pada tabel **Data Pengguna** (`/admin/pengguna`).

- **Hak Akses & Kondisi Tampil Tombol**:
  - Hanya muncul bila yang login adalah **Super Administrator** (`auth.user.is_super_admin === true`).
  - Hanya muncul pada baris pengguna yang **memiliki 2FA aktif** (`u.two_factor_enabled === true`).
  - **Tidak muncul** pada baris akun Super Admin itu sendiri (`auth.user.id !== u.id`), karena sistem melarang Super Admin mereset 2FA untuk akunnya sendiri.
- **Navigasi & Langkah Pengujian di UI**:
  1. Login sebagai `super_admin` di `/admin/masuk`.
  2. Buka menu **"Data Pengguna"** (`/admin/pengguna`).
  3. Cari baris pengguna yang memiliki 2FA aktif (misal juri atau user umum).
  4. Pada kolom **Aksi** di ujung kanan baris, klik tombol ikon gembok berputar berlatar kuning/amber: **"Reset 2FA"** (ikon `lock_reset`).
  5. Modal konfirmasi **"Reset 2FA: [Nama Pengguna]"** akan terbuka di tengah layar.
  6. Perhatikan teks penjelasan: *"Pengguna harus mengatur ulang 2FA saat login berikutnya. Masukkan alasan pengaturan ulang (minimal 5 karakter)."*
  7. **Uji Kasus Negatif**:
     - Bila alasan dikosongkan atau diisi kurang dari 5 karakter (misal `"abc"`), form dicegah kirim dan pesan galat *"Alasan reset minimal 5 karakter."* tampil di bawah kolom textarea.
  8. **Uji Kasus Positif**:
     - Masukkan alasan valid minimal 5 karakter, contoh: `"Pengguna kehilangan perangkat autentikator dan kode pemulihan"`.
     - Klik tombol **"Reset 2FA"**.
     - Tombol berubah status menjadi memproses (*disabled* dan animasi indikator putar) untuk mencegah pengiriman ganda.
     - Modal otomatis tertutup, dan banner notifikasi hijau muncul di atas halaman:  
       `"Autentikasi dua faktor (2FA) akun '[Nama]' berhasil diatur ulang."`
  9. **Verifikasi Hasil**:
     - Kunci rahasia 2FA, stempel konfirmasi, dan kode pemulihan pengguna tersebut telah dihapus di backend (`two_factor_secret = null`, `two_factor_confirmed_at = null`, `two_factor_recovery_codes = null`).
     - Tombol ikon *"Reset 2FA"* pada baris pengguna tersebut otomatis menghilang karena status `two_factor_enabled` telah menjadi `false`.
     - Tindakan tercatat rapi di tabel `audit_logs` dengan aksi `'reset_two_factor'` beserta alasan yang dimasukkan.
     - Saat pengguna tersebut login berikutnya, sistem akan mewajibkan pengguna untuk melakukan pengaturan 2FA dari awal.

---

## 3. Alur Persetujuan Kegiatan dengan Dua Super Admin

Untuk mencegah kecurangan (*four-eyes principle*), kegiatan yang dibuat oleh satu Super Admin **tidak dapat diterbitkan secara sepihak**. Penerbitan wajib disetujui oleh Super Admin aktif lainnya.

### Aturan Inti Kode:
1. Pembuat kegiatan dicatat di kolom `created_by`.
2. Pembuat kegiatan **dilarang menyetujui atau menolak kegiatannya sendiri** (`KegiatanController::setujui()` dan `tolak()` abort 403: *"Pembuat kegiatan tidak dapat menyetujui kegiatannya sendiri."*).
3. Hanya pembuat kegiatan yang berhak mengajukan persetujuan (`ajukan()` abort 403: *"Hanya pembuat kegiatan yang dapat mengajukan persetujuan."*).
4. Bila hanya ada 1 Super Admin di sistem, muncul peringatan: *"Kegiatan tidak dapat diterbitkan: Penerbitan kegiatan membutuhkan persetujuan seluruh Super Admin aktif selain pembuat. Saat ini tidak ada Super Admin lain yang aktif dalam sistem."*

---

### Langkah Uji Berurutan:

#### Fase A: Pembuatan Kegiatan (Status Draft) oleh Super Admin 1
1. Login sebagai **Super Admin 1** di `/admin/masuk`.
2. Klik menu **"Kelola Kegiatan"** (`/admin/kegiatan`).
3. Klik tombol **"Tambah Kegiatan Baru"**.
4. Isi data kegiatan lomba pada modal 5 langkah (lihat rincian langkah pada bagian 5).
5. Pada langkah 1, biarkan status default atau pilih status `"Draft"`.
6. Simpan kegiatan. Kegiatan tercatat dengan status badge abu-abu `"Draft"`.

#### Fase B: Mengajukan Persetujuan oleh Super Admin 1
1. Pada tabel kegiatan di `/admin/kegiatan`, klik tombol ikon mata **"Lihat Detail"** (`visibility`) pada kegiatan draft tadi &rarr; masuk ke URL `/admin/kegiatan/{id}`.
2. Di bagian atas layar, perhatikan panel **"Alur Persetujuan Kegiatan"**.
3. Di sudut kanan panel, klik tombol ungu: berlabel persis **"Ajukan Persetujuan"** (ikon `send`).
4. **Hasil**:
   - Status kegiatan berubah menjadi badge kuning `"Diajukan"` (`pending`).
   - Notifikasi email antrean dikirim ke seluruh Super Admin lain.
   - Muncul daftar: **"Daftar Penyetuju Wajib (1 Super Admin): Super Admin Dua - Menunggu"**.
   - Tombol "Setujui Kegiatan" **TIDAK MUNCUL** untuk Super Admin 1 karena ia adalah pembuat.
5. Lakukan Logout (klik menu **"Keluar Sistem"**).

#### Fase C: Penolakan oleh Super Admin 2 & Pengembalian ke Draft
1. Login sebagai **Super Admin 2** di `/admin/masuk`.
2. Buka menu **"Kelola Kegiatan"** (`/admin/kegiatan`). Pada badge filter terdapat indikator `"Menunggu Persetujuan Anda"`.
3. Buka detail kegiatan tersebut (`/admin/kegiatan/{id}`).
4. Pada panel persetujuan, Super Admin 2 melihat dua tombol aksi:
   - Tombol hijau: **"Setujui Kegiatan"** (ikon `check_circle`)
   - Tombol merah: **"Tolak Kegiatan"** (ikon `cancel`)
5. Klik tombol **"Tolak Kegiatan"**.
6. Modal konfirmasi penolakan terbuka:
   - Judul modal: **"Tolak Persetujuan Kegiatan"**
   - Kolom textarea: *"Tuliskan catatan alasan penolakan kegiatan secara rinci..."*
   - Masukkan catatan (wajib min. 5 karakter, misal: `"Harap perbaiki kuota dan lampiran panduan resmi"`).
   - Klik tombol **"Konfirmasi Penolakan"**.
7. **Hasil**:
   - Banner sukses muncul: `"Kegiatan ditolak dan dikembalikan ke status draft."`
   - Status kegiatan kembali menjadi `"Draft"`.
   - Seluruh riwayat suara persetujuan sebelumnya dihapus.
8. Lakukan Logout.

#### Fase D: Pengajuan Ulang oleh Super Admin 1
1. Super Admin 1 login kembali, masuk ke `/admin/kegiatan/{id}`.
2. Klik tombol **"Ajukan Persetujuan"**.
3. Status kembali menjadi `"Diajukan"`. Lakukan logout.

#### Fase E: Persetujuan oleh Super Admin 2 & Penerbitan Resmi
1. Super Admin 2 login kembali, buka `/admin/kegiatan/{id}`.
2. Klik tombol hijau: **"Setujui Kegiatan"**.
3. **Hasil**:
   - Suara persetujuan dicatat lengkap dengan tanda tangan digital data (*data fingerprint*) dan IP peninjau.
   - Karena seluruh penyetuju wajib telah setuju, kegiatan otomatis terbit.
   - Muncul banner hijau sukses: berlabel persis `"Seluruh Super Admin telah menyetujui. Kegiatan resmi diterbitkan."`
   - Status kegiatan berubah menjadi badge hijau `"Published"` / `"Terbit"`. Kegiatan kini dapat dilihat di katalog publik dan siap menerima pendaftaran.

---

### Pengujian Penguncian Field Kritis (*Locked Critical Fields*)
Setelah kegiatan berstatus `Published` dan memiliki catatan persetujuan:
1. Masih sebagai Super Admin, buka daftar kegiatan di `/admin/kegiatan`.
2. Klik tombol edit (ikon pensil).
3. Perhatikan form wizard:
   - Muncul banner peringatan: berlabel persis *"Field Kritis Dikunci: Jadwal, metode, sasaran, biaya, kuota, serta aturan submission pada kegiatan yang telah terbit telah dikunci demi integritas pendaftaran."*
   - Elemen input untuk: `kategori`, `sasaran_peserta`, `metode_pelaksanaan`, `tanggal_mulai`, `tanggal_selesai`, `registration_deadline`, `submission_deadline`, `biaya`, `kuota`, dan aturan karya berstatus `disabled`.
4. **Kasus Negatif (Bila dipaksa melalui manipulasi request)**:
   - Pesan galat validasi yang terbukti dari kode:
     `"Field kritis (jadwal, metode, biaya, kuota, atau aturan kehadiran & sertifikat) pada kegiatan yang sudah terbit telah dikunci dan tidak dapat diubah secara langsung."`

---

## 4. Presensi Dua Fase (Check-in Awal, Layar Proyektor, & QR Layar Peserta)

Sistem SEMADIKSI menerapkan arsitektur presensi fisik 2 fase yang menjamin peserta benar-benar hadir dari awal hingga akhir acara.

```
[ Tiket Peserta (QR) ]
         │
         ▼  (Fase 1: Pindai oleh Panitia di Pintu Masuk)
[ Check-In Presensi Awal ] ──► Status: Checked-In
         │
         │  (Acara berlangsung...)
         ▼  (Fase 2: Admin Buka Layar Proyektor)
[ Layar Proyektor Acara ] ──► QR Dinamis (HMAC berganti tiap 30 detik)
         │
         ▼  (Peserta Pindai QR Layar via Ponsel)
[ Verifikasi Kelayakan ] ──► Syarat: Wajib sudah Check-In Fase 1
         │
         ▼
[ Form Presensi Mandiri ] ──► Tanda Tangan Touchscreen Canvas (PNG)
         │
         ▼
[ Terbit E-Sertifikat Otomatis ] ──► Sertifikat Resmi + QR Verifikasi Publik
```

### Otorisasi:
- Pengelolaan scanner, buka sesi, dan proyektor: Hanya role `super_admin` (`route('admin.attendances')`).
- Pemindaian QR layar proyektor: Dilakukan oleh akun peserta (`mahasiswa_kipk` atau `user_umum`) yang telah login di perangkat gawai masing-masing.

---

### Langkah Uji Fase 1: Presensi Awal (Check-In Tiket Masuk)
1. Login sebagai Super Admin di `/admin/masuk`.
2. Di sidebar admin, klik menu **"Presensi & Kehadiran"** (`/admin/absensi`).
3. Pada dropdown **"Pilih Kegiatan:"**, pilih kegiatan yang sedang berjalan.
4. Klik tombol hijau: berlabel persis **"Pindai Tiket"** (ikon `qr_code_scanner`) &rarr; menuju URL `/admin/kegiatan/{id}/pindai-tiket`.
5. Di halaman pemindai tiket tersedia 3 metode check-in:
   - **Metode A (Kamera)**: Izinkan akses webcam/kamera, arahkan QR e-tiket peserta ke kotak pemindai.
   - **Metode B (Barcode Scanner USB / Keyboard)**: Pada kolom *"Input Kode Tiket / Pemindai USB"*, ketik nomor tiket (misal `TKT-20261026-0001`) lalu tekan Enter atau klik tombol **"Kirim"**.
   - **Metode C (Check-In Manual Cadangan)**:
     - Gulir ke tabel bawah: **"Daftar Peserta & Check-In Manual"**.
     - Cari peserta berdasarkan nama atau NIM.
     - Pada baris peserta yang belum hadir, klik tombol oranye: berlabel persis **"Check-in manual"** (ikon `how_to_reg`).
     - Pada modal yang muncul, isi kolom **Alasan** (minimal 3 karakter, misal: `"Gawai peserta kehabisan baterai"`).
     - Klik tombol **"Konfirmasi Check-In Manual"**.
6. **Hasil yang Diharapkan**:
   - Kartu sisi kanan berubah menjadi hijau: `"Tiket Sah & Diterima"` dengan detail nama peserta, nomor tiket, dan nomor kursi.
   - Suara bip sukses dibunyikan.
   - Status peserta di database berubah menjadi `checked_in`.
   - Muncul di tabel **"10 Riwayat Pemindaian Terakhir"** beserta tombol merah **"Batalkan"** (untuk membatalkan check-in dengan alasan wajib).

---

### Langkah Uji Fase 2: Buka Sesi & Menampilkan Layar Proyektor
1. Kembali ke halaman utama presensi di `/admin/absensi`.
2. Pada panel **"Sesi Presensi Akhir (Fase 2)"**, perhatikan status sesi:
   - Bila belum dibuka, klik tombol oranye: berlabel persis **"Buka Presensi Akhir"** (ikon `lock_open`).
   - Pada modal yang muncul, atur jam buka/tutup presensi, lalu konfirmasi.
   - Status berubah menjadi badge hijau berdenyut: `"Sesi Dibuka"`.
   - *(Catatan: Saat sesi aktif, tersedia tombol "Tutup Sekarang" dan "Perpanjang").*
3. Klik tombol biru cyan: berlabel persis **"Buka Layar Proyektor"** (ikon `tv`).
4. Halaman terbuka di tab baru pada URL `/admin/kegiatan/{id}/presensi-proyektor`:
   - Layar menampilkan QR Code dinamis besar dengan tanda air `"HMAC Terverifikasi"`.
   - Di bawah QR Code terdapat progress bar dan teks hitung mundur: `"QR diperbarui otomatis 30s"`.
   - Kode QR berganti secara otomatis setiap 30 detik untuk mencegah kecurangan titip absen / foto QR disebarkan.
   - Terdapat tombol **"Layar Penuh"** untuk mode proyektor aula.
   - Sisi kanan menampilkan live counter: **"Kehadiran Akhir Masuk"** (misal: `1 dari 1 peserta check-in`).

---

### Langkah Uji Alur Peserta Memindai QR Layar Proyektor
1. Di ponsel atau tab browser lain, login sebagai akun mahasiswa peserta (misal `ahmad@student.unusa.ac.id`) di `/masuk`.
2. Buka kamera ponsel atau peramban, lalu pindai kode QR yang sedang tampil di layar proyektor.
3. Tautan QR mengarah ke URL: `/kegiatan/{id}/presensi-akhir?token=TOKEN_HMAC`.
4. Sistem memverifikasi token dan kelayakan peserta:
   - Peserta **wajib** sudah check-in di Fase 1.
   - Sesi presensi akhir sedang dibuka.
5. Setelah lolos verifikasi, peserta diarahkan ke halaman formulir presensi mandiri di `/kegiatan/{id}/presensi`:
   - Muncul notifikasi hijau: *"Verifikasi QR presensi akhir berhasil! Silakan bubuhkan tanda tangan digital Anda di bawah ini."*
   - Data diri peserta (Nama, NIM, Prodi, Email, No. HP) otomatis terisi.
   - Tampil kanvas tanda tangan interaktif (*HTML5 Touchscreen Canvas*).
6. Peserta menggoreskan tanda tangan digital di atas kanvas layar sentuh ponsel.
7. Klik tombol hijau: berlabel persis **"Simpan Presensi & Tanda Tangan"**.
8. **Hasil yang Diharapkan**:
   - Presensi akhir tersimpan, gambar tanda tangan tersimpan sebagai berkas PNG.
   - Sistem otomatis membuat dokumen sertifikat digital dengan QR code verifikasi publik.
   - Halaman menampilkan konfirmasi kehadiran dan nomor seri sertifikat resmi.
   - Angka counter Fase 2 pada layar proyektor bertambah secara *real-time*.

#### Kasus Negatif & Pesan Galat Presensi Terbukti di Kode:
- **Token QR Kedaluwarsa (> 30-60 detik)**:
  Peserta dialihkan ke dashboard dengan pesan error:
  `"Token QR presensi akhir telah kedaluwarsa atau tidak valid. Silakan pindai ulang dari layar proyektor."`
- **Peserta Belum Check-in Fase 1**:
  Bila peserta belum pernah check-in di pintu masuk aula:
  `"Check-in awal belum tercatat. Hubungi panitia."`
- **Sesi Ditutup**:
  Bila admin telah menutup sesi presensi akhir:
  `"Sesi presensi akhir belum dibuka atau sudah ditutup oleh panitia."`
- **Akses Langsung Tanpa Scan QR**:
  Bila peserta mencoba mengetikkan URL `/kegiatan/{id}/presensi` secara langsung tanpa memindai QR proyektor:
  `"Pindai QR di layar acara untuk presensi akhir."`
- **Peserta Tidak Terdaftar**:
  Bila akun tidak memiliki tiket pada kegiatan tersebut:
  `"Anda tidak terdaftar dalam kegiatan ini dengan tiket yang sah."`

---

## 5. Pembuatan Kegiatan Lomba & Pengumpulan Karya (Wizard "5. Karya")

Fitur ini mengatur siklus hidup kompetisi, mulai dari pembuatan perlombaan oleh admin hingga pengumpulan berkas karya oleh peserta.

### Otorisasi:
- Pembuatan kegiatan: Hanya `super_admin` (`/admin/kegiatan`).
- Pengumpulan karya: Peserta terdaftar (`mahasiswa_kipk` atau `user_umum`) di menu **Kegiatan Saya** (`/dashboard/kegiatan-saya`).

---

### Langkah Pembuatan Kegiatan Lomba (Form Wizard 5 Langkah)
1. Login sebagai `super_admin`, buka menu **"Kelola Kegiatan"** (`/admin/kegiatan`).
2. Klik tombol **"Tambah Kegiatan Baru"** di kanan atas.
3. Modal 5 langkah terbuka:
   - **Langkah 1: `1. Info Dasar`**
     - Nama Kegiatan: Ketik nama lomba, contoh: `"Lomba Inovasi Teknologi Nasional 2026"`
     - Kategori: Pilih `"Lomba"`
     - Sasaran Peserta: Pilih `"Umum"` atau `"Internal KIP-K UNUSA"`
     - Metode Pelaksanaan: Pilih `"Offline"`
     - Waktu Mulai & Selesai (WIB): Tentukan jadwal acara (fitur pemilih waktu otomatis mengisi waktu selesai +2 jam).
     - Batas Pendaftaran (WIB): Tentukan deadline pendaftaran.
     - Biaya: Isi `0` (gratis).
     - Kuota Peserta: Masukkan kuota, misal `50`.
     - Klik tombol: **"Lanjut ke Langkah 2"**.
   - **Langkah 2: `2. Lokasi`**
     - Tipe Lokasi: Pilih `"Kampus UNUSA"`.
     - Pilih fasilitas: Kampus B &rarr; Tower UNUSA &rarr; Auditorium Lt. 9.
     - Nama Lokasi Tampilan: `"Auditorium Tower UNUSA Lt. 9"`.
     - Klik tombol: **"Lanjut ke Langkah 3"**.
   - **Langkah 3: `3. Berkas PDF`**
     - Unggah berkas pada kolom **"Dokumen Panduan Resmi Kegiatan (Wajib PDF, Maks. 10MB)"**.
     - Klik tombol: **"Lanjut ke Langkah 4"**.
   - **Langkah 4: `4. Denah Kursi`**
     - Opsi denah kursi interaktif (dapat dicentang atau diabaikan bila lomba tanpa nomor kursi).
     - Klik tombol: **"Lanjut ke Langkah 5"**.
   - **Langkah 5: `5. Karya` (Langkah Krusial Lomba)**:
     - Centang kotak: berlabel persis **"Wajibkan Pengumpulan Karya / Berkas"** (`is_submission_active = true`).
     - Muncul dua kolom tambahan:
       1. **Tenggat Waktu Pengumpulan (WIB)** (`submission_deadline`): Masukkan tanggal dan jam batas akhir pengumpulan berkas karya.
       2. **Ketentuan & Format Karya** (`submission_instructions`): Ketik petunjuk karya (contoh: *"Format naskah PDF maksimal 20 MB, berisi lembar orisinalitas, abstrak, dan pembahasan inovasi"*).
     - Klik tombol hijau di sudut kanan bawah: berlabel persis **"Terbitkan Kegiatan"**.
4. Terbitkan kegiatan (atau setujui melalui alur dua admin bila disimpan sebagai draft).

---

### Alur Pendaftaran & Pengumpulan Karya oleh Peserta
1. Login sebagai peserta (misal `ahmad@student.unusa.ac.id`) di `/masuk`.
2. Buka katalog kegiatan di `/kegiatan`, pilih lomba yang baru dibuat, lalu lakukan pendaftaran hingga tiket terbit.
3. Di navbar atas, klik menu **"Kegiatan Saya"** (`/dashboard/kegiatan-saya`).
4. Pada kartu lomba yang didaftarkan, perhatikan kotak **Status Karya Lomba**:
   - Status awal: badge merah berlabel `"Belum mengunggah karya"`.
   - Di sebelahnya terdapat tombol: berlabel persis **"Unggah Karya"**.
5. Klik tombol **"Unggah Karya"**.
6. Modal formulir terbuka berlabel **"Pengumpulan Karya Kompetisi"**:
   - Kolom **Judul Karya / Proyek \***: Masukkan judul karya (contoh: `"Sistem Deteksi Dini Berbasis IoT"`).
   - Kolom **Deskripsi Singkat / Abstrak**: Masukkan ringkasan karya ilmiah.
   - Kolom **Unggah Berkas Karya (Maks. 20MB) \***: Pilih berkas dokumen karya (PDF/ZIP, maksimal 20 MB).
   - Klik tombol hijau: berlabel persis **"Kirim Karya untuk Dinilai"** (berubah menjadi `"Mengunggah..."` saat proses).
7. **Hasil yang Diharapkan**:
   - Banner hijau sukses muncul: `"Karya berhasil diunggah dan siap dinilai oleh juri."`
   - Status pada kartu Kegiatan Saya berubah menjadi badge kuning: `"Karya terkirim (Menunggu Penjurian)"`.
   - Tombol berubah label menjadi **"Ubah Karya"** (peserta dapat memperbarui berkas sebelum tenggat waktu berakhir).
   - Berkas karya langsung masuk ke dashboard meja juri di menu **Penilaian Karya** (`/admin/penilaian`).

---

## 6. Pemilih Tanggal-Waktu pada Form Kegiatan (`InputWaktuWib`)

Seluruh input tanggal dan waktu pada formulir admin dikelola melalui komponen terpadu `resources/js/Components/Form/InputWaktuWib.jsx`.

### Karakteristik & Perilaku Terbukti dari Kode:
1. **Format Bebas Detik (`step="60"`)**:
   - Elemen input HTML5 diberi atribut `step="60"`.
   - Layar input peramban hanya menampilkan format jam dan menit (`--:--`), tanpa ada kolom detik (`--:--:--`).
2. **Normalisasi Nilai Waktu WIB**:
   - Komponen menerapkan fungsi `normalisasiWaktuWib(val)` yang memotong string ke format standar `YYYY-MM-DDTHH:mm`.
   - Nilai waktu diinput dalam zona waktu lokal **WIB** (Asia/Jakarta) dan saat disimpan dikonversi ke **UTC** di database backend melalui `App\Support\Wib::keUtc()`.
3. **Pemicu Kalender Otomatis (*Native showPicker*)**:
   - Mengklik atau memfokuskan kursor pada badan kolom input secara otomatis memicu metode peramban `e.target.showPicker()` di dalam blok pengaman `try/catch`. Pengguna tidak perlu membidik ikon kecil kalender secara presisi.
4. **Tema Gelap & Indikator Kontras (*Dark Mode Scheme*)**:
   - Atribut CSS inline `style={{ colorScheme: 'dark' }}` diterapkan pada input.
   - Ikon kalender bawaan peramban (*WebKit calendar picker indicator*) tampil kontras, jelas terlihat di atas latar belakang gelap `slate-950`, memiliki kursor *pointer*, dan efek pembesaran (*scale 110%*) saat didekati kursor.
5. **Tombol "Kosongkan" Otomatis**:
   - Untuk kolom jadwal yang bersifat opsional (seperti: Waktu Selesai, Batas Pendaftaran, Batas Pengumpulan Karya, dan Batas Booking Kursi), saat kolom memiliki isi nilai, tombol kecil berlabel persis **"Kosongkan"** otomatis muncul di sebelah kanan label.
   - Mengklik **"Kosongkan"** langsung mereset nilai kolom menjadi string kosong tanpa perlu menekan tombol keyboard *backspace*.
6. **Teks Bantuan & Aksesibilitas**:
   - Di bawah setiap kolom tertera teks bantu: *"Klik kolom atau ikon kalender untuk memilih"*.
   - Terhubung dengan atribut aksesibilitas `aria-label`, `aria-invalid`, dan `aria-describedby` ke elemen pesan kesalahan.

---

## 7. Fitur "Terbitkan Sertifikat"

> [!NOTE]
> **STATUS FITUR PADA KODE: SUDAH TERSEDIA**  
> Fitur ini telah diimplementasikan penuh di dalam kode, baik sebagai antarmuka penerbitan manual ber-template PDF di panel admin maupun sebagai layanan otomatis (*batch generator*) saat peserta menyelesaikan presensi akhir.

### 1. Antarmuka Penerbitan Sertifikat Panel Admin
- **Role yang Berhak**: `super_admin` (`Route::middleware(['role:super_admin'])`).
- **URL Awal**: `/admin/sertifikat` (Route: `admin.certificates`).
- **Menu Sidebar**: Klik menu **"Sertifikat Digital"** (ikon `workspace_premium`).
- **Halaman**: Berjudul **"Penerbitan & Arsip Sertifikat"** dengan tata letak 2 kolom:
  - **Kolom Kiri (Formulir Penerbitan)**:
    - Dropdown: **"Pilih Kegiatan / Beasiswa *"** (`kegiatan_id`)
    - Input Teks: **"Penerima Sertifikat (Mahasiswa) *"** (`nama_lengkap`, dilengkapi fitur autocomplete datalist rekomendasi peserta kegiatan/mahasiswa KIP-K).
    - Input Nomor Seri: **"Nomor Seri Sertifikat"** (`nomor_sertifikat`) beserta tombol klik **"Generate Otomatis"** (menghasilkan format unik: `CERT-{KODE_KEGIATAN}-{TAHUN}-{NOMOR}`).
    - Area Unggah Berkas: **"Lampiran Berkas Sertifikat (PDF) *"** (`certificate_file`, area *drag-and-drop* berkas PDF maksimal 10 MB).
    - Tombol Submit: berlabel persis **"Terbitkan Sertifikat"** (ikon `verified`).
  - **Kolom Kanan (Tabel Arsip)**:
    - Judul: **"Daftar Sertifikat Terbit"**.
    - Menampilkan data: Mahasiswa, Nama Kegiatan, Nomor Seri unik, dan Tanggal Terbit.
    - Tombol Aksi per Baris:
      - **"Unduh Berkas"** (`download` &rarr; unduh file PDF)
      - **"Verifikasi Publik"** (`verified` &rarr; membuka portal verifikasi publik di `/verifikasi?code=CERT-...`)
      - **"Hapus"** (`delete` &rarr; hapus arsip sertifikat)

### 2. Penerbitan Otomatis Lewat Presensi Akhir Fase 2
Selain form admin di atas, sistem menyediakan *engine* otomatis `CertificateGeneratorService` yang:
1. Membaca template PDF kegiatan yang diunggah di konfigurasi kegiatan.
2. Menempelkan nama lengkap peserta, nomor seri unik, dan kode QR verifikasi dinamis di pojok sertifikat.
3. Langsung terbit otomatis begitu peserta menyelesaikan tanda tangan digital presensi akhir di ponselnya.

---

## 8. Alat Diagnosa SSO Read-Only (`semadiksi:diagnosa-sso`)

Perintah CLI ini digunakan oleh Administrator / Pengembang untuk mendiagnosa alasan mengapa suatu akun ditolak saat login SSO UNUSA atau memeriksa kesiapan aktivasi akun tanpa mengubah database sama sekali (*strictly read-only*).

### Perintah Eksekusi
```bash
php artisan semadiksi:diagnosa-sso {nim}
```

Contoh:
```bash
php artisan semadiksi:diagnosa-sso 4330024012
```

### Karakteristik & Keamanan
1. **Read-Only**: Tidak ada penulisan atau manipulasi ke database, tidak ada pemanggilan jaringan luar (menggunakan data master dan provider lokal/mock).
2. **Penyamaran Data (*Masked*)**:
   - NIM disamarkan (contoh: `4330XXXX12` via `App\Support\Masking::nim`).
   - Tidak mencetak kata sandi (*hash*), token aktivasi, token sesi, atau klaim OIDC mentah.
3. **Logika Evaluasi Tunggal**: Menggunakan fungsi evaluasi `KeycloakService::evaluasiLoginSso` yang sama persis dengan alur callback SSO nyata, sehingga hasil diagnosa selalu akurat 100%.

### Parameter yang Ditampilkan
| Parameter | Keterangan |
| :--- | :--- |
| **NIM (Tersamar)** | NIM target dalam format aman |
| **Akun di SEMADIKSI** | Status keberadaan akun di tabel `users` (Ada / Tidak Ada / Akun Ganda) |
| **Peran Akun** | Peran akun (`mahasiswa_kipk`, `user_umum`, `super_admin`, dll.) |
| **Status Aktif** | Status blokir akun (`Aktif` / `Diblokir`) |
| **Email Terverifikasi** | Status verifikasi email (`Terverifikasi` / `Belum terverifikasi`) |
| **keycloak_sub Tertaut** | Status penautan ID SSO Keycloak (`Tertaut` / `Tidak tertaut`) |
| **Data Master Mahasiswa** | Keberadaan NIM di master mahasiswa UNUSA (`Ada` / `Tidak Ada`) |
| **Penerima KIP-K** | Status NIM pada daftar penerima beasiswa KIP-K (`Terdaftar` / `Tidak Terdaftar`) |
| **Kode Alasan Evaluasi** | Kode alasan internal (`aktivasi via SSO`, `bukan penerima KIP-K`, `role tidak diizinkan SSO`, `akun diblokir`, dll.) |
| **Pesan ke Pengguna** | Pesan ramah yang akan tampil di layar login pengguna |
| **Prediksi Kelayakan SSO** | Kesimpulan kelayakan login SSO |

---


## Matriks Rekapitulasi Jalur Klik & Ketersediaan UI

| Fitur | Lokasi Menu | URL Awal | Label Tombol Utama | Status UI |
| :--- | :--- | :--- | :--- | :--- |
| **Ekspor Penilaian Excel** | Penilaian Karya | `/admin/penilaian` | `"Ekspor Excel (.xlsx)"` | **Tersedia di UI** |
| **Impor Penilaian Excel** | Penilaian Karya | `/admin/penilaian` | `"Impor Nilai (.xlsx / .csv)"` &rarr; `"Unggah & Perbarui Nilai"` | **Tersedia di UI** |
| **Aktivasi 2FA** | Halaman Masuk | `/two-factor/setup` | `"Konfirmasi & Aktifkan 2FA"` | **Tersedia di UI** |
| **Tantangan 2FA** | Halaman Masuk | `/two-factor/challenge` | `"Verifikasi Masuk"` | **Tersedia di UI** |
| **Reset 2FA oleh Admin** | Data Pengguna | `/admin/pengguna` | Tombol `"Reset 2FA"` (ikon `lock_reset`) &rarr; Modal `"Reset 2FA"` | **Tersedia di UI** |
| **Ajukan Persetujuan Kegiatan** | Kelola Kegiatan | `/admin/kegiatan/{id}` | `"Ajukan Persetujuan"` | **Tersedia di UI** |
| **Setujui Kegiatan** | Kelola Kegiatan | `/admin/kegiatan/{id}` | `"Setujui Kegiatan"` | **Tersedia di UI** |
| **Tolak Kegiatan** | Kelola Kegiatan | `/admin/kegiatan/{id}` | `"Tolak Kegiatan"` &rarr; `"Konfirmasi Penolakan"` | **Tersedia di UI** |
| **Pemindai QR Tiket Awal** | Presensi & Kehadiran | `/admin/kegiatan/{id}/pindai-tiket` | `"Pindai Tiket"` | **Tersedia di UI** |
| **Check-in Manual Awal** | Presensi & Kehadiran | `/admin/kegiatan/{id}/pindai-tiket` | `"Check-in manual"` &rarr; `"Konfirmasi Check-In Manual"` | **Tersedia di UI** |
| **Buka Layar Proyektor** | Presensi & Kehadiran | `/admin/kegiatan/{id}/presensi-proyektor` | `"Buka Layar Proyektor"` | **Tersedia di UI** |
| **Presensi Mandiri Peserta** | Scan QR Layar | `/kegiatan/{id}/presensi` | `"Simpan Presensi & Tanda Tangan"` | **Tersedia di UI** |
| **Wizard Langkah 5 (Karya)** | Kelola Kegiatan | `/admin/kegiatan` | Tab `"5. Karya"` &rarr; `"Terbitkan Kegiatan"` | **Tersedia di UI** |
| **Unggah Karya Peserta** | Kegiatan Saya | `/dashboard/kegiatan-saya` | `"Unggah Karya"` &rarr; `"Kirim Karya untuk Dinilai"` | **Tersedia di UI** |
| **Pemilih Waktu (WIB)** | Form Kegiatan | `/admin/kegiatan` | Input kalender & tombol `"Kosongkan"` | **Tersedia di UI** |
| **Terbitkan Sertifikat** | Sertifikat Digital | `/admin/sertifikat` | `"Terbitkan Sertifikat"` | **Tersedia di UI** |
