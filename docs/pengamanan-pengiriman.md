# Pengamanan Pengiriman Email & Klien API UNUSA

Dokumen arsitektur pengamanan transmisi jaringan SEMADIKSI untuk menjaga keandalan antrean email dan ketahanan integrasi API eksternal UNUSA.

---

## 1. Pengamanan Pengiriman Email

### A. Antrean & Pembatas Laju (Rate Limiter)
- **Koneksi Antrean**: Seluruh email dikirim melalui antrean (`SendProtectedEmailJob` atau mailable berantrean). Sistem tetap beroperasi normal pada `QUEUE_CONNECTION=sync` untuk keperluan pengujian dan lingkungan lokal.
- **Batas Laju**: Standar 20 email per menit (`EMAIL_RATE_LIMIT_PER_MINUTE` di `config/unusa.php`). Jika laju terlampaui pada worker antrean, job dilepas kembali (`release`) dengan jeda otomatis.

### B. Percobaan Ulang Bertahap (Backoff) & Kegagalan Bersih PII
- **Percobaan Ulang**: Maksimal 3 kali percobaan (`tries = 3`) dengan jeda bertahap: **1 menit, 5 menit, dan 15 menit** (`[60, 300, 900]` detik).
- **Audit Kegagalan**: Ketika batas percobaan habis (`failed()`), sistem hanya mencatat kelas mailable dan pesan galat teknis. **Tidak ada data pribadi** (alamat email penerima, nama, token, nomor identitas) yang ditulis ke berkas log.

### C. Kuota Harian & Prioritas Kategori Email
- **Kuota Harian**: Standar 400 email per hari (`EMAIL_DAILY_QUOTA` di `config/unusa.php`), dihitung otomatis via cache harian.
- **Kategori Email**:
  - **Kritis**: Link aktivasi akun (`ActivationLinkMail`), reset kata sandi (`ResetKataSandiMail`), penerbitan e-tiket (`TicketIssuedMail`), dan pemilihan ulang kursi (`SeatReselectionMail`).
  - **Massal**: Pengingat dan pengumuman pembaruan kegiatan (`KegiatanUpdatedMail`, `KegiatanDiajukanMail`).
- **Peringatan 80%**: Ketika pengiriman harian mencapai $\ge 80\%$ (320 email), peringatan muncul di perintah `php artisan semadiksi:preflight` dan banner dashboard utama Super Administrator.
- **Penundaan Otomatis**: Jika kuota 100% tercapai, **email massal otomatis ditunda ke hari berikutnya** (dijadwalkan pukul 00:01 WIB), sedangkan **email kritis tetap diizinkan terkirim**.

---

## 2. Pengamanan Klien API UNUSA

### A. Pembatas Laju Sisi Klien
- Membatasi frekuensi request keluar dari SEMADIKSI ke gateway UNUSA (standar 60 request/menit, variabel `UNUSA_API_RATE_LIMIT_PER_MINUTE`).

### B. Penanganan HTTP 429 & Header Retry-After
- Jika gateway UNUSA merespons status `429 Too Many Requests`, klien membaca header `Retry-After` (detik) dan mengunci pemanggilan jaringan hingga jeda berakhir.
- Panggilan yang datang selama periode jeda langsung dialihkan ke data lokal tanpa membebani gateway kampus.

### C. Pemutus Sirkuit (Circuit Breaker)
- **Ambang Batas**: 3 kali kegagalan koneksi/server berturut-turut (`UNUSA_API_CB_THRESHOLD`).
- **Waktu Cooldown**: Sirkuit terbuka selama 5 menit (`UNUSA_API_CB_COOLDOWN_SECONDS` = 300 detik).
- **Perilaku**: Selama sirkuit terbuka, panggilan API dihentikan seketika dan otomatis beralih (*graceful fallback*) ke pencarian data lokal (CSV impor) tanpa menampilkan galat teknis kepada pengguna.

### D. Cache Singkat Pencarian NIM & Privasi Log
- Hasil pencarian NIM di-cache selama 5 menit (`UNUSA_API_CACHE_TTL_SECONDS` = 300 detik) menggunakan hash kunci `sha256` untuk mencegah kebocoran identitas di media penyimpanan cache.
- Pencatatan log kegagalan API UNUSA tidak memuat NIM, nama mahasiswa, maupun token OIDC.
