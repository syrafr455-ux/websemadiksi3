// Keadaan editor templat: normalisasi, deteksi perubahan, dan galat per kolom. Jalankan: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    BATAS_TEKS,
    aturSisiQr,
    adaPerubahan,
    galatTeks,
    jepitElemen,
    normalisasiKeadaan,
    panjangTeks,
    elemenTerhapus,
    peringatanHapus,
    pulihkanElemen,
    sembunyikanElemen,
    tombolHapusDitekan,
    sisiQrMm,
    teksUntukDisimpan,
} from '../../resources/js/lib/templatSertifikat.js';

const ELEMEN = ['judul', 'pengantar', 'nama', 'asal_institusi', 'jenis_predikat', 'kegiatan', 'tanggal', 'nomor_seri', 'qr', 'penandatangan_1', 'penandatangan_2', 'penandatangan_3', 'penandatangan_4'];
const JENIS = ['peserta', 'juri', 'pembicara', 'juara'];

const layoutBawaan = () => Object.fromEntries(ELEMEN.map((k, i) => [k, {
    rata: 'tengah', tebal: false, warna: '#1a1c1c', font: 'DejaVu Sans', tampil: !k.startsWith('penandatangan_3') && !k.startsWith('penandatangan_4'),
    x: 10, y: 5 + i * 6, lebar: k === 'qr' ? 10 : 40, tinggi: k === 'qr' ? 14 : 5, ukuran_font: 12,
}]));
const teksBawaan = () => Object.fromEntries(JENIS.map((j) => [j, { judul: `SERTIFIKAT ${j.toUpperCase()}`, pengantar: 'Diberikan kepada', penutup: 'Atas partisipasi dalam kegiatan {kegiatan}.' }]));
const templat = (tambahan = {}) => ({ orientasi: 'landscape', layout: layoutBawaan(), teks: teksBawaan(), ...tambahan });

test('templat kosong (bawaan sistem): keadaan awal tidak berbeda dari dirinya dan tidak ada galat', () => {
    const awal = normalisasiKeadaan(templat(), teksBawaan());
    const sekarang = normalisasiKeadaan(templat(), teksBawaan());

    assert.equal(adaPerubahan(awal, sekarang), false);
    assert.deepEqual(galatTeks(templat().teks, templat().teks), {});
});

test('normalisasi menyamakan angka dan string, urutan kunci, huruf besar warna, null dan string kosong', () => {
    const asli = templat();
    const acak = templat();
    acak.layout = Object.fromEntries(Object.entries(acak.layout).reverse().map(([k, el]) => [k, {
        tampil: el.tampil, font: el.font, warna: el.warna.toUpperCase(), tebal: el.tebal === false ? null : el.tebal, rata: el.rata,
        ukuran_font: String(el.ukuran_font), tinggi: String(el.tinggi), lebar: String(el.lebar), y: String(el.y), x: String(el.x),
    }]));
    acak.teks = { peserta: { judul: '', pengantar: null, penutup: '   ' }, ...Object.fromEntries(JENIS.slice(1).map((j) => [j, teksBawaan()[j]])) };

    assert.equal(adaPerubahan(normalisasiKeadaan(asli, teksBawaan()), normalisasiKeadaan(acak, teksBawaan())), false);
});

test('templat tersimpan dengan teks sendiri: tidak ada galat dan tidak ada perubahan pada keadaan awal', () => {
    const tersimpan = templat({ teks: { ...teksBawaan(), peserta: { judul: 'PIAGAM LOMBA ESSAY', pengantar: 'Dengan bangga diberikan kepada', penutup: 'Atas juara.' } } });
    const awal = normalisasiKeadaan(tersimpan, teksBawaan());

    assert.equal(adaPerubahan(awal, normalisasiKeadaan(structuredClone(tersimpan), teksBawaan())), false);
    assert.deepEqual(galatTeks(tersimpan.teks, tersimpan.teks), {});
    assert.equal(awal.teks.peserta.judul, 'PIAGAM LOMBA ESSAY');
});

test('perubahan nyata terdeteksi: geser elemen, ubah teks, dan ubah warna', () => {
    const awal = normalisasiKeadaan(templat(), teksBawaan());
    const geser = templat(); geser.layout.nama.x = 11;
    const teks = templat(); teks.teks.juri.judul = 'PIAGAM JURI KHUSUS';
    const warna = templat(); warna.layout.judul.warna = '#ff0000';

    for (const berubah of [geser, teks, warna]) assert.equal(adaPerubahan(awal, normalisasiKeadaan(berubah, teksBawaan())), true);
});

test('galat teks hanya muncul setelah kolom diubah dan benar-benar melebihi batas, dengan pesan manusiawi', () => {
    const awal = teksBawaan();
    const sekarang = structuredClone(awal);

    assert.deepEqual(galatTeks(sekarang, awal), {});

    sekarang.peserta.judul = 'J'.repeat(150);
    assert.deepEqual(galatTeks(sekarang, awal), {}, 'tepat 150 masih sah');

    sekarang.peserta.judul = 'J'.repeat(162);
    assert.deepEqual(galatTeks(sekarang, awal), { 'peserta.judul': 'Judul terlalu panjang (162 dari 150 karakter)' });

    sekarang.peserta.pengantar = 'P'.repeat(201);
    sekarang.juri.penutup = 'K'.repeat(601);
    assert.deepEqual(Object.keys(galatTeks(sekarang, awal)).sort(), ['juri.penutup', 'peserta.judul', 'peserta.pengantar']);
    assert.equal(galatTeks(sekarang, awal)['juri.penutup'], 'Kalimat penutup terlalu panjang (601 dari 600 karakter)');
});

test('kolom yang tidak disentuh tidak pernah menghasilkan galat walau nilai awalnya melebihi batas', () => {
    const awal = teksBawaan();
    awal.peserta.judul = 'J'.repeat(162);

    assert.deepEqual(galatTeks(structuredClone(awal), awal), {});
});

test('batas teks sama dengan aturan server dan panjang dihitung per karakter', () => {
    assert.deepEqual(BATAS_TEKS, { judul: 150, pengantar: 200, penutup: 600 });
    assert.equal(panjangTeks('Piagam Juara Terbaik'), 20);
    assert.equal(panjangTeks('😀😀'), 2);
    assert.equal(panjangTeks(null), 0);
});

test('setelah simpan berhasil, baseline diambil dari respons server dan status kembali bersih', () => {
    const awal = normalisasiKeadaan(templat(), teksBawaan());
    const diedit = templat(); diedit.layout.nama.x = 22.5; diedit.teks.peserta.judul = 'PIAGAM BARU';
    const sekarang = normalisasiKeadaan(diedit, teksBawaan());
    assert.equal(adaPerubahan(awal, sekarang), true);

    // Respons server: angka sebagai string dan teks yang tidak diubah tidak dikirim ulang.
    const respons = templat({ teks: { ...teksBawaan(), peserta: { ...teksBawaan().peserta, judul: 'PIAGAM BARU' } } });
    respons.layout.nama = { ...respons.layout.nama, x: '22.5' };
    assert.equal(adaPerubahan(normalisasiKeadaan(respons, teksBawaan()), sekarang), false);
});

test('hanya teks yang berbeda dari bawaan dikirim ke server; kolom kosong berarti bawaan', () => {
    const teks = teksBawaan();
    teks.peserta.judul = 'PIAGAM BARU';
    teks.juri.pengantar = '';

    assert.deepEqual(teksUntukDisimpan(teks, teksBawaan()), { peserta: { judul: 'PIAGAM BARU' } });
});

test('QR diatur dalam milimeter, tetap persegi dan dalam batas 18 sampai 60 mm', () => {
    const qr = layoutBawaan().qr;
    for (const [diminta, hasil] of [[30, 30], [5, 18], [200, 60]]) {
        const el = aturSisiQr(qr, 'landscape', diminta);
        assert.ok(Math.abs(sisiQrMm(el, 'landscape') - hasil) < 0.05, `diminta ${diminta} mm`);
        assert.ok(Math.abs((el.tinggi / 100) * 210 - hasil) < 0.05, 'persegi');
        assert.equal(el.tampil, true);
    }
});

test('semua elemen bisa dihapus lewat penanda tersembunyi; dipulihkan di posisi terakhir dan tanpa perubahan bersih', () => {
    // Data dari server sudah dijepit ke halaman (QR persegi, margin); fixture disamakan.
    const layout = Object.fromEntries(Object.entries(layoutBawaan()).map(([k, el]) => [k, jepitElemen(el, k, 'landscape')]));
    const awal = normalisasiKeadaan(templat({ layout }), teksBawaan());

    for (const kunci of ELEMEN) {
        const tampilAsli = layout[kunci].tampil;
        const terhapus = sembunyikanElemen(layout, kunci, 'landscape');
        assert.equal(terhapus[kunci].tampil, false, kunci);
        assert.equal(terhapus[kunci].x, layout[kunci].x, 'posisi terakhir tetap');
        assert.equal(layout[kunci].tampil, tampilAsli, 'layout asli tidak dimutasi');

        const pulih = pulihkanElemen(terhapus, kunci, 'landscape');
        assert.equal(pulih[kunci].tampil, true, kunci);
        assert.equal(pulih[kunci].x, layout[kunci].x);
        if (layout[kunci].tampil) {
            assert.equal(adaPerubahan(awal, normalisasiKeadaan(templat({ layout: pulih }), teksBawaan())), false, `hapus lalu pulihkan ${kunci} bukan perubahan`);
            assert.equal(adaPerubahan(awal, normalisasiKeadaan(templat({ layout: terhapus }), teksBawaan())), true, `hapus ${kunci} adalah perubahan`);
        }
    }
});

test('daftar elemen yang dihapus mengikuti urutan elemen dan kosong bila tidak ada', () => {
    const layout = layoutBawaan();
    assert.deepEqual(elemenTerhapus(layout).filter((k) => !k.startsWith('penandatangan_')), []);
    const hapus = sembunyikanElemen(sembunyikanElemen(layout, 'tanggal', 'landscape'), 'asal_institusi', 'landscape');
    assert.deepEqual(elemenTerhapus(hapus).filter((k) => !k.startsWith('penandatangan_')), ['asal_institusi', 'tanggal']);
});

test('peringatan hanya muncul saat QR, nomor seri, atau nama dihapus dan hilang saat dipulihkan', () => {
    const layout = layoutBawaan();
    assert.deepEqual(peringatanHapus(layout), []);

    const tanpaQr = sembunyikanElemen(layout, 'qr', 'landscape');
    assert.deepEqual(peringatanHapus(tanpaQr), ['Tanpa kode QR atau nomor seri, sertifikat ini sulit diverifikasi oleh pihak lain.']);
    assert.deepEqual(peringatanHapus(sembunyikanElemen(tanpaQr, 'nomor_seri', 'landscape')), ['Tanpa kode QR atau nomor seri, sertifikat ini sulit diverifikasi oleh pihak lain.'], 'satu kalimat saja');

    const tanpaNama = sembunyikanElemen(layout, 'nama', 'landscape');
    assert.deepEqual(peringatanHapus(tanpaNama), ['Sertifikat tanpa nama penerima tidak bermakna.']);
    assert.equal(peringatanHapus(sembunyikanElemen(sembunyikanElemen(layout, 'nama', 'landscape'), 'qr', 'landscape')).length, 2);

    assert.deepEqual(peringatanHapus(pulihkanElemen(tanpaQr, 'qr', 'landscape')), []);
    assert.deepEqual(peringatanHapus(sembunyikanElemen(layout, 'tanggal', 'landscape')), [], 'elemen lain tidak memicu peringatan');
});

test('tombol Delete dan Backspace menghapus elemen, kecuali fokus di kolom input, textarea, select, atau isian yang dapat diedit', () => {
    const tombol = (key, tag, ekstra = {}) => ({ key, target: { tagName: tag, isContentEditable: false, ...ekstra }, ctrlKey: false, metaKey: false, altKey: false });

    assert.equal(tombolHapusDitekan(tombol('Delete', 'DIV')), true);
    assert.equal(tombolHapusDitekan(tombol('Backspace', 'DIV')), true);
    assert.equal(tombolHapusDitekan(tombol('Delete', 'BODY')), true);
    for (const tag of ['INPUT', 'TEXTAREA', 'SELECT']) {
        assert.equal(tombolHapusDitekan(tombol('Delete', tag)), false, tag);
        assert.equal(tombolHapusDitekan(tombol('Backspace', tag)), false, tag);
    }
    assert.equal(tombolHapusDitekan(tombol('Delete', 'DIV', { isContentEditable: true })), false);
    assert.equal(tombolHapusDitekan(tombol('a', 'DIV')), false);
    assert.equal(tombolHapusDitekan({ ...tombol('Delete', 'DIV'), ctrlKey: true }), false);
});

test('klik atau geser nol piksel pada elemen tidak dihitung sebagai perubahan (penyebab status berubah palsu)', async () => {
    const { geserElemen } = await import('../../resources/js/lib/templatSertifikat.js');
    const awal = templat();
    const sesudah = templat();
    sesudah.layout.nama = geserElemen(sesudah.layout.nama, 'nama', 'landscape', 0, 0);

    assert.equal(adaPerubahan(normalisasiKeadaan(awal, teksBawaan()), normalisasiKeadaan(sesudah, teksBawaan())), false);

    sesudah.layout.nama = geserElemen(sesudah.layout.nama, 'nama', 'landscape', 0.5, 0);
    assert.equal(adaPerubahan(normalisasiKeadaan(awal, teksBawaan()), normalisasiKeadaan(sesudah, teksBawaan())), true);
});
