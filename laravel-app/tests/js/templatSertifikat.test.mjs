// Jalankan dari laravel-app/: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    HALAMAN_MM,
    geserElemen,
    isiPenampung,
    jepitElemen,
    menutupiQr,
    penampungAsing,
    persenKePiksel,
    pikselKePersen,
    sisiQrMm,
    snapTengah,
    tumpangTindih,
    ubahUkuran,
} from '../../resources/js/lib/templatSertifikat.js';

const dasar = { x: 10, y: 10, lebar: 20, tinggi: 10, ukuran_font: 12, rata: 'tengah', tebal: false, warna: '#000000', font: 'DejaVu Sans', tampil: true };

test('konversi persen dan piksel bolak-balik, aman untuk kanvas nol', () => {
    assert.equal(persenKePiksel(25, 800), 200);
    assert.equal(pikselKePersen(200, 800), 25);
    assert.equal(pikselKePersen(50, 0), 0);
    assert.equal(pikselKePersen(persenKePiksel(37.5, 640), 640), 37.5);
});

test('elemen biasa dijepit ke dalam halaman tanpa mengubah ukurannya bila masih muat', () => {
    const hasil = jepitElemen({ ...dasar, x: 95, y: -5, lebar: 20, tinggi: 10 }, 'judul', 'landscape');
    assert.equal(hasil.x, 80);
    assert.equal(hasil.y, 0);
    assert.equal(hasil.lebar, 20);
    assert.ok(hasil.x + hasil.lebar <= 100 && hasil.y + hasil.tinggi <= 100);
});

test('elemen wajib tidak pernah disembunyikan', () => {
    for (const kunci of ['nama', 'qr', 'nomor_seri']) {
        assert.equal(jepitElemen({ ...dasar, tampil: false }, kunci, 'landscape').tampil, true, kunci);
    }
    assert.equal(jepitElemen({ ...dasar, tampil: false }, 'judul', 'landscape').tampil, false);
});

for (const orientasi of ['landscape', 'portrait']) {
    test(`QR ${orientasi}: persegi, 18-60 mm, margin 5 mm`, () => {
        const [w, h] = HALAMAN_MM[orientasi];
        const kasus = [
            { x: 50, y: 50, lebar: 1, tinggi: 1 },
            { x: 50, y: 50, lebar: 100, tinggi: 100 },
            { x: 100, y: 100, lebar: 20, tinggi: 20 },
            { x: 0, y: 0, lebar: 20, tinggi: 5 },
        ];
        for (const kotak of kasus) {
            const qr = jepitElemen({ ...dasar, ...kotak }, 'qr', orientasi);
            const sisiX = (qr.lebar / 100) * w;
            const sisiY = (qr.tinggi / 100) * h;
            assert.ok(Math.abs(sisiX - sisiY) < 0.05, 'persegi');
            assert.ok(sisiX >= 17.99 && sisiX <= 60.01, `sisi ${sisiX}`);
            assert.ok((qr.x / 100) * w >= 4.99 && (qr.y / 100) * h >= 4.99, 'margin kiri-atas');
            assert.ok((qr.x / 100) * w + sisiX <= w - 4.99 && (qr.y / 100) * h + sisiY <= h - 4.99, 'margin kanan-bawah');
            assert.equal(qr.tampil, true);
        }
    });
}

test('ubah ukuran QR dari pegangan sudut menjaga bentuk persegi; elemen lain bebas', () => {
    const qr = jepitElemen({ ...dasar, x: 70, y: 60, lebar: 10, tinggi: 14 }, 'qr', 'landscape');
    const besar = ubahUkuran(qr, 'qr', 'landscape', 15, 12);
    assert.ok(Math.abs((besar.lebar / 100) * 297 - (besar.tinggi / 100) * 210) < 0.05);
    assert.ok(sisiQrMm(besar, 'landscape') > sisiQrMm(qr, 'landscape'));

    const teks = ubahUkuran(dasar, 'judul', 'landscape', 50, 6);
    assert.equal(teks.lebar, 50);
    assert.equal(teks.tinggi, 6);
});

test('geser dengan panah memakai langkah dan tetap di dalam halaman', () => {
    assert.equal(geserElemen(dasar, 'judul', 'landscape', 0.5, 0).x, 10.5);
    assert.equal(geserElemen(dasar, 'judul', 'landscape', 0, -50).y, 0);
    assert.equal(geserElemen({ ...dasar, x: 79.8 }, 'judul', 'landscape', 2, 0).x, 80);
});

test('snap tengah hanya aktif dekat 50% dan dapat dimatikan', () => {
    const hampir = { ...dasar, x: 39.5, lebar: 20 };
    assert.deepEqual(snapTengah(hampir), { x: 40, garis: true });
    assert.deepEqual(snapTengah(hampir, false), { x: 39.5, garis: false });
    assert.deepEqual(snapTengah({ ...dasar, x: 10 }), { x: 10, garis: false });
});

test('tumpang tindih dengan QR hanya terdeteksi untuk elemen yang tampil', () => {
    const layout = {
        qr: { ...dasar, x: 80, y: 70, lebar: 10, tinggi: 14 },
        judul: { ...dasar, x: 75, y: 72, lebar: 20, tinggi: 5 },
        nama: { ...dasar, x: 0, y: 0, lebar: 10, tinggi: 5 },
        tanggal: { ...dasar, x: 78, y: 75, lebar: 5, tinggi: 5, tampil: false },
    };
    assert.deepEqual(menutupiQr(layout), ['judul']);
    assert.equal(tumpangTindih(layout.nama, layout.qr), false);
});

test('penampung diisi dari data contoh dan yang asing dilaporkan', () => {
    assert.equal(isiPenampung('Untuk {nama} di {kegiatan} {rahasia}', { nama: 'Siti', kegiatan: 'Seminar' }), 'Untuk Siti di Seminar {rahasia}');
    assert.deepEqual(penampungAsing('{nama} {x} {y}', ['nama']), ['x', 'y']);
    assert.deepEqual(penampungAsing(null, ['nama']), []);
});
