import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import InputWaktuWib from '@/Components/Form/InputWaktuWib';
import { formatWib, tambahDuaJamWib } from '@/lib/waktuWib';
import {
    KATEGORI_OPTIONS,
    LOKASI_TYPE_OPTIONS,
    METODE_OPTIONS,
    SASARAN_OPTIONS,
    formatNarasumber,
    kegiatanKeForm,
    kegiatanKePayload,
    kegiatanKosong,
    labelDari,
    narasumberKosong,
} from '@/lib/kegiatanForm';

export default function KegiatanIndex({
    title = 'Manajemen Kegiatan',
    kegiatans = {},
    filters = {},
    unusaFacilities = [],
    statusOptions = [],
    menungguPersetujuanAnda = 0,
    adaSuperAdminLain = false,
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [filterCategory, setFilterCategory] = useState(filters.kategori || '');
    const [filterStatus, setFilterStatus] = useState(filters.status || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [currentStep, setCurrentStep] = useState(1);
    const isFieldKritisTerkunci = Boolean(editingItem && (editingItem.status === 'published' || editingItem.status === 'ongoing'));

    // Form State (nama field = nama kolom model Kegiatan, lihat resources/js/lib/kegiatanForm.js)
    const { data, setData, post, processing, errors, reset, clearErrors, setError, transform } = useForm(kegiatanKosong());
    transform((form) => (editingItem
        ? { _method: 'PUT', ...kegiatanKePayload(form) }
        : kegiatanKePayload(form)));

    const openCreateModal = () => {
        reset();
        clearErrors();
        setEditingItem(null);
        setCurrentStep(1);
        setModalOpen(true);
    };

    const openEditModal = (item) => {
        setEditingItem(item);
        clearErrors();
        setData(kegiatanKeForm(item));
        setCurrentStep(1);
        setModalOpen(true);
    };

    const handleDelete = (id, nama) => {
        if (confirm(`Apakah Anda yakin ingin menghapus kegiatan "${nama}"?`)) {
            router.delete(`/admin/kegiatan/${id}`);
        }
    };

    // Narasumber: daftar {nama, gelar, instansi, ...}; kunci lain (mis. foto) ikut dipertahankan.
    const tambahNarasumber = () => setData('narasumber', [...data.narasumber, narasumberKosong()]);
    const hapusNarasumber = (index) => setData('narasumber', data.narasumber.filter((_, i) => i !== index));
    const ubahNarasumber = (index, field, value) => setData(
        'narasumber',
        data.narasumber.map((n, i) => (i === index ? { ...n, [field]: value } : n)),
    );

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

    // Sebelum menyimpan denah yang mereset kursi peserta, minta konfirmasi berisi jumlah peserta & email
    // (dihitung server, sama dengan tab Denah & Booking di halaman detail).
    const konfirmasiDenah = async () => {
        try {
            const { data: hasil } = await window.axios.post(`/admin/kegiatan/${editingItem.id}/venue-config/pratinjau`, {
                layout_changed: true,
                is_seat_booking_active: true,
                layout_config: kegiatanKePayload(data).seat_layout_config,
            });
            if (!hasil.cukup) {
                setError('capacity', hasil.pesan_tolak);
                setCurrentStep(4);
                return false;
            }
            if (hasil.akan_reset) {
                return window.confirm(
                    `Denah baru akan mereset kursi ${hasil.peserta_terdampak} peserta. Mereka diminta memilih ulang kursi `
                    + `dan sistem mengirim ${hasil.email_dikirim} email (${hasil.email_pemesan} ke pemesan, ${hasil.email_delegasi} ke delegasi). Lanjutkan?`
                );
            }
        } catch {
            // Pemeriksaan gagal: server tetap memvalidasi kapasitas saat menyimpan.
        }
        return true;
    };

    const handlePlatformDaringChange = (platform) => {
        const platformLabels = {
            zoom: 'Zoom',
            meet: 'Google Meet',
            teams: 'Microsoft Teams',
            lainnya: 'Daring',
        };
        const label = platformLabels[platform] || 'Zoom';
        const namaOtomatis = platform === 'lainnya' ? 'Daring' : `Daring (${label})`;

        const isMurniDaring = data.lokasi_type === 'online' || data.metode_pelaksanaan === 'online';
        setData((prev) => ({
            ...prev,
            platform_daring: platform,
            lokasi_nama: isMurniDaring ? namaOtomatis : prev.lokasi_nama,
        }));
    };

    const handleLokasiTypeChange = (tipe) => {
        const isOnline = tipe === 'online';
        const platformLabels = {
            zoom: 'Zoom',
            meet: 'Google Meet',
            teams: 'Microsoft Teams',
            lainnya: 'Daring',
        };
        const label = platformLabels[data.platform_daring] || 'Zoom';
        const namaOtomatis = data.platform_daring === 'lainnya' ? 'Daring' : `Daring (${label})`;

        setData((prev) => ({
            ...prev,
            lokasi_type: tipe,
            lokasi_nama: isOnline ? namaOtomatis : (prev.lokasi_nama?.startsWith('Daring') ? 'Tower UNUSA - Kampus B' : prev.lokasi_nama),
            lokasi_detail: isOnline ? '' : prev.lokasi_detail,
        }));
    };

    const handleMetodeChange = (metode) => {
        const isMurniDaring = metode === 'online';
        const platformLabels = {
            zoom: 'Zoom',
            meet: 'Google Meet',
            teams: 'Microsoft Teams',
            lainnya: 'Daring',
        };
        const label = platformLabels[data.platform_daring] || 'Zoom';
        const namaOtomatis = data.platform_daring === 'lainnya' ? 'Daring' : `Daring (${label})`;

        setData((prev) => ({
            ...prev,
            metode_pelaksanaan: metode,
            lokasi_type: isMurniDaring ? 'online' : prev.lokasi_type,
            lokasi_nama: isMurniDaring ? namaOtomatis : prev.lokasi_nama,
        }));
    };

    const simpanDenganAksi = async (aksi = 'draft') => {
        clearErrors();

        if (data.tanggal_mulai && data.tanggal_selesai && data.tanggal_selesai <= data.tanggal_mulai) {
            setError('tanggal_selesai', 'Waktu selesai harus setelah waktu mulai.');
            setCurrentStep(1);
            return;
        }

        if (data.tanggal_mulai && data.registration_deadline && data.registration_deadline > data.tanggal_mulai) {
            setError('registration_deadline', 'Batas pendaftaran tidak boleh melewati waktu mulai kegiatan.');
            setCurrentStep(1);
            return;
        }

        const isDaringOrHybrid = data.lokasi_type === 'online' || data.metode_pelaksanaan === 'online' || data.metode_pelaksanaan === 'hybrid';
        if (aksi === 'ajukan' && isDaringOrHybrid && !data.tautan_rapat) {
            setError('tautan_rapat', 'Tautan rapat daring wajib diisi sebelum kegiatan diajukan.');
            setCurrentStep(2);
            return;
        }

        if (editingItem && data.is_seat_booking_active && !(await konfirmasiDenah())) return;

        const payload = kegiatanKePayload({ ...data, aksi_submit: aksi });
        if (editingItem) {
            payload._method = 'PUT';
        }

        router.post(editingItem ? `/admin/kegiatan/${editingItem.id}` : '/admin/kegiatan', payload, {
            onSuccess: () => setModalOpen(false),
            onError: (errs) => {
                if (errs.capacity) setCurrentStep(4);
                if (errs.tautan_rapat) setCurrentStep(2);
            },
        });
    };

    // Pemilih fasilitas UNUSA: kampus -> gedung -> ruangan selalu berupa kombinasi yang valid,
    // lalu nama & detail lokasi tampilan diisi dari pilihan itu.
    const pilihFasilitas = (campusName, buildingName = null, roomName = null) => {
        const campus = unusaFacilities.find((c) => c.name === campusName);
        const building = campus?.buildings?.find((b) => b.name === buildingName) || campus?.buildings?.[0];
        const room = building?.rooms?.find((r) => r.name === roomName) || building?.rooms?.[0];

        setData({
            ...data,
            lokasi_kampus: campusName,
            lokasi_gedung: building?.name || '',
            lokasi_ruangan: room?.name || '',
            lokasi_nama: `${building?.name || ''} - ${campusName}`,
            lokasi_detail: `${room?.name || ''}, ${campus?.address || ''}`,
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
                            Manajemen Kegiatan &amp; Denah Kursi
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

                {/* Banner Peringatan Kegiatan Menunggu Persetujuan */}
                {menungguPersetujuanAnda > 0 && (
                    <div className="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-amber-300">
                        <div className="flex items-center gap-2.5">
                            <span className="material-symbols-outlined text-amber-400 text-xl">pending_actions</span>
                            <div>
                                <span className="font-bold text-amber-200">Perhatian:</span> Terdapat{' '}
                                <strong className="text-white underline">{menungguPersetujuanAnda} kegiatan</strong> yang sedang menunggu persetujuan Anda sebagai Super Admin.
                            </div>
                        </div>
                        <a
                            href="/admin/kegiatan?status=diajukan"
                            className="px-3.5 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 font-bold border border-amber-500/40 text-[11px] flex items-center gap-1 transition-all"
                        >
                            <span>Lihat Daftar</span>
                            <span className="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </div>
                )}

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
                                {KATEGORI_OPTIONS.map((o) => (
                                    <option key={o.value} value={o.value}>{o.label}</option>
                                ))}
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
                                                <div className="text-[11px] text-emerald-400 font-medium mt-0.5">{labelDari(KATEGORI_OPTIONS, item.kategori)} • {labelDari(METODE_OPTIONS, item.metode_pelaksanaan)}</div>
                                                {formatNarasumber(item.narasumber) && (
                                                    <div className="text-[11px] text-slate-400 mt-1">Speaker: {formatNarasumber(item.narasumber)}</div>
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
                                                    {item.kuota_terisi} / {item.kuota} Peserta ({item.registrations_count || 0} Tiket)
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-1.5 flex-wrap">
                                                    {item.is_seat_booking_active ? (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-500/15 text-teal-400 border border-teal-500/20">
                                                            {item.seats_count || 0} Kursi
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] text-slate-500 bg-slate-800">Tanpa Kursi</span>
                                                    )}
                                                    {item.is_submission_active && (
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/20">
                                                            {item.submissions_count || 0} Karya
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col gap-1 items-start">
                                                    <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                                        item.status === 'published'
                                                            ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                            : item.status === 'diajukan'
                                                            ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30'
                                                            : (item.catatan_penolakan ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : 'bg-slate-700 text-slate-300')
                                                    }`}>
                                                        {item.status_teks || item.status}
                                                    </span>
                                                    {item.catatan_penolakan && (
                                                        <div className="text-[10px] text-rose-300/90 italic max-w-xs">
                                                            Catatan: {item.catatan_penolakan}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {/* Tombol Tinjau & Setujui untuk Super Admin yang wajib memberi suara */}
                                                    {item.perlu_tindakan_anda && (
                                                        <a
                                                            href={`/admin/kegiatan/${item.id}#persetujuan`}
                                                            className="px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-[11px] shadow-sm flex items-center gap-1 transition-all"
                                                            title="Tinjau & Berikan Keputusan Persetujuan"
                                                        >
                                                            <span className="material-symbols-outlined text-xs">how_to_reg</span>
                                                            <span>Tinjau &amp; Setujui</span>
                                                        </a>
                                                    )}

                                                    {/* Tombol Ajukan untuk Pembuat saat masih Draft */}
                                                    {item.bisa_diajukan_oleh_anda && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (confirm(`Ajukan kegiatan "${item.nama_kegiatan}" untuk ditinjau oleh Super Admin lain?`)) {
                                                                    router.post(`/admin/kegiatan/${item.id}/ajukan`);
                                                                }
                                                            }}
                                                            className="px-2.5 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 font-bold text-[11px] flex items-center gap-1 transition-all"
                                                            title="Ajukan Persetujuan ke Super Admin Lain"
                                                        >
                                                            <span className="material-symbols-outlined text-xs">send</span>
                                                            <span>Ajukan</span>
                                                        </button>
                                                    )}

                                                    <a
                                                        href={`/admin/kegiatan/${item.id}`}
                                                        className="p-1.5 rounded-lg bg-teal-500/10 hover:bg-teal-500/20 text-teal-300"
                                                        title="Detail & Pendaftar"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">groups</span>
                                                    </a>
                                                    <a
                                                        href={`/kegiatan/${item.id}`}
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
                                            Belum ada kegiatan yang terdaftar. Klik tombol "Tambah Kegiatan" untuk membuat kegiatan baru.
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
                                        {editingItem ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru'}
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
                                {isFieldKritisTerkunci && (
                                    <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 flex items-start gap-2.5 text-xs text-amber-300">
                                        <span className="material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5">lock</span>
                                        <div>
                                            <span className="font-bold text-amber-200">Field Kritis Dikunci:</span> Jadwal, metode, sasaran, biaya, kuota, serta aturan submission pada kegiatan yang telah terbit telah dikunci demi integritas pendaftaran. Field non-kritis (deskripsi, narasumber) tetap dapat diperbarui.
                                        </div>
                                    </div>
                                )}
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
                                                <select
                                                    value={data.kategori}
                                                    onChange={(e) => setData('kategori', e.target.value)}
                                                    disabled={isFieldKritisTerkunci}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                    required
                                                >
                                                    {KATEGORI_OPTIONS.map((o) => (
                                                        <option key={o.value} value={o.value}>{o.label}</option>
                                                    ))}
                                                </select>
                                                {errors.kategori && <span className="text-[10px] text-rose-400">{errors.kategori}</span>}
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Sasaran Peserta *</label>
                                                <select
                                                    value={data.sasaran_peserta}
                                                    onChange={(e) => setData('sasaran_peserta', e.target.value)}
                                                    disabled={isFieldKritisTerkunci}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    {SASARAN_OPTIONS.map((o) => (
                                                        <option key={o.value} value={o.value}>{o.label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Metode Pelaksanaan *</label>
                                                <select
                                                    value={data.metode_pelaksanaan}
                                                    onChange={(e) => handleMetodeChange(e.target.value)}
                                                    disabled={isFieldKritisTerkunci}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                    required
                                                >
                                                    {METODE_OPTIONS.map((o) => (
                                                        <option key={o.value} value={o.value}>{o.label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <InputWaktuWib
                                                id="tanggal_mulai"
                                                name="tanggal_mulai"
                                                label="Waktu Mulai (WIB)"
                                                value={data.tanggal_mulai}
                                                disabled={isFieldKritisTerkunci}
                                                required
                                                onChange={(e) => {
                                                    const val = e.target.value;
                                                    setData((prev) => ({
                                                        ...prev,
                                                        tanggal_mulai: val,
                                                        tanggal_selesai: !prev.tanggal_selesai && val ? tambahDuaJamWib(val) : prev.tanggal_selesai,
                                                    }));
                                                }}
                                                error={errors.tanggal_mulai}
                                            />
                                            <InputWaktuWib
                                                id="tanggal_selesai"
                                                name="tanggal_selesai"
                                                label="Waktu Selesai (WIB)"
                                                value={data.tanggal_selesai}
                                                disabled={isFieldKritisTerkunci}
                                                opsional
                                                min={data.tanggal_mulai}
                                                onChange={(e) => setData('tanggal_selesai', e.target.value)}
                                                onKosongkan={() => setData('tanggal_selesai', '')}
                                                error={
                                                    errors.tanggal_selesai ||
                                                    (!errors.tanggal_selesai && data.tanggal_mulai && data.tanggal_selesai && data.tanggal_selesai <= data.tanggal_mulai
                                                        ? 'Waktu selesai harus setelah waktu mulai.'
                                                        : null)
                                                }
                                                bantuan="Bawaan: 2 jam setelah waktu mulai bila dikosongkan."
                                            />
                                            <InputWaktuWib
                                                id="registration_deadline"
                                                name="registration_deadline"
                                                label="Batas Pendaftaran (WIB)"
                                                value={data.registration_deadline}
                                                disabled={isFieldKritisTerkunci}
                                                opsional
                                                max={data.tanggal_mulai}
                                                onChange={(e) => setData('registration_deadline', e.target.value)}
                                                onKosongkan={() => setData('registration_deadline', '')}
                                                error={
                                                    errors.registration_deadline ||
                                                    (!errors.registration_deadline && data.tanggal_mulai && data.registration_deadline && data.registration_deadline > data.tanggal_mulai
                                                        ? 'Batas pendaftaran tidak boleh melewati waktu mulai kegiatan.'
                                                        : null)
                                                }
                                                bantuan="Kosongkan bila tidak ada batas. Tertutup tepat pada jam ini."
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Biaya Tiket (Rp) *</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.biaya}
                                                    disabled={isFieldKritisTerkunci}
                                                    onChange={(e) => setData('biaya', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                    required
                                                />
                                                <span className="text-[10px] text-slate-500">0 = Gratis</span>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Kuota Peserta *</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={data.kuota}
                                                    disabled={isFieldKritisTerkunci}
                                                    onChange={(e) => setData('kuota', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                    required
                                                />
                                                {errors.kuota && <span className="text-[10px] text-rose-400">{errors.kuota}</span>}
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-300 mb-1">Poin XP / SKP</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.poin_xp}
                                                    onChange={(e) => setData('poin_xp', e.target.value)}
                                                    className="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                                                />
                                            </div>
                                        </div>

                                        <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
                                            <div className="flex items-center justify-between">
                                                <label className="text-xs font-bold text-slate-300">Narasumber</label>
                                                <button
                                                    type="button"
                                                    onClick={tambahNarasumber}
                                                    className="px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-[11px] font-bold flex items-center gap-1"
                                                >
                                                    <span className="material-symbols-outlined text-sm">person_add</span>
                                                    Tambah Narasumber
                                                </button>
                                            </div>
                                            {data.narasumber.length === 0 && (
                                                <p className="text-[11px] text-slate-500">Belum ada narasumber.</p>
                                            )}
                                            {data.narasumber.map((n, index) => (
                                                <div key={index} className="grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] gap-2 items-start">
                                                    <div>
                                                        <input
                                                            type="text"
                                                            value={n.nama ?? ''}
                                                            onChange={(e) => ubahNarasumber(index, 'nama', e.target.value)}
                                                            placeholder="Nama narasumber *"
                                                            aria-label={`Nama narasumber ${index + 1}`}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                            required
                                                        />
                                                        {errors[`narasumber.${index}.nama`] && (
                                                            <span className="text-[10px] text-rose-400">{errors[`narasumber.${index}.nama`]}</span>
                                                        )}
                                                    </div>
                                                    <input
                                                        type="text"
                                                        value={n.gelar ?? ''}
                                                        onChange={(e) => ubahNarasumber(index, 'gelar', e.target.value)}
                                                        placeholder="Gelar (mis. Dr., M.Kom.)"
                                                        aria-label={`Gelar narasumber ${index + 1}`}
                                                        className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={n.instansi ?? ''}
                                                        onChange={(e) => ubahNarasumber(index, 'instansi', e.target.value)}
                                                        placeholder="Instansi"
                                                        aria-label={`Instansi narasumber ${index + 1}`}
                                                        className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => hapusNarasumber(index)}
                                                        className="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400"
                                                        title="Hapus narasumber"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">delete</span>
                                                    </button>
                                                </div>
                                            ))}
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
                                                {LOKASI_TYPE_OPTIONS.map((lt) => (
                                                    <button
                                                        key={lt.id}
                                                        type="button"
                                                        onClick={() => handleLokasiTypeChange(lt.id)}
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

                                        {/* Bagian Pengaturan Rapat Daring untuk Kegiatan Online & Hybrid */}
                                        {(data.lokasi_type === 'online' || data.metode_pelaksanaan === 'online' || data.metode_pelaksanaan === 'hybrid') && (
                                            <div className="p-4 rounded-2xl bg-indigo-950/30 border border-indigo-500/30 space-y-4">
                                                <div className="flex items-center gap-2 text-xs font-bold text-indigo-300">
                                                    <span className="material-symbols-outlined text-base text-indigo-400">videocam</span>
                                                    Pengaturan Rapat Daring (Online / Hybrid)
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-300 mb-1">Platform Daring *</label>
                                                        <select
                                                            value={data.platform_daring}
                                                            onChange={(e) => handlePlatformDaringChange(e.target.value)}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                            required
                                                        >
                                                            <option value="zoom">Zoom Meeting</option>
                                                            <option value="meet">Google Meet</option>
                                                            <option value="teams">Microsoft Teams</option>
                                                            <option value="lainnya">Lainnya / Platform Eksternal</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-300 mb-1">
                                                            Tautan Rapat Resmi *
                                                        </label>
                                                        <input
                                                            type="url"
                                                            value={data.tautan_rapat}
                                                            onChange={(e) => setData('tautan_rapat', e.target.value)}
                                                            placeholder="https://zoom.us/j/1234567890"
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs font-mono"
                                                            required={data.metode_pelaksanaan === 'online' || data.metode_pelaksanaan === 'hybrid'}
                                                        />
                                                        {errors.tautan_rapat && (
                                                            <span className="text-[10px] text-rose-400 block mt-1">{errors.tautan_rapat}</span>
                                                        )}
                                                        <span className="text-[10px] text-slate-500">
                                                            Wajib HTTPS &amp; host resmi (zoom.us, meet.google.com, teams.microsoft.com).
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-300 mb-1">Meeting ID (Opsional)</label>
                                                        <input
                                                            type="text"
                                                            value={data.meeting_id}
                                                            onChange={(e) => setData('meeting_id', e.target.value)}
                                                            placeholder="Contoh: 812 3456 7890"
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs font-mono"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-300 mb-1">Passcode / Kata Sandi (Opsional)</label>
                                                        <input
                                                            type="text"
                                                            value={data.passcode_rapat}
                                                            onChange={(e) => setData('passcode_rapat', e.target.value)}
                                                            placeholder="Contoh: semadiksi2026"
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs font-mono"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-indigo-900/40">
                                                    <div>
                                                        <label className="block text-[11px] font-bold text-slate-300 mb-1">Mode Tombol Gabung *</label>
                                                        <select
                                                            value={data.join_button_mode}
                                                            onChange={(e) => setData('join_button_mode', e.target.value)}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        >
                                                            <option value="otomatis">Otomatis (Dibuka sebelum kegiatan mulai)</option>
                                                            <option value="manual">Manual (Hanya dibuka saat admin mengaktifkannya)</option>
                                                        </select>
                                                    </div>

                                                    {data.join_button_mode === 'otomatis' && (
                                                        <div>
                                                            <label className="block text-[11px] font-bold text-slate-300 mb-1">
                                                                Menit Dibuka Sebelum Mulai
                                                            </label>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                max="1440"
                                                                value={data.menit_buka_gabung}
                                                                onChange={(e) => setData('menit_buka_gabung', e.target.value)}
                                                                className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                            />
                                                            <span className="text-[10px] text-slate-500">Bawaan 15 menit sebelum waktu mulai.</span>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <InputWaktuWib
                                                        id="batas_tutup_gabung"
                                                        name="batas_tutup_gabung"
                                                        label="Batas Tutup Akses Gabung (WIB)"
                                                        value={data.batas_tutup_gabung}
                                                        opsional
                                                        onChange={(e) => setData('batas_tutup_gabung', e.target.value)}
                                                        onKosongkan={() => setData('batas_tutup_gabung', '')}
                                                        bantuan="Bawaan: waktu kegiatan selesai bila dikosongkan."
                                                        error={errors.batas_tutup_gabung}
                                                    />

                                                    <div className="flex flex-col justify-center space-y-2 pt-2">
                                                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={data.izin_tautan_presensi_akhir}
                                                                onChange={(e) => setData('izin_tautan_presensi_akhir', e.target.checked)}
                                                                className="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500"
                                                            />
                                                            <span>Izinkan tautan presensi akhir daring (HMAC)</span>
                                                        </label>

                                                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={data.wajib_tangkapan_layar}
                                                                onChange={(e) => setData('wajib_tangkapan_layar', e.target.checked)}
                                                                className="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500"
                                                            />
                                                            <span>Wajibkan unggah bukti tangkapan layar (screenshot) kehadiran</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* Fasilitas fisik UNUSA: hanya ditampilkan bila bukan murni daring */}
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
                                                            onChange={(e) => pilihFasilitas(e.target.value)}
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
                                                            onChange={(e) => pilihFasilitas(data.lokasi_kampus, e.target.value)}
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
                                                            onChange={(e) => pilihFasilitas(data.lokasi_kampus, data.lokasi_gedung, e.target.value)}
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
                                                {(errors.lokasi_gedung || errors.lokasi_ruangan) && (
                                                    <span className="text-[10px] text-rose-400">{errors.lokasi_gedung || errors.lokasi_ruangan}</span>
                                                )}
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
                                            {/* Sembunyikan detail ruangan fisik untuk kegiatan murni daring */}
                                            {!(data.lokasi_type === 'online' || data.metode_pelaksanaan === 'online') && (
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
                                            )}
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
                                    </div>
                                )}

                                {currentStep === 4 && (
                                    <div className="space-y-4">
                                        {errors.capacity && (
                                            <div className="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-start gap-2">
                                                <span className="material-symbols-outlined text-sm">error</span>
                                                <span>{errors.capacity}</span>
                                            </div>
                                        )}
                                        {editingItem && (
                                            <p className="text-[11px] text-amber-300/90">
                                                Mengubah denah akan mereset kursi peserta yang sudah memilih; mereka diminta memilih ulang lewat email.
                                                Denah dengan kapasitas lebih kecil dari jumlah kursi terisi akan ditolak.
                                            </p>
                                        )}
                                        <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-950 border border-slate-800">
                                            <div>
                                                <div className="text-xs font-bold text-white">Aktifkan Pemilihan Kursi Interaktif</div>
                                                <div className="text-[11px] text-slate-400">Peserta dapat memilih kursi bernomor langsung pada denah visual.</div>
                                            </div>
                                            <input
                                                type="checkbox"
                                                checked={data.is_seat_booking_active}
                                                onChange={(e) => setData('is_seat_booking_active', e.target.checked)}
                                                className="w-5 h-5 rounded text-emerald-600 bg-slate-900 border-slate-700 focus:ring-emerald-500"
                                            />
                                        </div>

                                        {data.is_seat_booking_active && (
                                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                                                <div className="text-xs font-bold text-slate-300">Konfigurasi Denah Kursi Baris A – I</div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label className="block text-[11px] text-slate-400 mb-1">Kursi per Baris (Kolom)</label>
                                                        <input
                                                            type="number"
                                                            min="4"
                                                            max="20"
                                                            value={data.seat_layout_config.cols_per_row}
                                                            onChange={(e) => setData('seat_layout_config', {
                                                                ...data.seat_layout_config,
                                                                cols_per_row: parseInt(e.target.value) || 10,
                                                            })}
                                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="block text-[11px] text-slate-400 mb-1">Baris VIP</label>
                                                        <input
                                                            type="text"
                                                            value={data.seat_layout_config.vip_rows.join(', ')}
                                                            onChange={(e) => setData('seat_layout_config', {
                                                                ...data.seat_layout_config,
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
                                                        {data.seat_layout_config.rows.map((r) => (
                                                            <div key={r} className="flex items-center justify-center gap-1.5">
                                                                <span className="w-5 text-[11px] font-bold text-slate-400">{r}</span>
                                                                {Array.from({ length: data.seat_layout_config.cols_per_row }, (_, i) => i + 1).map((c) => {
                                                                    const isVip = data.seat_layout_config.vip_rows.includes(r);
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
                                                checked={data.is_submission_active}
                                                onChange={(e) => setData('is_submission_active', e.target.checked)}
                                                className="w-5 h-5 rounded text-emerald-600 bg-slate-900 border-slate-700 focus:ring-emerald-500"
                                            />
                                        </div>

                                        {data.is_submission_active && (
                                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                                                    <InputWaktuWib
                                                        id="submission_deadline"
                                                        name="submission_deadline"
                                                        label="Tenggat Waktu Pengumpulan (WIB)"
                                                        value={data.submission_deadline}
                                                        opsional
                                                        onChange={(e) => setData('submission_deadline', e.target.value)}
                                                        onKosongkan={() => setData('submission_deadline', '')}
                                                        error={errors.submission_deadline}
                                                        bantuan="Tertutup tepat pada jam ini. Kosongkan bila tanpa tenggat."
                                                    />
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-300 mb-1">Ketentuan &amp; Format Karya</label>
                                                    <textarea
                                                        rows="3"
                                                        value={data.submission_instructions}
                                                        onChange={(e) => setData('submission_instructions', e.target.value)}
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

                                    <div className="flex flex-wrap items-center gap-2">
                                        {currentStep < 5 ? (
                                            <button
                                                type="button"
                                                onClick={() => setCurrentStep((prev) => Math.min(5, prev + 1))}
                                                className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20"
                                            >
                                                Lanjut ke Langkah {currentStep + 1}
                                            </button>
                                        ) : (
                                            <div className="flex flex-col sm:flex-row items-end sm:items-center gap-2">
                                                <button
                                                    type="button"
                                                    disabled={processing}
                                                    onClick={() => simpanDenganAksi('draft')}
                                                    className="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold transition-all disabled:opacity-50"
                                                >
                                                    Simpan sebagai Draft
                                                </button>

                                                <div className="relative group">
                                                    <button
                                                        type="button"
                                                        disabled={processing || !adaSuperAdminLain}
                                                        onClick={() => simpanDenganAksi('ajukan')}
                                                        className={`px-5 py-2.5 rounded-xl text-xs font-black shadow-lg transition-all ${
                                                            adaSuperAdminLain
                                                                ? 'bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white shadow-emerald-600/30'
                                                                : 'bg-slate-800 text-slate-500 border border-slate-700 cursor-not-allowed shadow-none'
                                                        }`}
                                                    >
                                                        Simpan &amp; Ajukan Persetujuan
                                                    </button>
                                                    {!adaSuperAdminLain && (
                                                        <div className="absolute right-0 bottom-full mb-2 hidden group-hover:block z-50 w-64 p-2 bg-slate-900 border border-slate-700 text-[11px] text-amber-300 rounded-lg shadow-xl pointer-events-none">
                                                            Tidak ada Super Admin aktif lain untuk menyetujui. Tambahkan Super Admin lain agar kegiatan dapat diajukan.
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
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
