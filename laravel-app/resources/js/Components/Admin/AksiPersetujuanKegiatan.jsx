import React, { useState } from 'react';
import { router } from '@inertiajs/react';

const TOMBOL = 'px-3 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1 transition-colors disabled:opacity-60';

/**
 * Tombol alur persetujuan kegiatan (Ajukan, Tarik ke Draft, Setujui, Tolak) untuk satu baris daftar
 * atau header halaman detail. `aksi` berisi can_ajukan, can_tarik_draft, can_setujui, can_tolak,
 * keputusan_saya, dan alasan_nonaktif dari App\Support\AksiPersetujuan. Server tetap memeriksa ulang
 * setiap permintaan, jadi komponen ini hanya menentukan apa yang ditampilkan.
 */
export default function AksiPersetujuanKegiatan({ kegiatanId, namaKegiatan, aksi = {} }) {
    const [modalTolak, setModalTolak] = useState(false);
    const [catatan, setCatatan] = useState('');
    const [memproses, setMemproses] = useState(false);

    const kirim = (jalur, data = {}, opsi = {}) => {
        setMemproses(true);
        router.post(`/admin/kegiatan/${kegiatanId}/${jalur}`, data, {
            preserveScroll: true,
            onFinish: () => setMemproses(false),
            ...opsi,
        });
    };

    const bukaModalTolak = () => {
        setCatatan('');
        setModalTolak(true);
    };

    const kirimTolak = () => kirim('tolak', { catatan }, { onSuccess: () => setModalTolak(false) });

    return (
        <div className="flex flex-wrap items-center gap-2">
            {aksi.can_ajukan && (
                <button
                    type="button"
                    disabled={memproses}
                    onClick={() => {
                        if (window.confirm(`Ajukan kegiatan "${namaKegiatan}" untuk ditinjau Super Admin lain?`)) kirim('ajukan');
                    }}
                    className={`${TOMBOL} bg-emerald-600 hover:bg-emerald-500 text-white`}
                    title="Ajukan ke Super Admin lain untuk disetujui"
                >
                    <span className="material-symbols-outlined text-sm">send</span>
                    Ajukan
                </button>
            )}

            {aksi.can_tarik_draft && (
                <button
                    type="button"
                    disabled={memproses}
                    onClick={() => {
                        if (window.confirm(`Tarik "${namaKegiatan}" kembali ke draft? Persetujuan yang sudah masuk dihapus.`)) kirim('tarik-draft');
                    }}
                    className={`${TOMBOL} bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/30`}
                    title="Kembalikan ke draft agar bisa diedit"
                >
                    <span className="material-symbols-outlined text-sm">undo</span>
                    Tarik ke Draft
                </button>
            )}

            {aksi.can_setujui && (
                <button
                    type="button"
                    disabled={memproses}
                    onClick={() => {
                        if (window.confirm(`Setujui kegiatan "${namaKegiatan}"?`)) kirim('setujui');
                    }}
                    className={`${TOMBOL} bg-emerald-600 hover:bg-emerald-500 text-white`}
                    title="Setujui penerbitan kegiatan ini"
                >
                    <span className="material-symbols-outlined text-sm">check_circle</span>
                    Setujui
                </button>
            )}

            {aksi.can_tolak && (
                <button
                    type="button"
                    disabled={memproses}
                    onClick={bukaModalTolak}
                    className={`${TOMBOL} bg-rose-600 hover:bg-rose-500 text-white`}
                    title="Tolak dan kembalikan ke draft dengan catatan"
                >
                    <span className="material-symbols-outlined text-sm">cancel</span>
                    Tolak
                </button>
            )}

            {aksi.keputusan_saya === 'setuju' && (
                <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                    Anda sudah menyetujui
                </span>
            )}
            {aksi.keputusan_saya === 'tolak' && (
                <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30">
                    Anda menolak
                </span>
            )}

            {aksi.alasan_nonaktif && (
                <div className="flex flex-col items-start gap-0.5">
                    <button
                        type="button"
                        disabled
                        aria-disabled="true"
                        title={aksi.alasan_nonaktif}
                        className={`${TOMBOL} bg-slate-800 text-slate-500 border border-slate-700 cursor-not-allowed`}
                    >
                        <span className="material-symbols-outlined text-sm">check_circle</span>
                        Setujui
                    </button>
                    <span className="text-[10px] text-slate-400">{aksi.alasan_nonaktif}</span>
                </div>
            )}

            {modalTolak && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 text-left"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={`judul-tolak-${kegiatanId}`}
                >
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 space-y-4 whitespace-normal">
                        <h3 id={`judul-tolak-${kegiatanId}`} className="text-base font-bold text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-rose-400">cancel</span>
                            Tolak "{namaKegiatan}"
                        </h3>
                        <p className="text-xs text-slate-300">
                            Penolakan mengembalikan kegiatan ke <strong>Draft</strong> dan menghapus semua persetujuan yang sudah masuk.
                            Tulis alasannya agar pembuat tahu apa yang perlu diperbaiki (minimal 5 karakter).
                        </p>
                        <textarea
                            value={catatan}
                            onChange={(e) => setCatatan(e.target.value)}
                            placeholder="Contoh: Tanggal pelaksanaan bentrok dengan ujian akhir semester."
                            rows={3}
                            className="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"
                        />
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setModalTolak(false)}
                                className="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                disabled={memproses || catatan.trim().length < 5}
                                onClick={kirimTolak}
                                className="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white text-xs font-bold"
                            >
                                Tolak Kegiatan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
