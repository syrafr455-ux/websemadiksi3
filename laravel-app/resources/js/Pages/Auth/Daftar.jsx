import React from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

const BARIS_KARTU = [
    ['NIM', 'nim'],
    ['Nama', 'nama'],
    ['Program Studi', 'program_studi'],
    ['Semester', 'semester'],
    ['Email UNUSA', 'email'],
    ['Nomor Telepon', 'telepon'],
];

const STATUS_PERINGATAN = ['tidak_ditemukan', 'email_tidak_diizinkan', 'terlalu_banyak', 'jeda', 'harian', 'sesi_habis'];

function Kartu({ kartu }) {
    return (
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-2xl bg-slate-950 border border-slate-800 text-sm" data-kartu-konfirmasi>
            {BARIS_KARTU.map(([label, kunci]) => (
                <div key={kunci} className="min-w-0">
                    <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{label}</dt>
                    <dd className="font-semibold text-white mt-0.5 break-all">{kartu[kunci] ?? '-'}</dd>
                </div>
            ))}
            <div className="min-w-0">
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Prestasi Tercatat</dt>
                <dd className="font-semibold text-white mt-0.5">{kartu.jumlah_prestasi} prestasi</dd>
            </div>
        </dl>
    );
}

export default function Daftar({ hasil = null, urlUmum = null }) {
    const { errors = {} } = usePage().props;
    const cari = useForm({ nim: '' });
    const kirim = useForm({});

    const status = hasil?.status;
    const kartu = hasil?.kartu;
    const peringatan = status && STATUS_PERINGATAN.includes(status);

    const submitCari = (e) => {
        e.preventDefault();
        cari.post('/daftar/unusa', { preserveScroll: true });
    };

    const submitKirim = (e) => {
        e.preventDefault();
        kirim.post('/daftar/unusa/kirim', { preserveScroll: true });
    };

    return (
        <GuestLayout judul="Daftar Akun Mahasiswa" subjudul="Masukkan NIM. Link aktivasi dikirim ke email UNUSA Anda yang tersimpan di data kampus.">
            <form onSubmit={submitCari} className="space-y-4">
                <div>
                    <label htmlFor="nim" className="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">NIM</label>
                    <input
                        id="nim"
                        type="text"
                        autoComplete="off"
                        value={cari.data.nim}
                        onChange={(e) => cari.setData('nim', e.target.value)}
                        className="w-full px-4 py-3 rounded-2xl bg-slate-950 border border-slate-700 focus:border-emerald-500 focus:outline-none text-sm font-medium text-white"
                        placeholder="Contoh: 2241010001"
                        required
                    />
                    {errors.nim && <p className="text-xs text-rose-400 font-medium mt-1">{errors.nim}</p>}
                </div>
                <button
                    type="submit"
                    disabled={cari.processing}
                    className="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-60 text-white font-bold text-sm"
                >
                    Daftar
                </button>
            </form>

            {status && status !== 'siap' && status !== 'terkirim' && (
                <div
                    role="status"
                    data-status={status}
                    className={`mt-6 p-4 rounded-2xl text-sm font-medium border ${peringatan ? 'bg-amber-500/10 border-amber-500/30 text-amber-200' : 'bg-slate-800 border-slate-700 text-slate-200'}`}
                >
                    {hasil.pesan}
                    {status === 'sudah_aktif' && (
                        <div className="mt-2 flex flex-col gap-1">
                            <a href="/masuk" className="font-bold text-emerald-400 hover:underline">Masuk ke akun Anda</a>
                            <a href="/lupa-kata-sandi" className="font-bold text-emerald-400 hover:underline">Lupa kata sandi?</a>
                        </div>
                    )}
                </div>
            )}

            {kartu && (status === 'siap' || status === 'terkirim' || status === 'jeda' || status === 'harian') && (
                <div className="mt-6 space-y-4">
                    <Kartu kartu={kartu} />
                    <p className="text-xs text-slate-400">Data pribadi disamarkan demi keamanan. Detail lengkap tidak ditampilkan sebelum akun aktif.</p>

                    {status === 'terkirim' && (
                        <div role="status" data-status="terkirim" className="p-4 rounded-2xl text-sm font-medium bg-emerald-500/10 border border-emerald-500/30 text-emerald-200">
                            {hasil.pesan}
                        </div>
                    )}
                    {(status === 'jeda' || status === 'harian') && (
                        <div role="status" data-status={status} className="p-4 rounded-2xl text-sm font-medium bg-amber-500/10 border border-amber-500/30 text-amber-200">
                            {hasil.pesan}
                        </div>
                    )}

                    <form onSubmit={submitKirim}>
                        <button
                            type="submit"
                            disabled={kirim.processing}
                            className="w-full py-3 rounded-2xl bg-teal-600 hover:bg-teal-500 disabled:opacity-60 text-white font-bold text-sm"
                        >
                            {status === 'terkirim' ? 'Kirim ulang link aktivasi' : 'Kirim link aktivasi ke email kampus'}
                        </button>
                    </form>
                </div>
            )}

            <div className="mt-8 pt-5 border-t border-slate-800 text-center text-xs text-slate-400 space-y-1">
                <div>
                    Sudah punya akun? <a href="/masuk" className="font-bold text-emerald-400 hover:underline">Masuk di sini</a>
                </div>
                {urlUmum && (
                    <div>
                        <a href={urlUmum} onClick={(e) => { e.preventDefault(); router.visit(urlUmum); }} className="font-bold text-emerald-400 hover:underline">
                            Daftar sebagai peserta umum
                        </a>
                    </div>
                )}
            </div>
        </GuestLayout>
    );
}
