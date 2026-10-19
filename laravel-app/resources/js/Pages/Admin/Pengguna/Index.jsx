import React, { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatWib } from '@/lib/waktuWib';
import {
    ROLE_LABELS,
    VERIFIKATOR_LEVELS,
    buatPasswordAcak,
    dataUntukRole,
    penggunaKeForm,
    penggunaKosong,
} from '@/lib/penggunaForm';

const kelasInput = 'w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white disabled:opacity-60';

function Field({ id, label, wajib = false, keterangan, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block font-bold text-slate-300 mb-1">
                {label} {wajib && <span className="text-rose-400">*</span>}
            </label>
            {children}
            {keterangan && <p className="text-[10px] text-slate-500 mt-1">{keterangan}</p>}
            {error && <p className="text-[10px] text-rose-400 mt-1">{error}</p>}
        </div>
    );
}

function DetailPeran({ u }) {
    if (u.role === 'user_umum') {
        return (
            <div>
                <div className="font-bold text-slate-200">{u.asal_kampus || '-'}</div>
                <div className="text-[11px] text-slate-400">NIM {u.nim || '-'} • {u.program_studi_teks || '-'}</div>
            </div>
        );
    }

    if (u.role === 'mahasiswa_kipk') {
        return (
            <div>
                <div className="font-bold text-slate-200">{u.nim || '-'}</div>
                <div className="text-[11px] text-slate-400">
                    {u.program_studi?.nama || u.prodi || '-'} {u.angkatan ? `(${u.angkatan})` : ''}
                </div>
                {!u.program_studi_id && (
                    <span className="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                        Prodi belum diatur
                    </span>
                )}
            </div>
        );
    }

    if (u.role === 'verifikator_berkas') {
        const level = VERIFIKATOR_LEVELS.find((l) => l.value === u.verifikator_level)?.label;
        return (
            <div>
                <div className="font-bold text-slate-200">{level || 'Level belum diatur'}</div>
                <div className="text-[11px] text-slate-400">
                    {u.verifikator_level === 'prodi' ? u.program_studi?.nama || '-' : u.fakultas?.nama || '-'}
                </div>
            </div>
        );
    }

    if (u.role === 'penilai_karya') {
        const lomba = u.lomba_dinilai || [];
        return lomba.length > 0 ? (
            <ul className="text-[11px] text-slate-300 list-disc list-inside space-y-0.5">
                {lomba.map((l) => <li key={l.id}>{l.nama_kegiatan}</li>)}
            </ul>
        ) : (
            <span className="text-[11px] text-slate-500 italic">Belum ditugaskan ke lomba</span>
        );
    }

    return <span className="text-slate-500">-</span>;
}

export default function PenggunaIndex({
    title = 'Data Pengguna & Tracking Akses',
    users = {},
    filters = {},
    stats = {},
    fakultasOptions = [],
    lombaOptions = [],
    verifikatorEmailDomains = ['unusa.ac.id'],
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [roleFilter, setRoleFilter] = useState(filters.role || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [passwordDibuat, setPasswordDibuat] = useState('');

    const { data, setData, post, put, transform, processing, errors, reset, clearErrors } = useForm(penggunaKosong('mahasiswa_kipk'));

    const semuaProdi = useMemo(
        () => fakultasOptions.flatMap((f) => (f.program_studi || []).map((p) => ({ ...p, fakultas_nama: f.nama }))),
        [fakultasOptions],
    );
    const prodiTerpilih = semuaProdi.find((p) => String(p.id) === String(data.program_studi_id));

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/admin/pengguna', {
            q: search,
            role: roleFilter,
        }, { preserveState: true });
    };

    const handleRoleChange = (e) => {
        const val = e.target.value;
        setRoleFilter(val);
        router.get('/admin/pengguna', {
            q: search,
            role: val,
        }, { preserveState: true });
    };

    const handleToggleBlock = (id, name, isBlocked) => {
        const actionText = isBlocked ? 'mengaktifkan kembali' : 'menonaktifkan';
        if (confirm(`Apakah Anda yakin ingin ${actionText} akun ${name}?`)) {
            router.patch(`/admin/pengguna/${id}/toggle-block`);
        }
    };

    const handleVerify = (id, name) => {
        if (confirm(`Verifikasi berkas dan status akun ${name}?`)) {
            router.patch(`/admin/pengguna/${id}/verify`);
        }
    };

    const tutupModal = () => {
        setModalOpen(false);
        setEditingUser(null);
        setPasswordDibuat('');
        clearErrors();
    };

    const bukaTambah = () => {
        reset();
        setData(penggunaKosong('mahasiswa_kipk'));
        clearErrors();
        setEditingUser(null);
        setPasswordDibuat('');
        setModalOpen(true);
    };

    const bukaEdit = (u) => {
        clearErrors();
        setData(penggunaKeForm(u));
        setEditingUser(u);
        setPasswordDibuat('');
        setModalOpen(true);
    };

    const isiPasswordOtomatis = () => {
        const password = buatPasswordAcak();
        setData('password', password);
        setPasswordDibuat(password);
    };

    const toggleLomba = (id) => {
        setData('lomba_ids', data.lomba_ids.includes(id) ? data.lomba_ids.filter((x) => x !== id) : [...data.lomba_ids, id]);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        transform((d) => dataUntukRole(d, { edit: Boolean(editingUser) }));
        const opsi = { preserveScroll: true, onSuccess: () => { tutupModal(); reset(); } };

        if (editingUser) {
            put(`/admin/pengguna/${editingUser.id}`, opsi);
        } else {
            post('/admin/pengguna', opsi);
        }
    };

    const roleBadges = {
        super_admin: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
        mahasiswa_kipk: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
        user_umum: 'bg-sky-500/15 text-sky-400 border-sky-500/30',
        verifikator_berkas: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
        penilai_karya: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
    };

    const role = data.role;
    const edit = Boolean(editingUser);

    return (
        <AdminLayout title={title}>
            <div className="space-y-6">
                {/* Header & Action Button */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400">badge</span>
                            Data Pengguna &amp; Matriks Hak Akses (5 Peran)
                        </h2>
                        <p className="text-xs text-slate-400 mt-1">
                            Manajemen akun mahasiswa KIP-K, user umum, verifikator berkas, juri penilai karya, serta tracking IP login.
                        </p>
                    </div>

                    <button
                        onClick={bukaTambah}
                        className="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 flex items-center gap-2 transition-all"
                    >
                        <span className="material-symbols-outlined text-sm">person_add</span>
                        Tambah Pengguna Baru
                    </button>
                </div>

                {/* Role Matrix Stats Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    {[
                        { label: 'Super Admin', count: stats.super_admin || 0, icon: 'shield_person', color: 'text-purple-400' },
                        { label: 'Mhs KIP-K', count: stats.mahasiswa_kipk || 0, icon: 'school', color: 'text-emerald-400' },
                        { label: 'User Umum', count: stats.user_umum || 0, icon: 'public', color: 'text-sky-400' },
                        { label: 'Verifikator', count: stats.verifikator_berkas || 0, icon: 'fact_check', color: 'text-blue-400' },
                        { label: 'Juri Penilai', count: stats.penilai_karya || 0, icon: 'gavel', color: 'text-amber-400' },
                        { label: 'Dinonaktifkan', count: stats.blocked || 0, icon: 'block', color: 'text-rose-400' },
                    ].map((st, i) => (
                        <div key={i} className="p-4 rounded-2xl bg-slate-900 border border-slate-800">
                            <div className="flex items-center justify-between text-slate-400 mb-2">
                                <span className="text-[11px] font-bold truncate">{st.label}</span>
                                <span className={`material-symbols-outlined text-base ${st.color}`}>{st.icon}</span>
                            </div>
                            <div className="text-xl font-black text-white">{st.count}</div>
                        </div>
                    ))}
                </div>

                {/* Filter & Search Bar */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                        <div className="relative flex-1">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama, NIM, email, kampus, prodi, atau IP login..."
                                className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-emerald-500"
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <select
                                value={roleFilter}
                                onChange={handleRoleChange}
                                className="px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-emerald-500"
                            >
                                <option value="">Semua Peran (5 Roles)</option>
                                {Object.entries(ROLE_LABELS).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <button type="submit" className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs">
                                Cari
                            </button>
                        </div>
                    </form>
                </div>

                {/* Users Table */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                                <tr>
                                    <th className="px-6 py-4">Pengguna / Akun</th>
                                    <th className="px-6 py-4">Peran (Role)</th>
                                    <th className="px-6 py-4">Detail Peran</th>
                                    <th className="px-6 py-4">Tracking Login (IP &amp; Waktu)</th>
                                    <th className="px-6 py-4">Status &amp; Verifikasi</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 text-slate-300">
                                {users.data && users.data.length > 0 ? (
                                    users.data.map((u) => (
                                        <tr key={u.id} className="hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white text-sm">{u.name}</div>
                                                <div className="text-[11px] text-slate-400">{u.email}</div>
                                                {u.phone && <div className="text-[11px] text-slate-500">{u.phone}</div>}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border ${
                                                    roleBadges[u.role] || 'bg-slate-800 text-slate-300'
                                                }`}>
                                                    {ROLE_LABELS[u.role] || u.role}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <DetailPeran u={u} />
                                            </td>
                                            <td className="px-6 py-4">
                                                {u.last_login_at ? (
                                                    <div>
                                                        <div className="text-slate-200 font-medium">
                                                            {formatWib(u.last_login_at)} WIB
                                                        </div>
                                                        <div className="text-[11px] font-mono text-emerald-400">IP: {u.last_login_ip || '-'}</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-500 italic">Belum pernah login</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                        u.is_blocked
                                                            ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30'
                                                            : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                    }`}>
                                                        {u.is_blocked ? 'Dinonaktifkan' : 'Aktif'}
                                                    </span>
                                                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                        u.verification_status === 'Verified'
                                                            ? 'bg-sky-500/15 text-sky-400 border border-sky-500/30'
                                                            : 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                                                    }`}>
                                                        {u.verification_status || 'Pending'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {u.role !== 'mahasiswa_kipk' && (
                                                        <button
                                                            onClick={() => bukaEdit(u)}
                                                            className="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400"
                                                            title="Edit Pengguna"
                                                            aria-label={`Edit ${u.name}`}
                                                        >
                                                            <span className="material-symbols-outlined text-sm">edit</span>
                                                        </button>
                                                    )}
                                                    {u.verification_status !== 'Verified' && (
                                                        <button
                                                            onClick={() => handleVerify(u.id, u.name)}
                                                            className="p-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400"
                                                            title="Verifikasi Akun"
                                                        >
                                                            <span className="material-symbols-outlined text-sm">verified</span>
                                                        </button>
                                                    )}
                                                    <button
                                                        onClick={() => handleToggleBlock(u.id, u.name, u.is_blocked)}
                                                        className={`p-1.5 rounded-lg ${
                                                            u.is_blocked
                                                                ? 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400'
                                                                : 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-400'
                                                        }`}
                                                        title={u.is_blocked ? 'Aktifkan Akun' : 'Nonaktifkan Akun'}
                                                    >
                                                        <span className="material-symbols-outlined text-sm">
                                                            {u.is_blocked ? 'lock_open' : 'block'}
                                                        </span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-slate-500">
                                            Tidak ada data pengguna yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Modal Tambah / Edit Pengguna */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-xl shadow-2xl p-6 space-y-4 max-h-[92vh] overflow-y-auto">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 className="text-base font-black text-white">{edit ? `Edit Pengguna: ${editingUser.name}` : 'Tambah Pengguna Baru'}</h3>
                                    <p className="text-xs text-slate-400">Field yang tampil menyesuaikan peran yang dipilih.</p>
                                </div>
                                <button onClick={tutupModal} className="p-1 rounded-lg text-slate-400 hover:text-white" aria-label="Tutup">
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            {/* autoComplete="off" + "new-password": jangan biarkan browser mengisi kredensial admin yang sedang login. */}
                            <form onSubmit={handleSubmit} autoComplete="off" className="space-y-3 text-xs" data-form-pengguna={role}>
                                <Field id="pengguna-role" label="Peran (Role)" wajib>
                                    <select
                                        id="pengguna-role"
                                        value={role}
                                        onChange={(e) => { clearErrors(); setData('role', e.target.value); }}
                                        disabled={edit}
                                        className={`${kelasInput} font-bold`}
                                    >
                                        {Object.entries(ROLE_LABELS).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </select>
                                </Field>

                                <Field
                                    id="pengguna-name"
                                    label="Nama Lengkap"
                                    wajib
                                    keterangan={role === 'user_umum' ? 'Ditulis persis seperti yang akan tercetak di sertifikat.' : null}
                                    error={errors.name}
                                >
                                    <input id="pengguna-name" type="text" autoComplete="off" value={data.name}
                                        onChange={(e) => setData('name', e.target.value)} className={kelasInput} required />
                                </Field>

                                {role === 'user_umum' && (
                                    <>
                                        <Field id="pengguna-asal_kampus" label="Asal Kampus" wajib error={errors.asal_kampus}>
                                            <input id="pengguna-asal_kampus" type="text" autoComplete="off" value={data.asal_kampus}
                                                onChange={(e) => setData('asal_kampus', e.target.value)} className={kelasInput} required />
                                        </Field>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field id="pengguna-nim" label="NIM" wajib error={errors.nim}>
                                                <input id="pengguna-nim" type="text" autoComplete="off" value={data.nim}
                                                    onChange={(e) => setData('nim', e.target.value)} className={kelasInput} required />
                                            </Field>
                                            <Field id="pengguna-program_studi_teks" label="Program Studi" wajib error={errors.program_studi_teks}>
                                                <input id="pengguna-program_studi_teks" type="text" autoComplete="off" value={data.program_studi_teks}
                                                    onChange={(e) => setData('program_studi_teks', e.target.value)} className={kelasInput} required />
                                            </Field>
                                        </div>
                                    </>
                                )}

                                <Field
                                    id="pengguna-email"
                                    label="Email"
                                    wajib
                                    keterangan={role === 'verifikator_berkas' ? `Wajib memakai domain resmi: ${verifikatorEmailDomains.map((d) => '@' + d).join(', ')}` : null}
                                    error={errors.email}
                                >
                                    <input id="pengguna-email" type="email" autoComplete="off" value={data.email}
                                        onChange={(e) => setData('email', e.target.value)} className={kelasInput} required />
                                </Field>

                                {role === 'mahasiswa_kipk' && (
                                    <Field id="pengguna-nim" label="NIM / Identitas" error={errors.nim}>
                                        <input id="pengguna-nim" type="text" autoComplete="off" value={data.nim}
                                            onChange={(e) => setData('nim', e.target.value)} className={kelasInput} />
                                    </Field>
                                )}

                                {role !== 'super_admin' && (
                                    <Field
                                        id="pengguna-phone"
                                        label={role === 'mahasiswa_kipk' ? 'Nomor Telepon' : 'Nomor Handphone'}
                                        wajib={role === 'user_umum'}
                                        keterangan={role === 'mahasiswa_kipk' ? null : 'Nomor Indonesia, contoh 081234567890.'}
                                        error={errors.phone}
                                    >
                                        <input id="pengguna-phone" type="tel" autoComplete="off" value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)} className={kelasInput} required={role === 'user_umum'} />
                                    </Field>
                                )}

                                {role === 'verifikator_berkas' && (
                                    <div className="p-3 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
                                        <div>
                                            <span className="block font-bold text-slate-300 mb-1">Jenis Verifikator <span className="text-rose-400">*</span></span>
                                            <div className="grid grid-cols-2 gap-2">
                                                {VERIFIKATOR_LEVELS.map((lv) => (
                                                    <label key={lv.value} className={`flex items-center gap-2 px-3 py-2 rounded-xl border cursor-pointer ${
                                                        data.verifikator_level === lv.value ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300' : 'border-slate-800 text-slate-300'
                                                    }`}>
                                                        <input type="radio" name="verifikator_level" value={lv.value}
                                                            checked={data.verifikator_level === lv.value}
                                                            onChange={() => setData('verifikator_level', lv.value)} />
                                                        {lv.label}
                                                    </label>
                                                ))}
                                            </div>
                                            {errors.verifikator_level && <p className="text-[10px] text-rose-400 mt-1">{errors.verifikator_level}</p>}
                                        </div>

                                        {data.verifikator_level === 'fakultas' ? (
                                            <Field id="pengguna-fakultas_id" label="Fakultas" wajib error={errors.fakultas_id}>
                                                <select id="pengguna-fakultas_id" value={data.fakultas_id}
                                                    onChange={(e) => setData('fakultas_id', e.target.value)} className={kelasInput} required>
                                                    <option value="">-- Pilih fakultas --</option>
                                                    {fakultasOptions.map((f) => <option key={f.id} value={f.id}>{f.nama}</option>)}
                                                </select>
                                            </Field>
                                        ) : (
                                            <Field
                                                id="pengguna-program_studi_id"
                                                label="Program Studi"
                                                wajib
                                                keterangan={prodiTerpilih ? `Fakultas (otomatis): ${prodiTerpilih.fakultas_nama}` : null}
                                                error={errors.program_studi_id}
                                            >
                                                <select id="pengguna-program_studi_id" value={data.program_studi_id}
                                                    onChange={(e) => setData('program_studi_id', e.target.value)} className={kelasInput} required>
                                                    <option value="">-- Pilih program studi --</option>
                                                    {fakultasOptions.map((f) => (
                                                        <optgroup key={f.id} label={f.nama}>
                                                            {(f.program_studi || []).map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                                                        </optgroup>
                                                    ))}
                                                </select>
                                            </Field>
                                        )}
                                    </div>
                                )}

                                {role === 'penilai_karya' && (
                                    <div className="p-3 rounded-2xl bg-slate-950 border border-slate-800 space-y-2" id="pengguna-lomba">
                                        <span className="block font-bold text-slate-300">Lomba yang Dijuri</span>
                                        {lombaOptions.length === 0 ? (
                                            <p className="text-[11px] text-slate-500">Belum ada kegiatan berkategori lomba.</p>
                                        ) : (
                                            <div className="max-h-40 overflow-y-auto space-y-1">
                                                {lombaOptions.map((l) => (
                                                    <label key={l.id} className="flex items-center gap-2 text-slate-300">
                                                        <input type="checkbox" checked={data.lomba_ids.includes(l.id)} onChange={() => toggleLomba(l.id)} />
                                                        {l.nama_kegiatan}
                                                    </label>
                                                ))}
                                            </div>
                                        )}
                                        {(errors.lomba_ids || Object.keys(errors).some((k) => k.startsWith('lomba_ids.'))) && (
                                            <p className="text-[10px] text-rose-400">
                                                {errors.lomba_ids || Object.entries(errors).find(([k]) => k.startsWith('lomba_ids.'))?.[1]}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <Field
                                    id="pengguna-password"
                                    label="Kata Sandi"
                                    wajib={!edit}
                                    keterangan={edit
                                        ? 'Kosongkan bila kata sandi tidak diubah.'
                                        : (role === 'mahasiswa_kipk' ? 'Minimal 8 karakter.' : 'Minimal 8 karakter, memuat huruf dan angka.')}
                                    error={errors.password}
                                >
                                    <div className="flex gap-2">
                                        <input id="pengguna-password" type="password" autoComplete="new-password" value={data.password}
                                            onChange={(e) => { setData('password', e.target.value); setPasswordDibuat(''); }}
                                            className={kelasInput} required={!edit} minLength={8} />
                                        <button type="button" onClick={isiPasswordOtomatis}
                                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold whitespace-nowrap">
                                            Buat Otomatis
                                        </button>
                                    </div>
                                </Field>

                                {passwordDibuat && (
                                    <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 space-y-1" role="status">
                                        <div className="font-bold">Kata sandi dibuat. Salin sekarang, tidak akan ditampilkan lagi:</div>
                                        <div className="flex items-center gap-2">
                                            <code className="flex-1 px-2 py-1 rounded bg-slate-950 font-mono text-sm text-white select-all" data-password-dibuat>{passwordDibuat}</code>
                                            <button type="button" onClick={() => navigator.clipboard?.writeText(passwordDibuat)}
                                                className="px-2 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 font-bold">
                                                Salin
                                            </button>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                                    <button type="button" onClick={tutupModal} className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold hover:bg-slate-700">
                                        Batal
                                    </button>
                                    <button type="submit" disabled={processing}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30">
                                        {processing ? 'Menyimpan...' : (edit ? 'Simpan Perubahan' : 'Simpan Akun')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
