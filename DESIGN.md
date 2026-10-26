# DESIGN.md - SEMADIKSI Portal Design Direction

## 1. Identity & Purpose
- **Brand**: SEMADIKSI UNUSA (Serikat Mahasiswa KIP-Kuliah dan Bidikmisi Universitas Nahdlatul Ulama Surabaya).
- **Domain**: Portal layanan kemahasiswaan terpadu (registrasi kegiatan, presensi 2-fase, e-sertifikat terverifikasi, pelaporan beasiswa, seleksi/penilaian lomba).
- **Personality**: Akademik, terpercaya, terstruktur, hangat dan bersahabat ("Seduluran Selawase").
- **Voice & Tone**: Lugas, informatif, jujur, tanpa kata-kata klise pemasaran AI (*seamless, revolusioner, game-changer*), tanpa metrik fiktif.

## 2. Palette & Tokens
- **Primary Core**: UNUSA Deep Green (`#0d631b`, container `#2e7d32`, on-primary `#ffffff`).
- **Accent**: Warm Gold / Amber (`#795900`, container `#fec330`, on-secondary `#ffffff`).
- **Surfaces & Background**:
  - Light theme: Clean neutral `#f9f9f9` (background), `#ffffff` (card surface), `#e2e2e2` (subtle border).
  - Admin dark theme: Slate `#0f172a` (background), `#1e293b` (card surface), `#334155` (border).
- **Text & Contrast**:
  - Primary text: `#1a1c1c` (contrast ratio $> 14:1$ on light surface, passes WCAG AAA).
  - Secondary/Label text: `#40493d` / `#555e51` (contrast ratio $> 5.5:1$, passes WCAG AA).
  - Focus Ring: High contrast `ring-2 ring-primary ring-offset-2`.

## 3. Typography
- **Primary Font**: Plus Jakarta Sans.
- **Hierarchy**:
  - H1 / Hero: 32px - 44px, bold/extrabold, line-height 1.2.
  - H2 / Section: 24px - 30px, bold.
  - H3 / Card Title: 16px - 18px, semibold/bold.
  - Body: 14px - 16px, regular (400) / medium (500), leading 1.6.
  - Labels & Meta: 12px - 13px, medium/semibold.

## 4. Layout & Components
- **No Empty Decorations**: Every card, button, and link must have a working destination and real function.
- **No Artificial Hero Bento**: The hero section must feature authentic portal shortcuts and real data, not non-clickable mock cards pretending to be an app.
- **Honest Media**: Event cards with no custom poster must render clean typographic branding with date and venue, not generic foreign stock photos.
- **Border Radius**: Consistent, restrained scale (`rounded-xl` for buttons/inputs, `rounded-2xl` for cards, no gratuitous `rounded-full` everywhere).
- **Elevation**: Flat by default with clean borders (`border border-surface-variant`), subtle `shadow-sm` on elevated cards. No oversized blurry floating shadows.

## 5. Dials
- **ENERGY**: 2 (Seimbang, profesional, fokus pada kejelasan tugas pengguna).
- **RHYTHM**: 2 (Tata letak teratur dengan ruang napas proporsional antar seksi).
- **MOTION**: 1 (Mikro-interaksi halus pada tombol dan kartu, tanpa efek mengambang yang berlebihan).
