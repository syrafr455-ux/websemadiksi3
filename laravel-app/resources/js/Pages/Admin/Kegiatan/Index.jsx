import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatWib, keInputWib } from '@/lib/waktuWib';

export default function KegiatanIndex({
    title = 'Manajemen Kegiatan Enterprise',
    kegiatans = {},
    filters = {},
    unusaFacilities = [],
    statusOptions = []
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [filterCategory, setFilterCategory] = useState(filters.kategori || '');
    const [filterStatus, setFilterStatus] = useState(filters.status || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [currentStep, setCurrentStep] = useState(1);

    // Form State
    const { data, setData, post, processing, errors, reset } = useForm({
        nama_kegiatan: '',
        deskripsi: '',
        kategori: 'Seminar Nasional',
        tipe_kegiatan: 'seminar',
        target_peserta: 'semua',
        waktu_mulai: '',
        waktu_selesai: '',
        lokasi_type: 'unusa_campus',
        lokasi_kampus: 'Kampus B (Jemursari)',
        lokasi_gedung: 'Tower UNUSA',
        lokasi_ruangan: 'Auditorium Lt. 9',
        lokasi_nama: 'Tower UNUSA - Kampus B',
        lokasi_detail: 'Auditorium Lantai 9, Jl. Raya Jemursari No. 57 Surabaya',
        lokasi_map_url: '',
        biaya: 0,
        kuota_total: 100,
        narasumber: '',
        poin_skp: 10,
        has_seat_selection: true,
        has_submission: false,
        submission_deadline: '',
        submission_rules: '',
        panduan_file: null,
        banner_image: null,
        status: 'published',
        seat_layout: {
            rows: ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            cols_per_row: 10,
            vip_rows: ['A', 'B'],
            panitia_rows: [],
        },
    });

    const openCreateModal = () => {
        reset();
        setEditingItem(null);
        setCurrentStep(1);
        setModalOpen(true);
    };

    const openEditModal = (item) => {
        setEditingItem(item);
        setData({
            nama_kegiatan: item.nama_kegiatan || '',
            deskripsi: item.deskripsi || '',
            kategori: item.kategori || 'Seminar Nasional',
            tipe_kegiatan: item.tipe_kegiatan || 'seminar',
            target_peserta: item.target_peserta || 'semua',
            waktu_mulai: keInputWib(item.tanggal_mulai),
            waktu_selesai: keInputWib(item.tanggal_selesai),
            lokasi_type: item.lokasi_type || 'unusa_campus',
            lokasi_kampus: item.metadata?.lokasi_kampus || 'Kampus B (Jemursari)',
            lokasi_gedung: item.metadata?.lokasi_gedung || 'Tower UNUSA',
            lokasi_ruangan: item.metadata?.lokasi_ruangan || 'Auditorium Lt. 9',
            lokasi_nama: item.lokasi_nama || '',
            lokasi_detail: item.lokasi_detail || '',
            lokasi_map_url: item.lokasi_map_url || '',
            biaya: item.biaya || 0,
            kuota_total: item.kuota_total || 100,
            narasumber: item.narasumber || '',
            poin_skp: item.poin_skp || 0,
            has_seat_selection: Boolean(item.has_seat_selection),
            has_submission: Boolean(item.has_submission),
            submission_deadline: keInputWib(item.submission_deadline),
            submission_rules: item.submission_rules || '',
            panduan_file: null,
            banner_image: null,
            status: item.status || 'published',
            seat_layout: {
                rows: ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
                cols_per_row: 10,
                vip_rows: ['A', 'B'],
                panitia_rows: [],
            },
        });
        setCurrentStep(1);
        setModalOpen(true);
    };

    const handleDelete = (id, nama) => {
        if (confirm(`Apakah Anda yakin ingin menghapus kegiatan "${nama}"?`)) {
            router.delete(`/admin/kegiatan/${id}`);
        }
    };

    const handleFileChange = (e, field) => {
        const file = e.target.files[0];
        if (!file) return;

        if (field === 'panduan_file') {
            if (file.type !== 'application/pdf') {
                alert('File panduan resmi wajib berekstensi PDF.');
                e.target.value = '';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('Ukuran berkas PDF maksimal 10MB.');
                e.target.value = '';
                return;
            }
        }

        setData(field, file);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingItem) {
            router.post(`/admin/kegiatan/${editingItem.id}`, {
                _method: 'PUT',
                ...data,
            }, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            post('/admin/kegiatan', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    // Helper untuk memilih fasilitas UNUSA
    const handleFacilityCampusChange = (campusName) => {
        const campus = unusaFacilities.find((c) => c.name === campusName);
        const firstBuilding = campus?.buildings?.[0];
        const firstRoom = firstBuilding?.rooms?.[0];

        setData({
            ...data,
            lokasi_kampus: campusName,
            lokasi_gedung: firstBuilding?.name || '',
            lokasi_ruangan: firstRoom?.name || '',
            lokasi_nama: `${firstBuilding?.name || ''} - ${campusName}`,
            lokasi_detail: `${firstRoom?.name || ''}, ${campus?.address || ''}`,
        });
    };

    return (
        <AdminLayout title={title}>
            <div className="space-y-6">
                {/* Header & Action Button */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400">event_seat</span>
                            Manajemen Kegiatan Enterprise &amp; Denah Kursi
                        </h2>
                        <p className="text-xs text-slate-400 mt-1">
                            Kelola jadwal, integrasi fasilitas UNUSA, denah kursi interaktif, tiket, dan sistem penjurian.
                        </p>
                    </div>
                    <button
                        onClick={openCreateModal}
                        className="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 flex items-center gap-2 transition-all"
                    >
                        <span className="material-symbols-outlined text-sm">add_circle</span>
                        Tambah Kegiatan Baru
                    </button>
                </div>

                {/* Filter & Search Bar */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <form method="GET" action="/admin/kegiatan" className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div className="md:col-span-2">
                            <input
                                type="text"
                                name="q"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama kegiatan, narasumber, atau lokasi..."
                                className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <select
                                name="kategori"
                                value={filterCategory}
                                onChange={(e) => setFilterCategory(e.target.value)}
                                className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-emerald-500"
                            >
                                <option value="">Semua Kategori</option>
                                <option value="Seminar Nasional">Seminar Nasional</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Kompetisi Mahasiswa">Kompetisi Mahasiswa</option>
                                <option value="Pelatihan KIP-K">Pelatihan KIP-K</option>
                                <option value="Sosialisasi">Sosialisasi</option>
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <select
                                name="status"
                                value={filterStatus}
                                onChange={(e) => setFilterStatus(e.target.value)}
                                className="flex-1 px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-emerald-500"
                            >
                                <option value="">Semua Status</option>
                                <option value="published">Diterbitkan (Published)</option>
                                <option value="draft">Draf</option>
                                <option value="completed">Selesai</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                            <button
                                type="submit"
                                className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs"
                            >
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                {/* Kegiatan Table */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                                <tr>
                                    <th className="px-6 py-4">Kegiatan</th>
                                    <th className="px-6 py-4">Waktu &amp; Lokasi</th>
                                    <th className="px-6 py-4">Biaya &amp; Kuota</th>
                                    <th className="px-6 py-4">Denah / Submisi</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 text-slate-300">
                                {kegiatans.data && kegiatans.data.length > 0 ? (
                                    kegiatans.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white text-sm">{item.nama_kegiatan}</div>
                                                <div className="text-[11px] text-emerald-400 font-medium mt-0.5">{item.kategori} • {item.tipe_kegiatan}</div>
                                                {item.narasumber && (
                                                    <div className="text-[11px] text-slate-400 mt-1">Speaker: {item.narasumber}</div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-medium text-slate-200">
                                                    {item.tanggal_mulai ? `${formatWib(item.tanggal_mulai)} WIB` : '-'}
                                                </div>
                                                <div className="text-[11px] text-slate-400 truncate max-w-xs">{item.lokasi_nama}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white">
                                                    {item.biaya > 0 ? `Rp ${Number(item.biaya).toLocaleString('id-ID')}` : 'Gratis'}
                                                </div>
                                                <div className="text-[11px] text-slate-400">
                                                    {item.kuota_terisi} / {item.kuota_total} Peserta ({item.registrations_count || 0} Tiket)
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-1.5 flex-wrap">
                                                    {item.has_seat_selection ? (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-500/15 text-teal-400 border border-teal-500/20">
                                                            {item.seats_count || 0} Kursi
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] text-slate-500 bg-slate-800">Tanpa Kursi</span>
                                                    )}
                                                    {item.has_submission && (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/20">
                                                            {item.submissions_count || 0} Karya
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                                    item.status === 'published'
                                                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                        : 'bg-slate-700 text-slate-300'
                                                }`}>
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <a
                                                        href={`/kegiatan/${item.slug || item.id}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white"
                                                        title="Lihat Halaman Publik"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">visibility</span>
                                                    </a>
                                                    <button
                                                        onClick={() => openEditModal(item)}
                                                        className="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400"
                                                        title="Edit Kegiatan"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">edit</span>
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(item.id, item.nama_kegiatan)}
                                                        className="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400"
                                                        title="Hapus Kegiatan"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-slate-500">
                                            Belum ada kegiatan enterprise yang terdaftar.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* 5-Step Modal Form Wizard */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                            {/* Modal Header */}
                            <div className="p-6 border-b border-slate-800 flex items-center justify-between bg-slate-950">
                                <div>
                                    <h3 className="text-lg font-black text-white">
                                        {editingItem ? 'Edit Kegiatan Enterprise' : 'Tambah Kegiatan Enterprise Baru'}
                                    </h3>
                                    <p className="text-xs text-slate-400 mt-0.5">Langkah {currentStep} dari 5: {
                                        currentStep === 1 ? 'Informasi Utama & Jadwal' :
                                        currentStep === 2 ? 'Lokasi & Fasilitas UNUSA' :
                                        currentStep === 3 ? 'Panduan Resmi (PDF) & Media' :
                                        currentStep === 4 ? 'Setup Denah Kursi Interaktif' :
                                        'Opsi Karya & Penjurian'
                                    }</p>
                                </div>
                                <button
                                    onClick={() => setModalOpen(false)}
                                    className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            {/* Step Indicator */}
                            <div className="px-6 py-3 bg-slate-900 border-b border-slate-800 flex items-center justify-between text-xs font-bold">
                                {[
                                    { step: 1, label: '1. Info Dasar' },
                                    { step: 2, label: '2. Lokasi' },
                                    { step: 3, label: '3. Berkas PDF' },
                                    { step: 4, label: '4. Denah Kursi' },
                                    { step: 5, label: '5. Karya' },
                                ].map((s) => (
                                    <button
                                        key={s.step}
                                        type="button"
                                        onClick={() => setCurrentStep(s.step)}
                                        className={`px-3 py-1.5 rounded-xl transition-all ${
                                            currentStep === s.step
                                                ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30 font-extrabold'
                                                : 'text-slate-400 hover:text-slate-200'
                                        }`}
                                    >
                                        {s.label}
                                    </button>
                                ))}
                            </div>

                            {/* Modal Form Body */}
                            <form onSubmit={handleSubmit} className="p-6 overflow-y-auto flex-1 space-y-6">
                                {currentStep === 1 && (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-300 mb-1">Nama Kegiatan *</label>
                                            <input
                                                type="text"
                                                value={data.nama_kegiatan}
                                                onChange={(e) => setData('nama_kegiatan', e.target.value)}
                                                className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-emerald-500 focus:outline-none"
                                                required
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Kategori *</label>
                                                <input
                                                    type="text"
                                                    value={data.kategori}
                                                    onChange={(e) => setData('kategori', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Tipe Kegiatan *</label>
                                                <select
                                                    value={data.tipe_kegiatan}
                                                    onChange={(e) => setData('tipe_kegiatan', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                >
                                                    <option value="seminar">Seminar</option>
                                                    <option value="workshop">Workshop</option>
                                                    <option value="lomba">Lomba / Kompetisi</option>
                                                    <option value="pelatihan">Pelatihan</option>
                                                    <option value="sosialisasi">Sosialisasi</option>
                                                    <option value="lainnya">Lainnya</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Target Peserta *</label>
                                                <select
                                                    value={data.target_peserta}
                                                    onChange={(e) => setData('target_peserta', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                >
                                                    <option value="semua">Semua Peserta (Terbuka)</option>
                                                    <option value="kipk_only">Khusus Mahasiswa KIP-K</option>
                                                    <option value="umum_only">Khusus Umum</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Waktu Mulai *</label>
                                                <input
                                                    type="datetime-local"
                                                    value={data.waktu_mulai}
                                                    onChange={(e) => setData('waktu_mulai', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Waktu Selesai</label>
                                                <input
                                                    type="datetime-local"
                                                    value={data.waktu_selesai}
                                                    onChange={(e) => setData('waktu_selesai', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Biaya Tiket (Rp) *</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.biaya}
                                                    onChange={(e) => setData('biaya', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                    required
                                                />
                                                <span className="text-[10px] text-slate-500">0 = Gratis</span>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Kuota Peserta *</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={data.kuota_total}
                                                    onChange={(e) => setData('kuota_total', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Narasumber</label>
                                                <input
                                                    type="text"
                                                    value={data.narasumber}
                                                    onChange={(e) => setData('narasumber', e.target.value)}
                                                    placeholder="Nama pemateri"
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Poin SKP</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.poin_skp}
                                                    onChange={(e) => setData('poin_skp', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-300 mb-1">Deskripsi Kegiatan</label>
                                            <textarea
                                                rows="3"
                                                value={data.deskripsi}
                                                onChange={(e) => setData('deskripsi', e.target.value)}
                                                className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-emerald-500 focus:outline-none"
                                            ></textarea>
                                        </div>
                                    </div>
                                )}

                                {currentStep === 2 && (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-300 mb-2">Tipe Lokasi *</label>
                                            <div className="grid grid-cols-3 gap-3">
                                                {[
                                                    { id: 'unusa_campus', label: 'Kampus UNUSA', icon: 'school' },
                                                    { id: 'external_venue', label: 'Venue Eksternal', icon: 'location_on' },
                                                    { id: 'online', label: 'Online (Zoom / Meet)', icon: 'videocam' },
                                                ].map((lt) => (
                                                    <button
                                                        key={lt.id}
                                                        type="button"
                                                        onClick={() => setData('lokasi_type', lt.id)}
                                                        className={`p-3 rounded-2xl border text-center flex flex-col items-center gap-1.5 transition-all ${
                                                            data.lokasi_type === lt.id
                                                                ? 'bg-emerald-500/15 border-emerald-500 text-emerald-300 font-bold'
                                                                : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'
                                                        }`}
                                                    >
                                                        <span className="material-symbols-outlined">{lt.icon}</span>
                                                        <span className="text-xs">{lt.label}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        {data.lokasi_type === 'unusa_campus' && (
                                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                                                <div className="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                                                    <span className="material-symbols-outlined text-sm">verified</span>
                                                    Fasilitas &amp; Ruangan Resmi UNUSA
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-400 mb-1">Kampus UNUSA</label>
                                                        <select
                                                            value={data.lokasi_kampus}
                                                            onChange={(e) => handleFacilityCampusChange(e.target.value)}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        >
                                                            {unusaFacilities.map((c) => (
                                                                <option key={c.name} value={c.name}>{c.name}</option>
                                                            ))}
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-400 mb-1">Gedung</label>
                                                        <select
                                                            value={data.lokasi_gedung}
                                                            onChange={(e) => setData('lokasi_gedung', e.target.value)}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        >
                                                            {unusaFacilities
                                                                .find((c) => c.name === data.lokasi_kampus)
                                                                ?.buildings.map((b) => (
                                                                    <option key={b.name} value={b.name}>{b.name}</option>
                                                                ))}
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-400 mb-1">Ruangan / Hall</label>
                                                        <select
                                                            value={data.lokasi_ruangan}
                                                            onChange={(e) => setData('lokasi_ruangan', e.target.value)}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        >
                                                            {unusaFacilities
                                                                .find((c) => c.name === data.lokasi_kampus)
                                                                ?.buildings.find((b) => b.name === data.lokasi_gedung)
                                                                ?.rooms.map((r) => (
                                                                    <option key={r.name} value={r.name}>{r.name} (Kapasitas: {r.capacity})</option>
                                                                ))}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Nama Lokasi Tampilan *</label>
                                                <input
                                                    type="text"
                                                    value={data.lokasi_nama}
                                                    onChange={(e) => setData('lokasi_nama', e.target.value)}
                                                    placeholder="Contoh: Tower UNUSA - Kampus B"
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Detail Ruangan / Alamat</label>
                                                <input
                                                    type="text"
                                                    value={data.lokasi_detail}
                                                    onChange={(e) => setData('lokasi_detail', e.target.value)}
                                                    placeholder="Contoh: Auditorium Lt. 9, Jl. Raya Jemursari 57"
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {currentStep === 3 && (
                                    <div className="space-y-4">
                                        <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                                            <label className="block text-xs font-bold text-slate-300">
                                                Dokumen Panduan Resmi Kegiatan (Wajib PDF, Maks. 10MB)
                                            </label>
                                            <input
                                                type="file"
                                                accept="application/pdf"
                                                onChange={(e) => handleFileChange(e, 'panduan_file')}
                                                className="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500"
                                            />
                                            <p className="text-[11px] text-slate-500">
                                                Format PDF resmi akan ditampilkan dan dapat diunduh langsung oleh calon peserta kegiatan.
                                            </p>
                                        </div>

                                        <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                                            <label className="block text-xs font-bold text-slate-300">
                                                Banner / Gambar Kegiatan (JPG, PNG, Maks. 5MB)
                                            </label>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) => handleFileChange(e, 'banner_image')}
                                                className="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-slate-700"
                                            />
                                        </div>
                                    </div>
                                )}

                                {currentStep === 4 && (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-950 border border-slate-800">
                                            <div>
                                                <div className="text-xs font-bold text-white">Aktifkan Pemilihan Kursi Interaktif</div>
                                                <div className="text-[11px] text-slate-400">Peserta dapat memilih kursi bernomor langsung pada denah visual.</div>
                                            </div>
                                            <input
                                                type="checkbox"
                                                checked={data.has_seat_selection}
                                                onChange={(e) => setData('has_seat_selection', e.target.checked)}
                                                className="w-5 h-5 rounded text-emerald-600 bg-slate-900 border-slate-700 focus:ring-emerald-500"
                                            />
                                        </div>

                                        {data.has_seat_selection && (
                                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                                                <div className="text-xs font-bold text-slate-300">Konfigurasi Denah Kursi Baris A – I</div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label className="block text-[11px] text-slate-400 mb-1">Kursi per Baris (Kolom)</label>
                                                        <input
                                                            type="number"
                                                            min="4"
                                                            max="20"
                                                            value={data.seat_layout.cols_per_row}
                                                            onChange={(e) => setData('seat_layout', {
                                                                ...data.seat_layout,
                                                                cols_per_row: parseInt(e.target.value) || 10,
                                                            })}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="block text-[11px] text-slate-400 mb-1">Baris VIP</label>
                                                        <input
                                                            type="text"
                                                            value={data.seat_layout.vip_rows.join(', ')}
                                                            onChange={(e) => setData('seat_layout', {
                                                                ...data.seat_layout,
                                                                vip_rows: e.target.value.split(',').map((s) => s.trim().toUpperCase()).filter(Boolean),
                                                            })}
                                                            placeholder="Contoh: A, B"
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Visual Stage & Seat Map Preview */}
                                                <div className="mt-4 p-4 rounded-2xl bg-slate-900/90 border border-slate-800/80 text-center">
                                                    <div className="w-full max-w-md mx-auto py-2 rounded-lg bg-emerald-600/20 border border-emerald-500/40 text-emerald-300 text-xs font-black tracking-widest uppercase mb-6 shadow-inner">
                                                        PANGGUNG / LAYAR UTAMA
                                                    </div>

                                                    <div className="space-y-2 overflow-x-auto pb-2">
                                                        {data.seat_layout.rows.map((r) => (
                                                            <div key={r} className="flex items-center justify-center gap-1.5">
                                                                <span className="w-5 text-[11px] font-bold text-slate-400">{r}</span>
                                                                {Array.from({ length: data.seat_layout.cols_per_row }, (_, i) => i + 1).map((c) => {
                                                                    const isVip = data.seat_layout.vip_rows.includes(r);
                                                                    return (
                                                                        <div
                                                                            key={c}
                                                                            className={`w-6 h-6 rounded flex items-center justify-center text-[9px] font-bold ${
                                                                                isVip
                                                                                    ? 'bg-amber-500/20 border border-amber-500/50 text-amber-300'
                                                                                    : 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-300'
                                                                            }`}
                                                                        >
                                                                            {c}
                                                                        </div>
                                                                    );
                                                                })}
                                                                <span className="w-5 text-[11px] font-bold text-slate-400">{r}</span>
                                                            </div>
                                                        ))}
                                                    </div>

                                                    <div className="mt-4 flex items-center justify-center gap-4 text-[10px] text-slate-400 font-semibold">
                                                        <div className="flex items-center gap-1">
                                                            <div className="w-3 h-3 rounded bg-emerald-500/20 border border-emerald-500"></div>
                                                            <span>Reguler</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <div className="w-3 h-3 rounded bg-amber-500/20 border border-amber-500"></div>
                                                            <span>VIP</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <div className="w-3 h-3 rounded bg-rose-500/20 border border-rose-500"></div>
                                                            <span>Terisi</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {currentStep === 5 && (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-950 border border-slate-800">
                                            <div>
                                                <div className="text-xs font-bold text-white">Wajibkan Pengumpulan Karya / Berkas</div>
                                                <div className="text-[11px] text-slate-400">Aktifkan untuk kompetisi, call for papers, atau lomba yang memerlukan penjurian.</div>
                                            </div>
                                            <input
                                                type="checkbox"
                                                checked={data.has_submission}
                                                onChange={(e) => setData('has_submission', e.target.checked)}
                                                className="w-5 h-5 rounded text-emerald-600 bg-slate-900 border-slate-700 focus:ring-emerald-500"
                                            />
                                        </div>

                                        {data.has_submission && (
                                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-300 mb-1">Tenggat Waktu Pengumpulan (Deadline)</label>
                                                    <input
                                                        type="datetime-local"
                                                        value={data.submission_deadline}
                                                        onChange={(e) => setData('submission_deadline', e.target.value)}
                                                        className="w-full px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-300 mb-1">Ketentuan &amp; Format Karya</label>
                                                    <textarea
                                                        rows="3"
                                                        value={data.submission_rules}
                                                        onChange={(e) => setData('submission_rules', e.target.value)}
                                                        placeholder="Contoh: Format berkas PDF maksimal 20MB, mencakup lembar orisinalitas dan abstrak..."
                                                        className="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                    ></textarea>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Modal Footer Buttons */}
                                <div className="pt-4 border-t border-slate-800 flex items-center justify-between">
                                    <button
                                        type="button"
                                        disabled={currentStep === 1}
                                        onClick={() => setCurrentStep((prev) => Math.max(1, prev - 1))}
                                        className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-slate-300 text-xs font-bold"
                                    >
                                        Sebelumnya
                                    </button>

                                    <div className="flex gap-2">
                                        {currentStep < 5 ? (
                                            <button
                                                type="button"
                                                onClick={() => setCurrentStep((prev) => Math.min(5, prev + 1))}
                                                className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20"
                                            >
                                                Lanjut ke Langkah {currentStep + 1}
                                            </button>
                                        ) : (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-black shadow-lg shadow-emerald-600/30"
                                            >
                                                {processing ? 'Menyimpan...' : (editingItem ? 'Simpan Perubahan' : 'Terbitkan Kegiatan')}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
