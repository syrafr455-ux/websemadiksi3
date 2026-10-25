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

### Aturan Kerja Khusus (Anti-Halusinasi dan Anti-Slop)
1. Jangan membaca atau mengedit .env (root maupun laravel-app/.env). Pakai .env.example sebagai acuan variabel.
2. Sebelum memakai kelas, method, route, kolom, view, atau API paket, buktikan keberadaannya dengan membaca kodenya (Grep/Read, php artisan route:list, migrasi, atau source di vendor). Jangan menebak nama. Jika tidak ditemukan, katakan tidak ditemukan; jangan mengarang.
3. Setiap bug dimulai dari tes yang gagal yang mereproduksinya, baru diperbaiki sampai tes itu lulus. Jangan menyatakan "lulus" tanpa output tes yang benar-benar dijalankan. Jangan menambah fitur di luar tugas ini.

### Aturan Umum

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
- Zona waktu (`laravel-app/`): semua waktu disimpan dalam UTC (`config/app.php` dan koneksi `pgsql` sama-sama UTC). Input dari pengguna dianggap WIB (Asia/Jakarta) dan dikonversi ke UTC saat disimpan dengan `App\Support\Wib::keUtc()`. Saat ditampilkan, waktu dikonversi ke WIB: di PHP/Blade/email dengan `Wib::dari()`, di React dengan `formatWib()`/`keInputWib()` dari `resources/js/lib/waktuWib.js`. Label "WIB" hanya boleh muncul bila nilainya sudah dikonversi. Deadline dan batas tutup bersifat eksklusif: tertutup tepat pada detik batasnya.
- Satu tugas per giliran. Satu prompt dihitung sebagai satu tugas walaupun berisi beberapa bagian (A, B, C, ...).
- Untuk tugas besar, tulis rencananya dulu dan tunggu persetujuan sebelum mengedit. Pengecualian: prompt yang bertuliskan "kerjakan sampai selesai" dikerjakan langsung tanpa rencana terpisah dan tanpa menunggu persetujuan di tiap langkah (batasan lain di Aturan Kerja dan di prompt itu tetap berlaku).
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
php artisan semadiksi:referensi-akademik              # re-sync fakultas & program studi reference data
```

Development currently runs on a **local PostgreSQL** (`laravel-app/.env`: `127.0.0.1:5432`, database `semadiksi`, schema `public`). Supabase is the later target; `.env.example` shows that target setup, where `DB_SEARCH_PATH` selects the schema. Laravel is not connected to Supabase yet, and its `users` table is not shared with the Next.js app: the Next.js `public.users` on Supabase has a different shape (camelCase columns, string `id`, old `student`/`admin` roles). See "Aturan Kerja" for the conditions before Laravel may touch Supabase. Tests are PHPUnit feature tests in `tests/Feature`. `phpunit.xml` forces SQLite `:memory:` so `RefreshDatabase` never wipes Supabase; keep it that way.

Seeding: `php artisan db:seed` (`DatabaseSeeder`) creates only demo accounts and demo content for local development, and throws in `production` (even with `--force`). It is idempotent: demo rows are looked up by a natural key (`sekali()` → `firstOrCreate`), so re-running neither duplicates data, fails on unique columns (certificate code), nor overwrites demo rows edited locally. Demo passwords come from `SEED_ADMIN_PASSWORD` / `SEED_MAHASISWA_PASSWORD` (read through `config/seeding.php`). When they are empty, new demo accounts get a random password printed once in the terminal, and existing accounts keep their password. The first production `super_admin` will be created through a separate interactive artisan command (not built yet), never through the seeder.

`ReferensiAkademikSeeder` is different: it holds reference data (fakultas & program studi UNUSA), is idempotent (`updateOrCreate` per kode), **may run in production**, runs automatically from the migration that creates those tables, and can be re-run with `php artisan semadiksi:referensi-akademik`.

`.env.example` is tracked through `!.env.example` in `laravel-app/.gitignore` (the root `.gitignore` of the Next.js app ignores `.env*`). Keep it free of real credentials and add every new `env()` variable the app reads.

Architecture notes:

- **Hybrid views**: most pages are Blade (`resources/views/**`), while newer screens (admin kegiatan index/detail, pengguna, penilaian, `Dashboard/KegiatanSaya`, `Kegiatan/Show`) are React pages in `resources/js/Pages/**` rendered via Inertia, with layouts in `resources/js/Layouts`.
- **Inertia is vendored, not a composer package**: the server side is an in-house shim in `app/Inertia/` (namespace `Inertia\`, PSR-4-mapped in `composer.json`), wired through `HandleInertiaRequests`. Check that shim before relying on features of the official `inertia-laravel` package. The client is `@inertiajs/react` v3: the `@inertia` directive (in `AppServiceProvider`) renders the first page as `<script data-page="app" type="application/json">…</script>` followed by `<div id="app">`, not a `data-page` attribute. Tests read page props by parsing that script tag. The shim resolves nested closure props.
- **Admin menu**: `App\Support\MenuAdmin` is the single source for both `layouts/admin.blade.php` and `AdminLayout.jsx` (shared Inertia prop `menu_admin`). A link is shown only if its route middleware (`admin`, `role:...`) admits the user's role, so register new admin pages there instead of hard-coding links.
- **Kegiatan form**: field names in `resources/js/lib/kegiatanForm.js` equal the `kegiatan` column names (validated in `Admin\KegiatanController::kegiatanRules()`); only uploads differ (`panduan_file`, `certificate_template_file`). Updates are partial (omitted fields keep their value). Empty lists are sent as `null` (`kegiatanKePayload`) because Inertia's FormData drops empty arrays when a file is attached. The UNUSA facility choice is stored in `lokasi_kampus/lokasi_gedung/lokasi_ruangan` (only for `lokasi_type = unusa_campus`, validated against `UnusaFacilityService::listCampuses()`).
- **Seat layouts** are created and changed only through `App\Services\Kegiatan\DenahKursiService`, used by the kegiatan form and `PUT /admin/kegiatan/{id}/venue-config`: capacity check against seats held by active registrations (same `capacity` error), reset of held seats with `seat_history` and `needs_seat_reselection`, `venue_change_count`, and `SendSeatReselectionNotificationJob` dispatched `afterCommit`.
- **User forms**: one Form Request per role in `app/Http/Requests/Pengguna` (base `PenggunaRequest`, resolved by `UserManagementController::formUntuk()`); the public `/daftar` umum path reuses `UserUmumRequest::aturan()`. The fields each role sends are mirrored in `FIELD_PER_ROLE` in `resources/js/lib/penggunaForm.js`.
- **NIM uniqueness**: `users.is_unusa` (boolean) marks UNUSA student accounts; `mahasiswa_kipk` accounts are always `is_unusa`, as are accounts from the UNUSA registration path. NIM is unique only among `is_unusa` accounts: partial unique index `users_nim_unusa_unique` (PostgreSQL and SQLite) plus `PenggunaRequest::aturanNimUnusaUnik()`. Participants from other campuses only need a unique email. Any lookup of a user by NIM (login, UNUSA activation, KIP sync, password reset) must add `where('is_unusa', true)`.
- **Verifikator scope**: `verifikator_level` (`fakultas`|`prodi`) plus `fakultas_id`/`program_studi_id`; filtering goes through `User::scopeDalamCakupan()` / `mencakupMahasiswa()`. The custom forms they process are `config('unusa.verifikator_form_keys')` (default `monev`, `pelaporan`, read via `CustomFormSubmission::formVerifikator()`); `pencairan` is always super_admin only. Verifikator emails must use a domain in `unusa.verifikator_email_domains`. Super admin sets fakultas/prodi of legacy `mahasiswa_kipk` accounts via `POST /admin/pengguna/{id}/akademik` (audited).
- **Juri**: lomba assignments live in the pivot `kegiatan_penilai` (`Kegiatan::penilai()`, `User::lombaDinilai()`); access is checked by `KegiatanPolicy::nilaiKarya`.
- **KIP-K data**: `PenerimaKipk.nomor_pendaftaran_kip` (SIM KIP Kuliah registration number) uses the `encrypted` cast; lookups go through `nomor_kip_hash` (HMAC-SHA256 with `APP_KEY` of the digits-only number, `App\Support\NomorKip`). The full number is shown only to super_admin through an audited action and must never appear in logs, error messages, or other responses. Never scrape the SIM KIP portal or ask for SIM KIP passwords. Data sources are swappable providers: `UnusaAcademicProvider` and `KipRecipientProvider` contracts, chosen by `config('unusa.academic_provider')` / `config('unusa.kip_recipient_provider')` (`database` = CSV imports, `api` = skeleton) in `AppServiceProvider`.
- **Password reset** (`LupaKataSandiController`, `/lupa-kata-sandi`): Laravel password broker tokens; the request response is always neutral; rate limited per IP and per email/NIM; blocked accounts can't reset.
- **Tests never need the internet**: `Tests\TestCase` calls `Http::preventStrayRequests()`, and `phpunit.xml` sets `VALIDASI_EMAIL_DNS=false` so `email:rfc,dns` rules (`PenggunaRequest::aturanFormatEmailDns()`) skip the DNS lookup only in tests. Sessions use JSON serialization, so `session('errors')` in tests can be an array, not a `ViewErrorBag`.
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
