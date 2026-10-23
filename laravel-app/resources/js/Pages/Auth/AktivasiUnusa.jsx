import React from 'react';
import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function AktivasiUnusa({ mahasiswa = {}, aksi }) {
    const form = useForm({ password: '', password_confirmation: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(aksi);
    };

    const kelasInput = 'w-full px-4 py-3 rounded-2xl bg-slate-950 border border-slate-700 focus:border-emerald-500 focus:outline-none text-sm font-medium text-white';

    return (
        <GuestLayout judul="Buat Kata Sandi" subjudul="Satu langkah lagi untuk mengaktifkan akun SEMADIKSI Anda.">
            <dl className="grid grid-cols-2 gap-3 p-4 rounded-2xl bg-slate-950 border border-slate-800 text-sm mb-6" data-kartu-konfirmasi>
                <div>
                    <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-400">NIM</dt>
                    <dd className="font-semibold text-white mt-0.5">{mahasiswa.nim}</dd>
                </div>
                <div>
                    <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Nama</dt>
                    <dd className="font-semibold text-white mt-0.5">{mahasiswa.nama}</dd>
                </div>
            </dl>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="password" className="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">Kata Sandi</label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        className={kelasInput}
                        required
                    />
                    <p className="text-xs text-slate-400 mt-1">Minimal 10 karakter, berisi huruf dan angka.</p>
                    {form.errors.password && <p className="text-xs text-rose-400 font-medium mt-1">{form.errors.password}</p>}
                </div>
                <div>
                    <label htmlFor="password_confirmation" className="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">Ulangi Kata Sandi</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        className={kelasInput}
                        required
                    />
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-60 text-white font-bold text-sm"
                >
                    Aktifkan Akun
                </button>
            </form>
        </GuestLayout>
    );
}
