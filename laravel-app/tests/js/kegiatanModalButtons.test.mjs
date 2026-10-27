// Jalankan dari laravel-app/: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

function ModalFooterButtons({ currentStep, editingItem, processing, adaSuperAdminLain, onSimpan }) {
    if (currentStep < 5) {
        return React.createElement('button', { type: 'button' }, `Lanjut ke Langkah ${currentStep + 1}`);
    }

    if (editingItem) {
        return React.createElement(
            'div',
            { className: 'flex flex-col items-end gap-1' },
            React.createElement(
                'button',
                {
                    type: 'button',
                    disabled: processing,
                    onClick: () => onSimpan('draft'),
                    id: 'btn-simpan-perubahan',
                },
                'Simpan Perubahan',
            ),
            React.createElement(
                'p',
                { className: 'text-[11px] text-slate-400', id: 'petunjuk-ajukan-edit' },
                'Untuk mengajukan persetujuan, gunakan tombol Ajukan di daftar kegiatan',
            ),
        );
    }

    return React.createElement(
        'div',
        { className: 'flex flex-col sm:flex-row items-end sm:items-center gap-2' },
        React.createElement(
            'button',
            {
                type: 'button',
                disabled: processing,
                onClick: () => onSimpan('draft'),
                id: 'btn-simpan-draft',
            },
            'Simpan sebagai Draft',
        ),
        React.createElement(
            'div',
            { className: 'relative group' },
            React.createElement(
                'button',
                {
                    type: 'button',
                    disabled: processing || !adaSuperAdminLain,
                    onClick: () => onSimpan('ajukan'),
                    id: 'btn-simpan-ajukan',
                },
                'Simpan & Ajukan Persetujuan',
            ),
        ),
    );
}

test('Mode edit hanya menampilkan "Simpan Perubahan" dan petunjuk pengajuan', () => {
    const html = renderToStaticMarkup(
        React.createElement(ModalFooterButtons, {
            currentStep: 5,
            editingItem: { id: 12, nama_kegiatan: 'Seminar IT' },
            processing: false,
            adaSuperAdminLain: true,
            onSimpan: () => {},
        }),
    );

    assert.ok(html.includes('Simpan Perubahan'), 'Harus menampilkan tombol "Simpan Perubahan"');
    assert.ok(
        html.includes('Untuk mengajukan persetujuan, gunakan tombol Ajukan di daftar kegiatan'),
        'Harus menampilkan petunjuk pengajuan tombol Ajukan',
    );
    assert.ok(!html.includes('Simpan sebagai Draft'), 'Tidak boleh menampilkan "Simpan sebagai Draft"');
    assert.ok(!html.includes('Simpan &amp; Ajukan Persetujuan') && !html.includes('Simpan & Ajukan Persetujuan'), 'Tidak boleh menampilkan "Simpan & Ajukan Persetujuan"');
});

test('Mode tambah baru menampilkan dua tombol: "Simpan sebagai Draft" dan "Simpan & Ajukan Persetujuan"', () => {
    const html = renderToStaticMarkup(
        React.createElement(ModalFooterButtons, {
            currentStep: 5,
            editingItem: null,
            processing: false,
            adaSuperAdminLain: true,
            onSimpan: () => {},
        }),
    );

    assert.ok(html.includes('Simpan sebagai Draft'), 'Harus menampilkan "Simpan sebagai Draft"');
    assert.ok(
        html.includes('Simpan &amp; Ajukan Persetujuan') || html.includes('Simpan & Ajukan Persetujuan'),
        'Harus menampilkan "Simpan & Ajukan Persetujuan"',
    );
    assert.ok(!html.includes('Simpan Perubahan'), 'Tidak boleh menampilkan "Simpan Perubahan" di mode tambah');
    assert.ok(!html.includes('Untuk mengajukan persetujuan, gunakan tombol Ajukan di daftar kegiatan'), 'Tidak boleh menampilkan petunjuk pengajuan edit di mode tambah');
});

test('Index.jsx: struktur tombol mode edit dan petunjuk terdapat di file sumber', () => {
    const berkas = join(dirname(fileURLToPath(import.meta.url)), '../../resources/js/Pages/Admin/Kegiatan/Index.jsx');
    const kode = readFileSync(berkas, 'utf8');

    assert.ok(kode.includes('editingItem ? ('), 'Pemeriksaan kondisi editingItem harus ada');
    assert.ok(kode.includes('Simpan Perubahan'), 'Teks "Simpan Perubahan" harus ada di Index.jsx');
    assert.ok(
        kode.includes('Untuk mengajukan persetujuan, gunakan tombol Ajukan di daftar kegiatan'),
        'Teks petunjuk tombol Ajukan harus ada di Index.jsx',
    );
});
