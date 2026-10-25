# API UNUSA

Dipakai bila `UNUSA_ACADEMIC_PROVIDER=api`. Hanya pencarian mahasiswa per NIM yang memakai API; fakultas, prodi, dan staf tetap dari database lokal. Jika API gagal, pencarian kembali ke data impor CSV dan pengguna hanya melihat hasil dari data itu (tanpa galat teknis).

## Variabel .env

| Variabel | Isi |
|---|---|
| `UNUSA_ACADEMIC_PROVIDER` | `api` untuk memakai API, `database` untuk CSV |
| `UNUSA_API_BASE_URL` | URL dasar API data, mis. `https://api.contoh.unusa.ac.id` |
| `UNUSA_API_ISSUER` | Issuer OIDC, mis. `https://sso.contoh.unusa.ac.id/realms/unusa` |
| `UNUSA_API_CLIENT_ID`, `UNUSA_API_CLIENT_SECRET` | Kredensial client dari IT UNUSA |
| `UNUSA_API_SCOPE` | Scope (opsional) |
| `UNUSA_API_PATH_MAHASISWA` | Path data mahasiswa, `{nim}` diganti NIM. Bawaan `/mahasiswa/{nim}` |

Perilaku klien (`App\Services\Unusa\UnusaApiClient`): timeout 5 detik, 2 kali percobaan ulang bila gagal tersambung, verifikasi sertifikat TLS selalu aktif (jangan dimatikan), token di-cache sampai kedaluwarsa dikurangi 30 detik.

## Urutan pengujian

Jalankan dari server yang sama dengan aplikasi (bukan dari laptop), agar VPN/IP yang diuji sama.

1. **Discovery**: `curl -sS "$UNUSA_API_ISSUER/.well-known/openid-configuration"` harus mengembalikan JSON berisi `token_endpoint`.
2. **Token**: `curl -sS -d grant_type=client_credentials -d client_id=... -d client_secret=... "<token_endpoint>"` harus mengembalikan `access_token` dan `expires_in`.
3. **Endpoint data**: `curl -sS -H "Authorization: Bearer <token>" "$UNUSA_API_BASE_URL/mahasiswa/<NIM uji>"` harus mengembalikan JSON.

Bentuk JSON yang diasumsikan aplikasi (belum dikonfirmasi IT): `nim, nama, email, no_telepon, semester, angkatan, program_studi, status, prestasi[]`.

## Arti galat (terlihat admin di log aplikasi, bukan pengguna)

| Pesan | Penyebab |
|---|---|
| Server UNUSA tidak terjangkau. Periksa VPN atau gateway. | Gagal tersambung atau timeout (setelah 2 kali ulang). Cek VPN, gateway, firewall, DNS. |
| Akses ke API UNUSA ditolak (401/403). | Client id/secret salah, scope kurang, atau IP server belum didaftarkan. |
| Konfigurasi API UNUSA belum lengkap. | Ada variabel `UNUSA_API_*` yang kosong. |
| API UNUSA mengembalikan respons yang tidak terduga. | Status selain 2xx/401/403/404 atau JSON tidak sesuai. |

Pengguna umum hanya melihat hasil dari data impor CSV, atau pesan netral "NIM tidak ditemukan".

## Catatan produksi

Server produksi mungkin hanya bisa menjangkau API UNUSA lewat VPN atau dari IP tetap yang didaftarkan IT UNUSA. Hosting tanpa IP keluar tetap (mis. sebagian shared hosting) akan terus mendapat galat "tidak terjangkau" atau 403.
