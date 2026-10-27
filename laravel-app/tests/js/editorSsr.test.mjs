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

const render = (templatProps, props = {}) => renderToStaticMarkup(React.createElement(Editor, { kegiatanId: 7, templat: templatProps, ...props }));
const SIAP_BERSIH = [/belum disimpan/i, /terlalu panjang/i, /maksimal/i, /role="alert"/];

test('editor SSR: templat kosong tidak menampilkan galat atau status berubah dan Simpan nonaktif', () => {
    const html = render(templatContoh());

    for (const pola of SIAP_BERSIH) assert.doesNotMatch(html, pola, `keadaan awal tidak boleh memuat ${pola}`);
    assert.match(html, /<button[^>]*disabled[^>]*>Simpan<\/button>/, 'Simpan nonaktif selama tidak ada perubahan');
});

test('editor SSR: templat tersimpan dan teks bawaan juga bersih pada keadaan awal', () => {
    const tersimpan = templatContoh({
        tersimpan: true, versi: 3, latar_url: '/admin/kegiatan/7/sertifikat/latar?v=3',
        teks: { ...teksContoh, peserta: { judul: 'PIAGAM LOMBA ESSAY', pengantar: 'Dengan bangga diberikan kepada', penutup: 'Atas juara pada {kegiatan}.' } },
    });
    const panjang = templatContoh({ teks: { ...teksContoh, juara: { judul: 'J'.repeat(150), pengantar: 'P'.repeat(200), penutup: 'K'.repeat(600) } } });

    for (const html of [render(tersimpan), render(panjang)]) {
        for (const pola of SIAP_BERSIH) assert.doesNotMatch(html, pola);
    }
});

test('editor SSR: satu layar sederhana, tanpa tab jenis, orientasi, daftar elemen, atau penimpa per jenis', () => {
    const html = render(templatContoh({ penimpa: { juri: { layout: layoutContoh(), teks: {}, orientasi: 'landscape', versi: 1 } } }), { kegiatanLain: [{ id: 3, nama_kegiatan: 'Seminar Lama' }] });

    assert.match(html, /aspect-ratio:297 \/ 210/);
    assert.match(html, /Nama Peserta Contoh/);
    assert.match(html, /Kode QR contoh/);
    for (const label of ['Simpan', 'Pratinjau PDF', 'Unggah Latar', 'Kembalikan ke Bawaan']) assert.ok(html.includes(label), `tombol '${label}' tidak ada`);
    for (const hilang of ['Bawaan semua jenis', 'role="tablist"', 'Gunakan tata letak berbeda', 'Hapus tata letak khusus', 'Orientasi A4', 'orientasi-templat', 'Seminar Lama', 'Reset ke bawaan', 'Hapus latar', 'aria-pressed', 'memakai tata letak khusus']) {
        assert.ok(!html.includes(hilang), `'${hilang}' seharusnya sudah tidak ada`);
    }
    assert.doesNotMatch(html, /undefined|NaN/);
});

test('editor SSR: orientasi mengikuti templat (latar), potret menghasilkan kanvas 210 x 297', () => {
    assert.match(render(templatContoh({ orientasi: 'portrait' })), /aspect-ratio:210 \/ 297/);
    assert.match(render(templatContoh({ latar_url: '/admin/kegiatan/7/sertifikat/latar?v=2' })), /background-image:url\(\/admin\/kegiatan\/7\/sertifikat\/latar\?v=2\)/);
});

test('editor SSR: pengeditan teks ada di bagian lipat tertutup dengan penghitung per kolom', () => {
    const html = render(templatContoh());

    assert.match(html, /<details(?![^>]*\bopen\b)[^>]*>\s*<summary[^>]*>Ubah teks sertifikat \(opsional\)<\/summary>/);
    assert.match(html, /18\/150/, 'penghitung judul bawaan "SERTIFIKAT PESERTA"');
    assert.match(html, /\/200/);
    assert.match(html, /\/600/);
    assert.doesNotMatch(html, /text-rose-[0-9]+[^"]*">\d+\/\d+/, 'penghitung tidak merah selama tidak melebihi');
});

test('editor SSR: panel elemen terpilih; nama dan QR tidak punya tombol Sembunyikan, elemen opsional punya', () => {
    const panel = (kunci) => render(templatContoh(), { terpilihAwal: kunci });

    assert.match(panel('nama'), /Nama penerima/);
    assert.ok(!panel('nama').includes('Sembunyikan'));
    assert.match(panel('qr'), /Ukuran \(mm\)/);
    assert.ok(!panel('qr').includes('Sembunyikan'));
    assert.ok(!panel('nomor_seri').includes('Sembunyikan'));
    assert.match(panel('tanggal'), />Sembunyikan</);
    assert.match(panel('tanggal'), /Ukuran huruf/);
    assert.match(panel('tanggal'), /Rata teks/);
    assert.match(panel('tanggal'), /Warna/);
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
