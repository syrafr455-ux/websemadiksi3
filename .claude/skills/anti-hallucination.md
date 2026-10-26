---
name: anti-hallucination
description: Mekanisme verifikasi berbasis bukti untuk mencegah AI berasumsi atau mengarang fungsi.
---
# PROTOKOL ANTI-HALUSINASI

1. VERIFIKASI SEBELUM MENULIS:
   - Selalu periksa file migrasi di database/migrations/ sebelum membuat query kolom database.
   - Jangan pernah menebak enum atau kolom model jika belum membaca file Model terkait.

2. ATURAN INTEGRITAS KODE:
   - Dilarang membuat fungsi dummy, mock kosong, atau // TODO: implement later di kode aplikasi inti.
   - Setiap fitur baru WAJIB disertai Feature Test otomatis di PHPUnit.

3. STATUS BUKTI:
   - Jangan melaporkan 'fitur telah berhasil diperbaiki' sebelum perintah verifikasi (misal: composer test / npm run build) dijalankan dan menghasilkan output sukses.
