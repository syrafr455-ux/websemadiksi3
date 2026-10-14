# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Overview

SEMADIKSI Portal — a student portal for KIP-K scholarship recipients at Universitas Nahdlatul Ulama Surabaya (UNUSA): activities (kegiatan), attendance (absensi/presensi), certificates (sertifikat), KIP-K documents (berkas), reports (pelaporan, monev), disbursement requests (pencairan). UI text, routes, and most domain names are in Indonesian.

The repo holds **two separate implementations of the same portal**, with matching route paths (`/masuk`, `/daftar`, `/dashboard/*`, `/admin/*`, `/verifikasi`, `/berita-acara`, ...):

1. **Next.js app (repo root)** — the original/deployed version.
2. **`laravel-app/`** — a Laravel rewrite with a richer domain (event booking with seats, payments, two-phase attendance, certificate generation, work judging). It has its own `package.json`, `node_modules`, and tooling; run its commands from inside `laravel-app/`.

Check which app a task is about before editing.

## Aturan Kerja

Aturan ini berlaku untuk setiap tugas di repo ini.

- Jangan mengubah file `.env` (di root maupun `laravel-app/.env`), `database/semadiksi.db`, dan aplikasi Next.js di root. Aplikasi Next.js boleh DIBACA sebagai acuan desain. Pekerjaan dilakukan di `laravel-app/`.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, atau migrasi apa pun ke Supabase tanpa izin pemilik proyek.
- Pengembangan saat ini memakai PostgreSQL lokal; Supabase adalah target nanti. Sebelum Laravel menyentuh Supabase, semua syarat ini harus terpenuhi:
  - Pakai skema terpisah (`DB_SEARCH_PATH=laravel`) atau project Supabase terpisah, jangan skema `public` milik aplikasi Next.js.
  - Pakai user database terbatas yang hanya punya hak di skema tersebut.
  - Jalankan `SHOW search_path` dan pastikan hasilnya benar sebelum migrasi apa pun.
  - `migrate:fresh` dan `db:seed` dilarang di Supabase.
- Jangan menjalankan `git checkout`, `git restore`, `git reset`, atau `git clean` yang membuang perubahan.
- Jangan memasang paket baru (composer maupun npm) tanpa izin.
- Tes memakai SQLite in-memory (`phpunit.xml`). Jangan arahkan tes ke koneksi lain.
- Satu tugas per giliran.
- Untuk tugas besar, tulis rencananya dulu dan tunggu persetujuan sebelum mengedit.
- Setelah mengedit, jalankan seluruh tes dengan `composer test` dari `laravel-app/` (bila bermasalah, gunakan `php vendor/bin/phpunit`) dan laporkan hasilnya, termasuk tes yang gagal.

## Next.js app (root)

Commands (from the repo root): `npm run dev`, `npm run build`, `npm run lint`. There are no tests.

Next.js 16 / React 19 / Tailwind 4, App Router under `src/app`, path alias `@/*` → `src/*`. Per AGENTS.md, read `node_modules/next/dist/docs/` before using Next APIs.

- **Data**: Supabase via `@supabase/supabase-js` (`src/lib/db.ts`). Only the `users` table is persisted; `userDb` wraps all user queries (columns are camelCase, e.g. `kipStatus`, `isBlocked` as 0/1). Env vars: `NEXT_PUBLIC_SUPABASE_URL`/`SUPABASE_URL` plus one of the publishable/anon/service-role keys. `database/semadiksi.db` is the legacy SQLite file from before the Supabase migration.
- **Auth**: custom JWT (`src/lib/auth.ts`, `JWT_SECRET`), set as the httpOnly cookie `semadiksi_token` by `/api/auth/login`. Login accepts an "identity" (email, NIM, id, phone, or name). `verifyPassword` still accepts plain-text legacy passwords when the stored value isn't a bcrypt hash.
- **Client session**: pages are mostly `"use client"`; the logged-in user is cached in `localStorage` under `semadiksi_current_user`, and `src/app/dashboard/layout.tsx` re-reads it on `focus`, `storage`, and the custom `userProfileUpdated` event. Dispatch that event after changing profile data.
- **Content**: most non-user content (berita acara, beasiswa, KIP info, etc.) is static/mock data in `src/data/portalData.ts`, not the database.
- API routes live in `src/app/api/**/route.ts` with `export const runtime = "nodejs"` and return `{ success, message, ... }` JSON.

## Laravel app (`laravel-app/`)

Laravel 13, PHP ^8.3, Vite 7 + Tailwind 4 + React 19.

```bash
composer setup          # install, .env, key, migrate, npm build
composer dev            # serve + queue:listen + pail + vite (concurrently)
composer test           # config:clear + php artisan test
php artisan test --filter=EventEngineAndBookingTest   # single test class/method
vendor/bin/pint         # code style
php artisan semadiksi:migrate-legacy-activities       # copy old `activities` rows into `kegiatan`
```

Development currently runs on a **local PostgreSQL** (`laravel-app/.env`: `127.0.0.1:5432`, database `semadiksi`, schema `public`). Supabase is the later target; `.env.example` shows that target setup, where `DB_SEARCH_PATH` selects the schema. Laravel is not connected to Supabase yet, and its `users` table is not shared with the Next.js app: the Next.js `public.users` on Supabase has a different shape (camelCase columns, string `id`, old `student`/`admin` roles). See "Aturan Kerja" for the conditions before Laravel may touch Supabase. Tests are PHPUnit feature tests in `tests/Feature`. `phpunit.xml` forces SQLite `:memory:` so `RefreshDatabase` never wipes Supabase; keep it that way.

Architecture notes:

- **Hybrid views**: most pages are Blade (`resources/views/**`), while newer screens (admin kegiatan/pengguna/penilaian, `Dashboard/KegiatanSaya`, `Kegiatan/Show`) are React pages in `resources/js/Pages/**` rendered via Inertia, with layouts in `resources/js/Layouts`.
- **Inertia is vendored, not a composer package**: the server side is an in-house shim in `app/Inertia/` (namespace `Inertia\`, PSR-4-mapped in `composer.json`), wired through `HandleInertiaRequests`. Check that shim before relying on features of the official `inertia-laravel` package.
- **Roles** (`App\Enums\UserRole`): `super_admin`, `verifikator_berkas`, `penilai_karya` are staff (`isStaff()`, admin panel under `/admin`, `admin` middleware); `mahasiswa_kipk`, `user_umum` are participants (`/dashboard`). Finer-grained gating uses `role:<a>,<b>` middleware (`EnsureRole`). `EnsureActiveAccount` runs on every web request and blocks deactivated users.
- **Two generations of models**: legacy tables (`Activity`, `ActivityRegistration`, `Attendance`, `Certificate`) coexist with the standardized `Kegiatan*` models (`Kegiatan`, `KegiatanSeat`, `KegiatanRegistration`, `KegiatanPresensi`, `KegiatanSubmission`, `Sertifikat`, `Payment`). New work goes on the `Kegiatan*`/`Sertifikat` side; status values are PHP enums in `app/Enums`.
- **Domain services** in `app/Services`: `PaymentGatewayService` (paid bookings; the gateway calls `POST /api/payment/webhook`, which is CSRF-exempt in `bootstrap/app.php`), `CertificateGeneratorService` (dompdf + QR codes from an uploaded template and schema config), and `UnusaFacilityService` behind the `UnusaFacilityProvider` contract (config-backed implementation in `Services/Facilities`).
- Updating a kegiatan fires `KegiatanUpdatedEvent` → `SendKegiatanUpdatedNotification` (mail); booking issues `TicketIssuedMail`. The queue worker from `composer dev` must be running for these locally.

## Spesifikasi Produk (target untuk `laravel-app/`)

Ringkasan rancangan dari pemilik proyek. Ini target fitur, bukan deskripsi kode saat ini. Bila berbeda dengan kode, cek kodenya dan tanyakan ke pemilik proyek.

### Tech stack
- Backend: Laravel 13 (PHP ^8.3). Frontend: Inertia.js (React) + Tailwind CSS.
- Database: PostgreSQL (Supabase) lewat koneksi `pgsql` (lihat `laravel-app/.env.example`). Tes selalu memakai SQLite in-memory (`phpunit.xml`), jadi `RefreshDatabase` tidak pernah menyentuh Supabase.
- Library: Simple-QRCode, Laravel-DomPDF / FPDI.

### Role pengguna
1. `super_admin`: kontrol penuh, tracking user, kelola role, cetak sertifikat massal.
2. `mahasiswa_kipk`: akses kegiatan internal KIP-K/umum, pelaporan KIP-K, booking kursi, unggah karya.
3. `user_umum`: registrasi kegiatan umum, booking kursi, pembayaran HTM, unggah karya.
4. `verifikator_berkas`: tim kemahasiswaan untuk verifikasi berkas KIP-K & monev. Bukan juri dan tidak melakukan CRUD kegiatan.
5. `penilai_karya`: juri lomba (input nilai & catatan, ekspor/impor Excel).

### Pengadaan kegiatan
- Kategori lengkap, sasaran peserta, metode pelaksanaan (online/offline/hybrid).
- Wajib upload PDF informasi resmi kegiatan.
- Lokasi: pilihan kampus UNUSA (lewat service class, disiapkan untuk integrasi API kampus) atau teks kustom jika di luar kampus.
- Lomba punya deadline ganda: batas akhir pendaftaran dan batas akhir pengumpulan karya.

### Booking kursi & safety venue
- Multi-delegation booking: satu akun/device bisa mendaftarkan rekan yang ikut serta.
- Perubahan tempat ditolak jika kapasitas baru lebih kecil dari jumlah kursi yang sudah ter-booking.
- Reset flow: jika pemindahan ruangan valid dan denah berubah, kursi lama di-reset, status registrasi menjadi `needs_seat_reselection`, dan peserta dikirimi email untuk memilih ulang kursi.
- Freeze: saat booking dinonaktifkan admin atau di luar jadwal, kursi yang sudah ter-booking tetap tersimpan permanen.

### Two-phase presensi & tanda tangan digital
- Presensi awal: scan QR e-tiket saat check-in fisik di lokasi.
- Presensi akhir: form mandiri di HP peserta (Nama, NIM, Prodi, Email, No. HP) plus tanda tangan digital di layar sentuh (HTML5 Canvas).

### E-sertifikat otomatis & portal validasi publik
- Super admin mengunggah template resmi (kop & TTE, tanpa nama).
- Backend otomatis mengisi nama peserta, nomor seri, dan QR code di pojok kanan bawah.
- QR code mengarah ke `/verifikasi?code=CERT-XXXX-XXXX`. Halaman itu menampilkan status keaslian, hash dokumen SHA-256, dan riwayat penandatanganan resmi.

### Pembayaran otomatis
- Gateway yang direkomendasikan: Tripay / Midtrans (QRIS dan Virtual Account).
- Webhook otomatis mengonfirmasi tiket dan mengirim notifikasi email.
