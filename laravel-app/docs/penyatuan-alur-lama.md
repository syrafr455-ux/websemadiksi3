# Dokumentasi Penyatuan Alur Lama ke Alur Baru SEMADIKSI

Dokumen ini memetakan struktur dan pemakaian tabel alur lama (`activities`, `attendances`, `certificates`, `activity_registrations`) ke alur baru (`kegiatan`, `kegiatan_presensi`, `sertifikat`, `kegiatan_registrations`).

Alur baru dijadikan **satu-satunya sumber kebenaran (single source of truth)** untuk seluruh operasi sistem, sementara tabel dan data lama tetap dipertahankan utuh sebagai arsip baca-saja (*read-only archive*).

---

## 1. Pemetaan Struktur Tabel

### A. Kegiatan: `activities` &rarr; `kegiatan`
Tabel `kegiatan` memiliki kolom `legacy_activity_id` yang menyimpan ID asli dari tabel `activities`.

| Kolom Lama (`activities`) | Kolom Baru (`kegiatan`) | Aturan Transformasi & Pemetaan |
| :--- | :--- | :--- |
| `id` | `legacy_activity_id` | Disimpan sebagai referensi unik pelacak migrasi idempoten. |
| `title` | `nama_kegiatan` | Disalin langsung (string). |
| `description` | `deskripsi` | Disalin langsung (teks). |
| `category` | `kategori` | Dipetakan ke enum `kategori` (`seminar`, `workshop`, `webinar`, `pelatihan`, `lomba`, `pengabdian_masyarakat`, `lainnya`). |
| `type` | `metode_pelaksanaan` | Dipetakan ke enum `metode_pelaksanaan` (`online`, `offline`, `hybrid`). |
| `event_date` / `date_text` | `tanggal_mulai`, `tanggal_selesai` | Diparsing menjadi format datetime ISO/WIB; default `now()` jika kosong. |
| `status` | `status` | Dipetakan ke enum `KegiatanStatus` (`draft`, `published`, `ongoing`, `completed`, `cancelled`). |
| `price` | `biaya` | Nilai `Gratis` dikonversi ke `0.00`; angka diparsing desimal. |
| `quota` | `kuota` | Disalin langsung (integer). |
| `xp_points` | `poin_xp` | Disalin langsung (integer). |
| `location` | `lokasi_nama`, `lokasi_type` | `lokasi_nama` disalin; `lokasi_type` ditentukan (`unusa_campus`, `online`, atau `external`). |
| `enable_seat_booking` | `is_seat_booking_active` | Boolean flag pemilihan kursi. |
| `seat_layout_config` | `seat_layout_config` | JSON denah kursi disalin jika ada. |
| `require_file_upload` | `is_submission_active` | Boolean pengumpulan karya / submission. |
| `file_upload_instruction` | `submission_instructions` | Teks instruksi submission disalin. |
| *(default)* | `file_panduan_pdf` | Default diisi jalur panduan standar `kegiatan/panduan/panduan.pdf`. |

---

### B. Presensi: `attendances` &rarr; `kegiatan_presensi`

Tabel `kegiatan_presensi` mewakili presensi mandiri (fase 2) dan terhubung langsung ke `kegiatan_id`.

| Kolom Lama (`attendances`) | Kolom Baru (`kegiatan_presensi`) | Aturan Transformasi & Pemetaan |
| :--- | :--- | :--- |
| `activity_id` | `kegiatan_id` | **Wajib valid**. Dicocokkan ke `kegiatan.legacy_activity_id` atau `kegiatan.id`. |
| `user_id` | `user_id` | Disalin jika berelasi ke akun user yang ada (nullable). |
| `student_name` | `nama_lengkap` | Disalin langsung (string nama peserta). |
| `student_nim` | `nim` | Disalin langsung (string identitas / NIM). |
| `university` / user prodi | `prodi` | Diisi nama program studi user jika ada; fallback ke nama perguruan tinggi / `Umum`. |
| `student_email` | `email` | Disalin langsung; jika kosong diisi email user atau fallback `peserta@semadiksi.id`. |
| `student_phone` | `nomor_telepon` | Disalin langsung; jika kosong diisi `-`. |
| `proof_image_url` | `signature_path` | URL/jalur bukti kehadiran disimpan pada berkas tanda tangan/bukti presensi. |
| `submitted_at` | `check_in_at` | Disalin sebagai timestamp waktu kehadiran (WIB). |
| *(relasi)* | `registration_id` | Dicari dari `kegiatan_registrations` jika peserta memiliki pendaftaran terdata. |

#### Penanganan Data Ambigu pada `attendances`
Jika baris pada `attendances` memiliki kriteria berikut:
1. `activity_id` bernilai `NULL`.
2. `activity_id` merujuk ke ID yang tidak ditemukan di `activities` maupun `kegiatan`.
3. Terdapat ambiguitas data kegiatan tujuan.

**Aturan Ketat:**
Sesuai arahan anti-halusinasi dan integritas data, **JANGAN MENEBAK**. Baris tersebut **TIDAK DISALIN** ke tabel `kegiatan_presensi` (karena kolom `kegiatan_id` pada `kegiatan_presensi` bersifat *NOT NULL*). Data dibiarkan tetap utuh di tabel `attendances` sebagai **arsip baca-saja (read-only archive)** dan dicatat dalam laporan log migrasi.

---

### C. Sertifikat: `certificates` &rarr; `sertifikat`

Tabel `sertifikat` mewakili sertifikat digital terverifikasi dengan hash SHA-256 dan terhubung ke `kegiatan_id`.

| Kolom Lama (`certificates`) | Kolom Baru (`sertifikat`) | Aturan Transformasi & Pemetaan |
| :--- | :--- | :--- |
| `code` | `nomor_sertifikat` | Kunci unik nomor sertifikat (contoh: `CERT-...`). Idempoten, tidak menimpa data yang sudah ada. |
| `activity_id` | `kegiatan_id` | **Wajib valid**. Dicocokkan ke `kegiatan.legacy_activity_id` atau `kegiatan.id`. |
| `user_id` | `user_id` | Disalin jika akun user terdaftar (nullable). |
| `user_name` | `nama_penerima` | Nama lengkap penerima sertifikat. |
| `status` | `is_valid` | Nilai `Berlaku` dipetakan ke `true`; `Dicabut` dipetakan ke `false`. |
| `issued_date` | `issued_at` | Disalin sebagai timestamp penerbitan sertifikat (WIB). |
| `file_url` | `file_pdf_path` | Jalur dokumen PDF sertifikat. |
| *(kriptografi)* | `document_hash` | Dihitung menggunakan SHA-256 (`hash('sha256', $code . $user_name)`). |
| *(default)* | `peran_sertifikat` | Default dipetakan ke enum `PeranSertifikat::Peserta`. |

#### Penanganan Data Ambigu pada `certificates`
Jika baris pada `certificates` memiliki kriteria:
1. `activity_id` bernilai `NULL`.
2. `activity_id` tidak dapat dipetakan secara pasti ke `kegiatan_id` mana pun.

**Aturan Ketat:**
Baris tersebut **TIDAK DISALIN** ke `sertifikat`, melainkan tetap berada di tabel `certificates` sebagai **arsip baca-saja**. Portal publik `/verifikasi` tetap mampu memverifikasi sertifikat legacy ini melalui mekanisme fallback read-only.

---

## 2. Status Route Publik & Layanan Baru

1. **Route Publik `/absensi` (`absensi.public` & `absensi.public.store`)**:
   - Menampilkan daftar kegiatan aktif dari tabel `kegiatan` (status `published` atau `ongoing`).
   - Menyimpan bukti presensi mandiri ke tabel `kegiatan_presensi`.
2. **Route Publik `/verifikasi` & `/verifikasi-sertifikat` (`verifikasi.sertifikat` & `verifikasi`)**:
   - Memeriksa tabel baru `sertifikat` sebagai sumber utama verifikasi nomor dan hash dokumen SHA-256.
   - Menyediakan fallback aman ke tabel legacy `certificates` untuk kode sertifikat riwayat masa lalu tanpa memutus tautan publik.
3. **Perintah Artisan**:
   - `php artisan semadiksi:migrasi-alur-lama`
   - Bawaan: `--dry-run` (hanya menampilkan statistik dan analisis tanpa menyentuh basis data).
   - Flag `--eksekusi`: Menjalankan migrasi secara idempoten (hanya dijalankan saat pemeliharaan terjadwal).
