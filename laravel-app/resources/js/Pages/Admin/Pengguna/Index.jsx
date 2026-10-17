import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatWib } from '@/lib/waktuWib';

export default function PenggunaIndex({
    title = 'Data Pengguna & Tracking Akses',
    users = {},
    filters = {},
    stats = {},
    roleOptions = {}
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [roleFilter, setRoleFilter] = useState(filters.role || '');
    const [createUserModal, setCreateUserModal] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        nim: '',
        phone: '',
        angkatan: '2024',
        prodi: 'S1 Sistem Informasi',
        university: 'Universitas Nahdlatul Ulama Surabaya',
        role: 'mahasiswa_kipk',
        password: '',
    });

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

    const handleCreateUser = (e) => {
        e.preventDefault();
        post('/admin/pengguna', {
            onSuccess: () => {
                setCreateUserModal(false);
                reset();
            },
        });
    };

    const roleBadges = {
        super_admin: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
        mahasiswa_kipk: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
        user_umum: 'bg-sky-500/15 text-sky-400 border-sky-500/30',
        verifikator_berkas: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
        penilai_karya: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
    };

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
                        onClick={() => {
                            reset();
                            setCreateUserModal(true);
                        }}
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
                                placeholder="Cari nama, NIM, email, atau IP login..."
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
                                <option value="super_admin">Super Admin</option>
                                <option value="mahasiswa_kipk">Mahasiswa KIP-K</option>
                                <option value="user_umum">User Umum</option>
                                <option value="verifikator_berkas">Verifikator Berkas</option>
                                <option value="penilai_karya">Penilai Karya (Juri)</option>
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
                                    <th className="px-6 py-4">NIM / Prodi</th>
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
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border ${
                                                    roleBadges[u.role] || 'bg-slate-800 text-slate-300'
                                                }`}>
                                                    {roleOptions[u.role] || u.role}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-slate-200">{u.nim || '-'}</div>
                                                <div className="text-[11px] text-slate-400">{u.prodi || 'Umum'} {u.angkatan ? `(${u.angkatan})` : ''}</div>
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

                {/* Create User Modal */}
                {createUserModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl p-6 space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 className="text-base font-black text-white">Tambah Pengguna Baru</h3>
                                    <p className="text-xs text-slate-400">Buat akun dengan salah satu dari 5 peran sistem.</p>
                                </div>
                                <button
                                    onClick={() => setCreateUserModal(false)}
                                    className="p-1 rounded-lg text-slate-400 hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <form onSubmit={handleCreateUser} className="space-y-3 text-xs">
                                <div>
                                    <label className="block font-bold text-slate-300 mb-1">Nama Lengkap *</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block font-bold text-slate-300 mb-1">Email *</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-bold text-slate-300 mb-1">Peran (Role) *</label>
                                        <select
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value)}
                                            className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-bold"
                                        >
                                            <option value="mahasiswa_kipk">Mahasiswa KIP-K</option>
                                            <option value="user_umum">User Umum</option>
                                            <option value="verifikator_berkas">Verifikator Berkas</option>
                                            <option value="penilai_karya">Penilai Karya (Juri)</option>
                                            <option value="super_admin">Super Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block font-bold text-slate-300 mb-1">NIM / Identitas</label>
                                        <input
                                            type="text"
                                            value={data.nim}
                                            onChange={(e) => setData('nim', e.target.value)}
                                            className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-bold text-slate-300 mb-1">Nomor Telepon</label>
                                        <input
                                            type="text"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-300 mb-1">Kata Sandi (Password) *</label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                        required
                                        minLength={8}
                                    />
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setCreateUserModal(false)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold hover:bg-slate-700"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30"
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan Akun'}
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
