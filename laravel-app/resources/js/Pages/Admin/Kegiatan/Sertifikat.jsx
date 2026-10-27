import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import EditorTemplatSertifikat from '@/Components/Admin/EditorTemplatSertifikat';

const MAKS_PENANDATANGAN = 4;

/** Penugasan 1 sampai 4 penandatangan untuk kegiatan ini, per jenis sertifikat (logika dan endpoint T2a). */
function PanelPenandatangan({ kegiatanId, penandatangan }) {
    const { ditugaskan = [], master = [], jenis = {} } = penandatangan;
    const [jenisDipilih, setJenisDipilih] = useState('semua');
    const awal = (j) => ditugaskan
        .filter((d) => d.jenis_sertifikat === j)
        .sort((a, b) => a.urutan - b.urutan)
        .map((d) => String(d.penandatangan_id));
    const [slot, setSlot] = useState(() => awal('semua'));
    const [memproses, setMemproses] = useState(false);

    const ganti = (j) => {
        setJenisDipilih(j);
        setSlot(awal(j));
    };
    const ubahSlot = (index, nilai) => setSlot((prev) => {
        const salinan = Array.from({ length: MAKS_PENANDATANGAN }, (_, i) => prev[i] ?? '');
        salinan[index] = nilai;
        return salinan;
    });
    const ids = slot.filter(Boolean);
    const duplikat = new Set(ids).size !== ids.length;

    const simpan = () => {
        setMemproses(true);
        router.post('/admin/sertifikat/penugasan-penandatangan', {
            kegiatan_id: kegiatanId,
            jenis_sertifikat: jenisDipilih,
            penandatangan_ids: ids,
        }, { preserveScroll: true, onFinish: () => setMemproses(false) });
    };

    return (
        <div className="space-y-4">
            <p className="text-xs text-slate-400">
                Tugaskan 1 sampai {MAKS_PENANDATANGAN} penandatangan. Penugasan khusus jenis dipakai lebih dulu; bila kosong, dipakai
                penugasan &quot;Semua jenis&quot;. Data penandatangan dibekukan pada sertifikat saat terbit. Master penandatangan
                dikelola dari tombol di halaman Kelola Kegiatan.
            </p>
            {master.length === 0 && (
                <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300">
                    Belum ada penandatangan aktif. Tambahkan dulu lewat <strong>Master Penandatangan</strong> di halaman Kelola Kegiatan.
                </div>
            )}
            <div className="max-w-xs">
                <label htmlFor="jenis-penugasan" className="block text-[11px] font-bold text-slate-300 mb-1">Jenis sertifikat</label>
                <select
                    id="jenis-penugasan"
                    value={jenisDipilih}
                    onChange={(e) => ganti(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                >
                    {Object.entries(jenis).map(([nilai, label]) => (
                        <option key={nilai} value={nilai}>{label}</option>
                    ))}
                </select>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {Array.from({ length: MAKS_PENANDATANGAN }, (_, i) => (
                    <div key={i}>
                        <label htmlFor={`slot-${i}`} className="block text-[11px] font-bold text-slate-300 mb-1">Penandatangan {i + 1}</label>
                        <select
                            id={`slot-${i}`}
                            value={slot[i] ?? ''}
                            onChange={(e) => ubahSlot(i, e.target.value)}
                            className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                        >
                            <option value="">(kosong)</option>
                            {master.map((m) => (
                                <option key={m.id} value={String(m.id)}>{[m.gelar, m.nama].filter(Boolean).join(' ')} - {m.jabatan}</option>
                            ))}
                        </select>
                    </div>
                ))}
            </div>
            {duplikat && <p className="text-[11px] text-rose-300">Satu orang tidak boleh dipilih dua kali.</p>}
            <button
                type="button"
                disabled={memproses || ids.length === 0 || duplikat}
                onClick={simpan}
                className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold"
            >
                Simpan Penugasan
            </button>
        </div>
    );
}

export default function KegiatanSertifikat({
    title = 'Sertifikat Kegiatan',
    kegiatan = {},
    ringkasan = {},
    tautan = {},
    penandatangan = {},
    templat = null,
    kegiatanLain = [],
}) {
    const [tab, setTab] = useState('templat');
    const kelasTab = (aktif) => `px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 ${
        aktif ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
    }`;

    return (
        <AdminLayout title={title}>
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <a href={tautan.kembali} className="text-[11px] text-slate-400 hover:text-white flex items-center gap-1">
                            <span className="material-symbols-outlined text-sm">arrow_back</span>
                            Kembali ke Kelola Kegiatan
                        </a>
                        <h2 className="text-xl font-black text-white mt-1">{kegiatan.nama_kegiatan}</h2>
                        <p className="text-xs text-slate-400 mt-1">Sertifikat kegiatan &middot; Status: {kegiatan.status_teks || kegiatan.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-3 text-xs">
                        <div className="px-3 py-2 rounded-xl bg-slate-900 border border-slate-800">
                            <div className="text-[10px] uppercase tracking-wider text-slate-500">Layak terbit</div>
                            <div className="text-lg font-black text-white">{ringkasan.layak ?? 0}</div>
                        </div>
                        <div className="px-3 py-2 rounded-xl bg-slate-900 border border-slate-800">
                            <div className="text-[10px] uppercase tracking-wider text-slate-500">Sudah terbit</div>
                            <div className="text-lg font-black text-white">{ringkasan.terbit ?? 0}</div>
                        </div>
                    </div>
                </div>

                <nav className="flex flex-wrap gap-2 border-b border-slate-800 pb-3" aria-label="Menu sertifikat kegiatan">
                    <button type="button" onClick={() => setTab('templat')} className={kelasTab(tab === 'templat')} aria-current={tab === 'templat' ? 'page' : undefined}>
                        <span className="material-symbols-outlined text-sm">design_services</span>Templat
                    </button>
                    <a href={tautan.terbitkan} className={kelasTab(false)}>
                        <span className="material-symbols-outlined text-sm">workspace_premium</span>Terbitkan
                    </a>
                    <a href={tautan.arsip} className={kelasTab(false)}>
                        <span className="material-symbols-outlined text-sm">inventory_2</span>Penerima &amp; Arsip
                    </a>
                    <button type="button" onClick={() => setTab('penandatangan')} className={kelasTab(tab === 'penandatangan')} aria-current={tab === 'penandatangan' ? 'page' : undefined}>
                        <span className="material-symbols-outlined text-sm">draw</span>Penandatangan
                    </button>
                </nav>

                <section className="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    {tab === 'templat' && templat && (
                        <EditorTemplatSertifikat kegiatanId={kegiatan.id} templat={templat} kegiatanLain={kegiatanLain} />
                    )}
                    {tab === 'penandatangan' && <PanelPenandatangan kegiatanId={kegiatan.id} penandatangan={penandatangan} />}
                </section>
            </div>
        </AdminLayout>
    );
}
