// Render SSR komponen editor dengan data contoh (tanpa paket baru: memakai vite dan react yang sudah terpasang).
// Jalankan dari laravel-app/: node --test tests/js
import { after, before, test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const req = createRequire(join(root, 'package.json'));
const muat = async (nama) => import(pathToFileURL(req.resolve(nama)).href);

let server;
let React;
let renderToStaticMarkup;
let Editor;
let Aksi;

const ELEMEN = ['judul', 'pengantar', 'nama', 'asal_institusi', 'jenis_predikat', 'kegiatan', 'tanggal', 'nomor_seri', 'qr', 'penandatangan_1', 'penandatangan_2', 'penandatangan_3', 'penandatangan_4'];

function layoutContoh() {
    return Object.fromEntries(ELEMEN.map((kunci, i) => [kunci, {
        x: 10, y: 5 + i * 6, lebar: kunci === 'qr' ? 10 : 40, tinggi: kunci === 'qr' ? 14 : 5, ukuran_font: 12,
        rata: 'tengah', tebal: false, warna: '#1a1c1c', font: 'DejaVu Sans', tampil: !kunci.startsWith('penandatangan_3') && !kunci.startsWith('penandatangan_4'),
    }]));
}

const teksContoh = Object.fromEntries(['peserta', 'juri', 'pembicara', 'juara'].map((j) => [j, {
    judul: `SERTIFIKAT ${j.toUpperCase()}`, pengantar: 'Diberikan kepada', penutup: 'Atas partisipasi dalam {kegiatan} pada {tanggal}.',
}]));

const templatContoh = (tambahan = {}) => ({
    orientasi: 'landscape',
    layout: layoutContoh(),
    teks: teksContoh,
    latar_url: null,
    versi: 0,
    tersimpan: false,
    penimpa: {},
    bawaan: { landscape: layoutContoh(), portrait: layoutContoh(), teks: teksContoh },
    aturan: {
        font: ['DejaVu Sans', 'Times'], elemen: ELEMEN, wajib: ['nama', 'qr', 'nomor_seri'],
        penampung: ['nama', 'asal_institusi', 'jenis', 'predikat', 'kegiatan', 'tanggal', 'nomor'],
        qr_min_mm: 18, qr_maks_mm: 60, margin_qr_mm: 5,
    },
    ...tambahan,
});

before(async () => {
    const { createServer } = await muat('vite');
    const react = (await muat('@vitejs/plugin-react')).default;
    server = await createServer({
        root,
        configFile: false,
        logLevel: 'error',
        plugins: [react()],
        resolve: { alias: { '@': join(root, 'resources/js') } },
        server: { middlewareMode: true },
        appType: 'custom',
    });
    React = (await muat('react')).default;
    ({ renderToStaticMarkup } = await muat('react-dom/server'));
    Editor = (await server.ssrLoadModule('/resources/js/Components/Admin/EditorTemplatSertifikat.jsx')).default;
    Aksi = (await server.ssrLoadModule('/resources/js/Components/Admin/AksiPersetujuanKegiatan.jsx')).default;
});

after(async () => {
    await server?.close();
});

test('editor SSR: tata letak bawaan menampilkan kanvas A4 lanskap, elemen contoh, dan semua tombol', () => {
    const html = renderToStaticMarkup(React.createElement(Editor, { kegiatanId: 7, templat: templatContoh(), kegiatanLain: [{ id: 3, nama_kegiatan: 'Seminar Lama' }] }));

    assert.match(html, /aspect-ratio:297 \/ 210/);
    assert.match(html, /Nama Peserta Contoh/);
    assert.match(html, /Kode QR contoh/);
    assert.match(html, /SERTIFIKAT PESERTA/);
    assert.match(html, /Atas partisipasi dalam Nama Kegiatan Contoh pada 1 Januari 2026\./, 'penampung diisi data contoh');
    for (const label of ['Simpan', 'Pratinjau PDF', 'Unggah latar', 'Reset ke bawaan', 'Seminar Lama', 'Bawaan semua jenis', 'Peserta', 'Juri', 'Pembicara', 'Juara']) {
        assert.ok(html.includes(label), `label '${label}' tidak ada`);
    }
    assert.match(html, /Properti: Nama penerima/);
    assert.doesNotMatch(html, /undefined|NaN/, 'tidak ada nilai undefined/NaN di keluaran');
});

test('editor SSR: orientasi potret dan penimpa per jenis ditandai di tab', () => {
    const html = renderToStaticMarkup(React.createElement(Editor, {
        kegiatanId: 7,
        templat: templatContoh({ orientasi: 'portrait', penimpa: { juri: { layout: layoutContoh(), teks: {}, orientasi: 'portrait', versi: 1 } } }),
    }));

    assert.match(html, /aspect-ratio:210 \/ 297/);
    assert.match(html, /Juri \*/);
    assert.doesNotMatch(html, /Peserta \*/);
});

test('editor SSR: latar tampil sebagai gambar latar kanvas dan tombol hapus latar muncul', () => {
    const html = renderToStaticMarkup(React.createElement(Editor, { kegiatanId: 7, templat: templatContoh({ latar_url: '/admin/kegiatan/7/sertifikat/latar?v=2' }) }));

    assert.match(html, /background-image:url\(\/admin\/kegiatan\/7\/sertifikat\/latar\?v=2\)/);
    assert.match(html, /Hapus latar/);
});

test('tombol persetujuan SSR: label sesuai peran dan status', () => {
    const teks = (aksi) => renderToStaticMarkup(React.createElement(Aksi, { kegiatanId: 29, namaKegiatan: 'Lomba Essay', aksi }))
        .replace(/<span class="material-symbols-outlined[^>]*>[^<]*<\/span>/g, '')
        .replace(/<[^>]+>/g, '|').replace(/\|+/g, '|');

    assert.match(teks({ can_ajukan: true }), /Ajukan/);
    assert.match(teks({ can_tarik_draft: true }), /Tarik ke Draft/);
    assert.match(teks({ can_setujui: true, can_tolak: true }), /Setujui\|Tolak/);
    assert.match(teks({ keputusan_saya: 'setuju' }), /Anda sudah menyetujui/);
    assert.match(teks({ alasan_nonaktif: 'Menunggu diajukan oleh Ahmad' }), /Menunggu diajukan oleh Ahmad/);
});
