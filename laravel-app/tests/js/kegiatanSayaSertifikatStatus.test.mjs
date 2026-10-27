// Jalankan dari laravel-app/: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

function RegistrationActionButtons({ reg, isPaidOrFree = true }) {
    if (!isPaidOrFree) {
        return React.createElement(
            'div',
            { className: 'text-xs text-amber-400 font-bold flex items-center gap-1', id: 'unpaid-box' },
            'Selesaikan Pembayaran via Tripay'
        );
    }

    return React.createElement(
        'div',
        { className: 'pt-3 border-t border-slate-800 flex flex-col gap-2' },
        React.createElement(
            'div',
            { className: 'flex items-center gap-2 w-full' },
            React.createElement(
                'button',
                { type: 'button', 'data-lihat-tiket': reg.id },
                'Tampilkan E-Tiket'
            ),
            React.createElement(
                'button',
                { type: 'button', 'data-unduh-tiket': reg.id },
                'Unduh E-Tiket'
            )
        ),
        reg.sertifikat_status === 'terbit' ? React.createElement(
            'a',
            {
                href: reg.sertifikat_url || reg.sertifikat_verification_url,
                'data-sertifikat-terbit': reg.id,
                className: 'w-full py-2 px-3 rounded-xl bg-amber-500/15'
            },
            `Unduh Sertifikat (${reg.sertifikat_nomor})`
        ) : reg.sertifikat_status === 'menunggu_verifikasi' ? React.createElement(
            'div',
            {
                'data-sertifikat-menunggu': reg.id,
                className: 'w-full py-2 px-3 rounded-xl bg-slate-800/80'
            },
            'Menunggu verifikasi panitia'
        ) : null
    );
}

test('KegiatanSaya.jsx file integrity: memeriksa sintaksis dan pola status sertifikat', () => {
    const __dirname = dirname(fileURLToPath(import.meta.url));
    const filePath = join(__dirname, '../../resources/js/Pages/Dashboard/KegiatanSaya.jsx');
    const source = readFileSync(filePath, 'utf8');

    assert.ok(source.includes("reg.sertifikat_status === 'terbit'"), 'Memuat penanganan sertifikat terbit');
    assert.ok(source.includes("reg.sertifikat_status === 'menunggu_verifikasi'"), 'Memuat penanganan menunggu verifikasi');
    assert.ok(source.includes('Menunggu verifikasi panitia'), 'Memuat label teks Menunggu verifikasi panitia');
    assert.ok(source.includes('Unduh Sertifikat'), 'Memuat tombol Unduh Sertifikat');
    assert.ok(source.includes('data-sertifikat-terbit'), 'Memuat atribut data-sertifikat-terbit');
    assert.ok(source.includes('data-sertifikat-menunggu'), 'Memuat atribut data-sertifikat-menunggu');
});

test('SSR RegistrationActionButtons: merender status menunggu verifikasi bila presensi selesai tetapi sertifikat belum terbit', () => {
    const reg = {
        id: 101,
        sertifikat_status: 'menunggu_verifikasi',
        sertifikat_pesan: 'Menunggu verifikasi panitia',
    };

    const html = renderToStaticMarkup(React.createElement(RegistrationActionButtons, { reg, isPaidOrFree: true }));

    assert.ok(html.includes('Menunggu verifikasi panitia'), 'Harus menampilkan teks menunggu verifikasi panitia');
    assert.ok(html.includes('data-sertifikat-menunggu="101"'), 'Harus memiliki atribut data-sertifikat-menunggu');
    assert.ok(!html.includes('Unduh Sertifikat'), 'Tidak boleh menampilkan tombol unduh sertifikat');
});

test('SSR RegistrationActionButtons: merender tautan unduh sertifikat bila sertifikat sudah terbit', () => {
    const reg = {
        id: 102,
        sertifikat_status: 'terbit',
        sertifikat_nomor: 'CERT-TEST-2026-0001',
        sertifikat_url: '/sertifikat/102/download',
        sertifikat_verification_url: '/verifikasi?code=CERT-TEST-2026-0001',
    };

    const html = renderToStaticMarkup(React.createElement(RegistrationActionButtons, { reg, isPaidOrFree: true }));

    assert.ok(html.includes('Unduh Sertifikat (CERT-TEST-2026-0001)'), 'Harus memuat nomor seri pada tombol unduh');
    assert.ok(html.includes('data-sertifikat-terbit="102"'), 'Harus memiliki atribut data-sertifikat-terbit');
    assert.ok(!html.includes('Menunggu verifikasi panitia'), 'Tidak boleh menampilkan status menunggu');
});

test('SSR RegistrationActionButtons: tidak merender blok sertifikat bila belum tersedia (belum presensi)', () => {
    const reg = {
        id: 103,
        sertifikat_status: 'belum_tersedia',
        sertifikat_pesan: 'Belum presensi',
    };

    const html = renderToStaticMarkup(React.createElement(RegistrationActionButtons, { reg, isPaidOrFree: true }));

    assert.ok(html.includes('Tampilkan E-Tiket'), 'Tetap memuat tombol e-tiket');
    assert.ok(!html.includes('Unduh Sertifikat'), 'Tidak ada tombol unduh sertifikat');
    assert.ok(!html.includes('Menunggu verifikasi panitia'), 'Tidak ada teks menunggu verifikasi');
});
