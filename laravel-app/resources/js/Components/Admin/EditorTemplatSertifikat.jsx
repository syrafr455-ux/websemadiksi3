import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    DATA_CONTOH,
    HALAMAN_MM,
    LABEL_ELEMEN,
    LANGKAH_PANAH,
    LANGKAH_PANAH_SHIFT,
    WAJIB,
    geserElemen,
    isiPenampung,
    jepitElemen,
    menutupiQr,
    penampungAsing,
    pikselKePersen,
    sisiQrMm,
    snapTengah,
    ubahUkuran,
} from '@/lib/templatSertifikat';

const TAB_JENIS = [
    { id: 'bawaan', label: 'Bawaan semua jenis' },
    { id: 'peserta', label: 'Peserta' },
    { id: 'juri', label: 'Juri' },
    { id: 'pembicara', label: 'Pembicara' },
    { id: 'juara', label: 'Juara' },
];

const RATA = { kiri: 'left', tengah: 'center', kanan: 'right' };
const MM_PER_PT = 0.3528;

function pesanGalat(err) {
    const data = err?.response?.data;
    if (data?.errors) return Object.values(data.errors).flat();
    return [data?.message || 'Permintaan gagal. Periksa koneksi lalu coba lagi.'];
}

function bangunKeadaan(templat) {
    return {
        orientasi: templat.orientasi,
        dasar: templat.layout,
        teks: templat.teks,
        penimpa: Object.fromEntries(Object.entries(templat.penimpa || {}).map(([jenis, p]) => [jenis, p.layout])),
    };
}

/** Isi kotak untuk satu elemen pada kanvas, memakai data contoh. */
function isiElemen(kunci, teks, jenisLabel) {
    const data = { ...DATA_CONTOH, jenis: jenisLabel };
    switch (kunci) {
        case 'judul': return isiPenampung(teks.judul, data);
        case 'pengantar': return isiPenampung(teks.pengantar, data);
        case 'nama': return DATA_CONTOH.nama;
        case 'asal_institusi': return DATA_CONTOH.asal_institusi;
        case 'jenis_predikat': return `${jenisLabel} - ${DATA_CONTOH.predikat}`;
        case 'kegiatan': return isiPenampung(teks.penutup, data);
        case 'tanggal': return `Surabaya, ${DATA_CONTOH.tanggal}`;
        case 'nomor_seri': return `No. ${DATA_CONTOH.nomor}`;
        default: return `Nama Penandatangan ${kunci.slice(-1)}\nJabatan`;
    }
}

function QrContoh() {
    const sel = [];
    for (let y = 0; y < 9; y += 1) {
        for (let x = 0; x < 9; x += 1) {
            if ((x * 7 + y * 3 + x * y) % 3 === 0 || (x < 3 && y < 3) || (x > 5 && y < 3) || (x < 3 && y > 5)) sel.push([x, y]);
        }
    }
    return (
        <svg viewBox="0 0 9 9" className="w-full h-full bg-white p-[6%]" aria-label="Kode QR contoh" role="img">
            {sel.map(([x, y]) => <rect key={`${x}-${y}`} x={x} y={y} width="1" height="1" fill="#1a1c1c" />)}
        </svg>
    );
}

export default function EditorTemplatSertifikat({ kegiatanId, templat, kegiatanLain = [] }) {
    const awal = useMemo(() => bangunKeadaan(templat), [templat]);
    const [orientasi, setOrientasi] = useState(awal.orientasi);
    const [dasar, setDasar] = useState(awal.dasar);
    const [teks, setTeks] = useState(awal.teks);
    const [penimpa, setPenimpa] = useState(awal.penimpa);
    const [tersimpanPenimpa, setTersimpanPenimpa] = useState(Object.keys(awal.penimpa));
    const [tab, setTab] = useState('bawaan');
    const [terpilih, setTerpilih] = useState('nama');
    const [snapAktif, setSnapAktif] = useState(true);
    const [garisTengah, setGarisTengah] = useState(false);
    const [kotor, setKotor] = useState(false);
    const [sibuk, setSibuk] = useState(false);
    const [pesan, setPesan] = useState(null);
    const [galat, setGalat] = useState([]);
    const [peringatan, setPeringatan] = useState(null);
    const [latarUrl, setLatarUrl] = useState(templat.latar_url);
    const [sumberSalin, setSumberSalin] = useState('');
    const [lebarKanvas, setLebarKanvas] = useState(640);
    const kanvasRef = useRef(null);
    const gerak = useRef(null);

    const [w, h] = HALAMAN_MM[orientasi];
    const jenisAktif = tab === 'bawaan' ? 'peserta' : tab;
    const punyaPenimpa = tab !== 'bawaan' && Boolean(penimpa[tab]);
    const layout = punyaPenimpa ? penimpa[tab] : dasar;
    const teksAktif = teks[jenisAktif] || {};
    const jenisLabel = TAB_JENIS.find((t) => t.id === jenisAktif)?.label ?? 'Peserta';
    const tumpang = menutupiQr(layout);
    const asing = Object.values(teksAktif).flatMap((t) => penampungAsing(t, templat.aturan.penampung));

    const ubahLayout = useCallback((fungsi) => {
        setKotor(true);
        if (punyaPenimpa) setPenimpa((prev) => ({ ...prev, [tab]: fungsi(prev[tab]) }));
        else setDasar((prev) => fungsi(prev));
    }, [punyaPenimpa, tab]);

    const setElemen = (kunci, el) => ubahLayout((prev) => ({ ...prev, [kunci]: el }));

    useEffect(() => {
        const el = kanvasRef.current;
        if (!el || typeof ResizeObserver === 'undefined') return undefined;
        const pengamat = new ResizeObserver(() => setLebarKanvas(el.getBoundingClientRect().width));
        pengamat.observe(el);
        return () => pengamat.disconnect();
    }, []);

    useEffect(() => {
        const cegah = (e) => {
            if (!kotor) return;
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', cegah);
        const lepas = router.on('before', (event) => {
            if (kotor && !window.confirm('Ada perubahan templat yang belum disimpan. Tinggalkan halaman ini?')) event.preventDefault();
        });
        return () => {
            window.removeEventListener('beforeunload', cegah);
            lepas?.();
        };
    }, [kotor]);

    const muatDari = (data) => {
        const keadaan = bangunKeadaan(data);
        setOrientasi(keadaan.orientasi);
        setDasar(keadaan.dasar);
        setTeks(keadaan.teks);
        setPenimpa(keadaan.penimpa);
        setTersimpanPenimpa(Object.keys(keadaan.penimpa));
        setLatarUrl(data.latar_url);
        setKotor(false);
    };

    // --- Seret dan ubah ukuran dengan pointer events ---
    const mulaiGerak = (e, kunci, mode) => {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.setPointerCapture(e.pointerId);
        setTerpilih(kunci);
        gerak.current = { kunci, mode, x: e.clientX, y: e.clientY, awal: layout[kunci] };
    };

    const sedangGerak = (e) => {
        const g = gerak.current;
        const rect = kanvasRef.current?.getBoundingClientRect();
        if (!g || !rect) return;
        const dx = pikselKePersen(e.clientX - g.x, rect.width);
        const dy = pikselKePersen(e.clientY - g.y, rect.height);
        if (g.mode === 'ubah') {
            setElemen(g.kunci, ubahUkuran(g.awal, g.kunci, orientasi, g.awal.lebar + dx, g.awal.tinggi + dy));
            return;
        }
        let baru = geserElemen(g.awal, g.kunci, orientasi, dx, dy);
        const snap = snapTengah(baru, snapAktif);
        setGarisTengah(snap.garis);
        if (snap.garis) baru = jepitElemen({ ...baru, x: snap.x }, g.kunci, orientasi);
        setElemen(g.kunci, baru);
    };

    const akhiriGerak = () => {
        gerak.current = null;
        setGarisTengah(false);
    };

    const tekanTombol = (e, kunci) => {
        const langkah = e.shiftKey ? LANGKAH_PANAH_SHIFT : LANGKAH_PANAH;
        const arah = { ArrowLeft: [-langkah, 0], ArrowRight: [langkah, 0], ArrowUp: [0, -langkah], ArrowDown: [0, langkah] }[e.key];
        if (!arah) return;
        e.preventDefault();
        setElemen(kunci, geserElemen(layout[kunci], kunci, orientasi, arah[0], arah[1]));
    };

    const ubahProp = (kunci, tambahan) => {
        const el = { ...layout[kunci], ...tambahan };
        if (kunci === 'qr' && 'lebar' in tambahan) {
            setElemen(kunci, ubahUkuran(layout.qr, 'qr', orientasi, Number(tambahan.lebar), Number(tambahan.lebar)));
            return;
        }
        setElemen(kunci, jepitElemen(el, kunci, orientasi));
    };

    const ubahOrientasi = (baru) => {
        const sesuaikan = (tata) => Object.fromEntries(Object.entries(tata).map(([k, el]) => [k, jepitElemen(el, k, baru)]));
        setOrientasi(baru);
        setDasar((prev) => sesuaikan(prev));
        setPenimpa((prev) => Object.fromEntries(Object.entries(prev).map(([j, tata]) => [j, sesuaikan(tata)])));
        setKotor(true);
    };

    const ubahTeks = (bidang, nilai) => {
        setTeks((prev) => ({ ...prev, [jenisAktif]: { ...prev[jenisAktif], [bidang]: nilai } }));
        setKotor(true);
    };

    // --- Aksi ke server ---
    const jalankan = async (fungsi, pesanSukses) => {
        setSibuk(true);
        setPesan(null);
        setGalat([]);
        try {
            const hasil = await fungsi();
            if (pesanSukses) setPesan(pesanSukses);
            return hasil;
        } catch (err) {
            setGalat(pesanGalat(err));
            return null;
        } finally {
            setSibuk(false);
        }
    };

    const alamat = `/admin/kegiatan/${kegiatanId}/sertifikat`;

    const simpan = () => jalankan(async () => {
        await window.axios.put(`${alamat}/templat`, { jenis: null, orientasi, layout: dasar, teks });
        for (const [jenis, tata] of Object.entries(penimpa)) {
            await window.axios.put(`${alamat}/templat`, { jenis, orientasi, layout: tata, teks: { [jenis]: teks[jenis] } });
        }
        for (const jenis of tersimpanPenimpa.filter((j) => !penimpa[j])) {
            await window.axios.delete(`${alamat}/templat`, { data: { jenis } });
        }
        setTersimpanPenimpa(Object.keys(penimpa));
        setKotor(false);
    }, 'Templat disimpan.');

    const resetBawaan = () => {
        if (!window.confirm('Kembalikan semua tata letak dan teks kegiatan ini ke bawaan sistem? Latar juga dihapus.')) return;
        jalankan(async () => {
            for (const jenis of [null, ...tersimpanPenimpa]) await window.axios.delete(`${alamat}/templat`, { data: { jenis } });
            const { bawaan } = templat;
            muatDari({ ...templat, orientasi: 'landscape', layout: bawaan.landscape, teks: bawaan.teks, penimpa: {}, latar_url: null });
        }, 'Templat dikembalikan ke bawaan.');
    };

    const unggahLatar = (e) => {
        const berkas = e.target.files?.[0];
        e.target.value = '';
        if (!berkas) return;
        const form = new FormData();
        form.append('latar', berkas);
        jalankan(async () => {
            const { data } = await window.axios.post(`${alamat}/latar`, form);
            setLatarUrl(data.templat.latar_url);
            setPeringatan(data.peringatan);
        }, 'Latar diunggah.');
    };

    const hapusLatar = () => jalankan(async () => {
        const { data } = await window.axios.delete(`${alamat}/latar`);
        setLatarUrl(data.templat.latar_url);
        setPeringatan(null);
    }, 'Latar dihapus.');

    const salinDariKegiatan = () => {
        if (!sumberSalin || !window.confirm('Templat kegiatan ini akan diganti dengan salinan. Lanjutkan?')) return;
        jalankan(async () => {
            const { data } = await window.axios.post(`${alamat}/salin`, { sumber_id: Number(sumberSalin) });
            muatDari(data.templat);
        }, 'Templat disalin.');
    };

    const pratinjau = () => jalankan(async () => {
        const badan = {
            jenis: jenisAktif,
            orientasi,
            layout,
            teks: { [jenisAktif]: teks[jenisAktif] },
        };
        const { data } = await window.axios.post(`${alamat}/pratinjau`, badan, { responseType: 'blob' });
        window.open(URL.createObjectURL(new Blob([data], { type: 'application/pdf' })), '_blank', 'noopener');
    });

    const el = layout[terpilih];
    const pxPerMm = lebarKanvas / w;
    const kelasInput = 'w-full px-2 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-white text-xs';

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Jenis sertifikat">
                {TAB_JENIS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.id}
                        onClick={() => setTab(t.id)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-bold ${tab === t.id ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'}`}
                    >
                        {t.label}{penimpa[t.id] ? ' *' : ''}
                    </button>
                ))}
                <span className="text-[11px] text-slate-500">* memakai tata letak khusus</span>
            </div>

            {kotor && <div role="status" className="px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300">Ada perubahan yang belum disimpan.</div>}
            {pesan && <div role="status" className="px-3 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300">{pesan}</div>}
            {peringatan && <div role="alert" className="px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300">{peringatan}</div>}
            {galat.length > 0 && (
                <ul role="alert" className="px-4 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-xs text-rose-300 list-disc list-inside">
                    {galat.map((g) => <li key={g}>{g}</li>)}
                </ul>
            )}
            {tumpang.length > 0 && (
                <div className="px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300">
                    Menutupi kode QR: {tumpang.map((k) => LABEL_ELEMEN[k]).join(', ')}. Ini hanya peringatan; QR tetap dirender di atas pelat putih.
                </div>
            )}
            {asing.length > 0 && (
                <div role="alert" className="px-3 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-xs text-rose-300">
                    Penampung tidak dikenal: {asing.map((a) => `{${a}}`).join(', ')}. Tersedia: {templat.aturan.penampung.map((p) => `{${p}}`).join(', ')}.
                </div>
            )}

            <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-4">
                <div>
                    <div
                        ref={kanvasRef}
                        data-testid="kanvas"
                        className="relative w-full max-w-[860px] bg-white border border-slate-700 overflow-hidden select-none touch-none"
                        style={{ aspectRatio: `${w} / ${h}`, backgroundImage: latarUrl ? `url(${latarUrl})` : undefined, backgroundSize: '100% 100%' }}
                    >
                        {garisTengah && <div className="absolute top-0 bottom-0 left-1/2 w-px bg-sky-500 pointer-events-none" aria-hidden="true" />}
                        {Object.entries(layout).filter(([, e]) => e.tampil).map(([kunci, e]) => (
                            <div
                                key={kunci}
                                role="button"
                                tabIndex={0}
                                aria-label={`${LABEL_ELEMEN[kunci]}. Seret atau gunakan tombol panah untuk memindahkan.`}
                                onPointerDown={(ev) => mulaiGerak(ev, kunci, 'geser')}
                                onPointerMove={sedangGerak}
                                onPointerUp={akhiriGerak}
                                onPointerCancel={akhiriGerak}
                                onFocus={() => setTerpilih(kunci)}
                                onKeyDown={(ev) => tekanTombol(ev, kunci)}
                                className={`absolute cursor-move overflow-hidden outline-none ${terpilih === kunci ? 'ring-2 ring-emerald-500 bg-emerald-500/10' : 'ring-1 ring-slate-400/60'}`}
                                style={{
                                    left: `${e.x}%`, top: `${e.y}%`, width: `${e.lebar}%`, height: `${e.tinggi}%`,
                                    color: e.warna, fontFamily: `'${e.font}', sans-serif`, fontWeight: e.tebal ? 700 : 400,
                                    textAlign: RATA[e.rata], fontSize: `${e.ukuran_font * MM_PER_PT * pxPerMm}px`, lineHeight: 1.25,
                                    whiteSpace: 'pre-line', overflowWrap: 'anywhere',
                                }}
                            >
                                {kunci === 'qr' ? <QrContoh /> : isiElemen(kunci, teksAktif, jenisLabel)}
                                {terpilih === kunci && (
                                    <span
                                        role="presentation"
                                        onPointerDown={(ev) => mulaiGerak(ev, kunci, 'ubah')}
                                        onPointerMove={sedangGerak}
                                        onPointerUp={akhiriGerak}
                                        className="absolute right-0 bottom-0 w-3 h-3 bg-emerald-500 cursor-nwse-resize"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
                        <label className="flex items-center gap-1.5">
                            <input type="checkbox" checked={snapAktif} onChange={(e) => setSnapAktif(e.target.checked)} />
                            Tarik ke tengah halaman (snap)
                        </label>
                        <span>Panah: geser {LANGKAH_PANAH}%, Shift+panah: {LANGKAH_PANAH_SHIFT}%.</span>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="bg-slate-950 border border-slate-800 rounded-xl p-3 space-y-2">
                        <label htmlFor="orientasi-templat" className="block text-[11px] font-bold text-slate-300">Orientasi A4</label>
                        <select id="orientasi-templat" value={orientasi} onChange={(e) => ubahOrientasi(e.target.value)} className={kelasInput}>
                            <option value="landscape">Lanskap (297 x 210 mm)</option>
                            <option value="portrait">Potret (210 x 297 mm)</option>
                        </select>
                        {tab !== 'bawaan' && (punyaPenimpa ? (
                            <button type="button" onClick={() => { setPenimpa((p) => { const { [tab]: _, ...sisa } = p; return sisa; }); setKotor(true); }} className="text-[11px] text-rose-300 underline">
                                Hapus tata letak khusus {jenisLabel}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => { setPenimpa((p) => ({ ...p, [tab]: JSON.parse(JSON.stringify(dasar)) })); setKotor(true); }}
                                className="text-[11px] text-emerald-300 underline"
                            >
                                Gunakan tata letak berbeda untuk jenis ini
                            </button>
                        ))}
                    </div>

                    <div className="bg-slate-950 border border-slate-800 rounded-xl p-3 space-y-2">
                        <div className="text-[11px] font-bold text-slate-300">Elemen</div>
                        <div className="flex flex-wrap gap-1.5">
                            {Object.keys(layout).map((kunci) => (
                                <button
                                    key={kunci}
                                    type="button"
                                    onClick={() => setTerpilih(kunci)}
                                    aria-pressed={terpilih === kunci}
                                    className={`px-2 py-1 rounded-md text-[10px] font-bold ${terpilih === kunci ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-300'} ${layout[kunci].tampil ? '' : 'opacity-50'}`}
                                >
                                    {LABEL_ELEMEN[kunci]}
                                </button>
                            ))}
                        </div>
                    </div>

                    {el && (
                        <fieldset className="bg-slate-950 border border-slate-800 rounded-xl p-3 grid grid-cols-2 gap-2">
                            <legend className="px-1 text-[11px] font-bold text-slate-300">Properti: {LABEL_ELEMEN[terpilih]}</legend>
                            {[['x', 'X (%)'], ['y', 'Y (%)'], ['lebar', terpilih === 'qr' ? 'Sisi (% lebar)' : 'Lebar (%)'], ['tinggi', 'Tinggi (%)'], ['ukuran_font', 'Font (pt)']].map(([prop, label]) => (
                                <label key={prop} className="text-[10px] text-slate-400">
                                    {label}
                                    <input
                                        type="number"
                                        step="0.5"
                                        value={el[prop]}
                                        disabled={terpilih === 'qr' && prop === 'tinggi'}
                                        onChange={(e) => (prop === 'ukuran_font' ? ubahProp(terpilih, { ukuran_font: Number(e.target.value) }) : ubahProp(terpilih, { [prop]: Number(e.target.value) }))}
                                        className={kelasInput}
                                    />
                                </label>
                            ))}
                            {terpilih === 'qr' && <p className="col-span-2 text-[10px] text-slate-400">Sisi QR sekarang {sisiQrMm(el, orientasi).toFixed(1)} mm (batas {templat.aturan.qr_min_mm}-{templat.aturan.qr_maks_mm} mm, margin {templat.aturan.margin_qr_mm} mm).</p>}
                            <label className="text-[10px] text-slate-400">
                                Rata
                                <select value={el.rata} onChange={(e) => ubahProp(terpilih, { rata: e.target.value })} className={kelasInput}>
                                    <option value="kiri">Kiri</option>
                                    <option value="tengah">Tengah</option>
                                    <option value="kanan">Kanan</option>
                                </select>
                            </label>
                            <label className="text-[10px] text-slate-400">
                                Font
                                <select value={el.font} onChange={(e) => ubahProp(terpilih, { font: e.target.value })} className={kelasInput}>
                                    {templat.aturan.font.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </label>
                            <label className="text-[10px] text-slate-400">
                                Warna
                                <input type="color" value={el.warna} onChange={(e) => ubahProp(terpilih, { warna: e.target.value })} className="w-full h-8 rounded-lg bg-slate-950 border border-slate-800" />
                            </label>
                            <div className="flex flex-col gap-1 text-[11px] text-slate-300 pt-4">
                                <label className="flex items-center gap-1.5"><input type="checkbox" checked={el.tebal} onChange={(e) => ubahProp(terpilih, { tebal: e.target.checked })} />Tebal</label>
                                <label className="flex items-center gap-1.5">
                                    <input type="checkbox" checked={el.tampil} disabled={WAJIB.includes(terpilih)} onChange={(e) => ubahProp(terpilih, { tampil: e.target.checked })} />
                                    Tampil{WAJIB.includes(terpilih) ? ' (wajib)' : ''}
                                </label>
                            </div>
                        </fieldset>
                    )}

                    {tab !== 'bawaan' && (
                        <fieldset className="bg-slate-950 border border-slate-800 rounded-xl p-3 space-y-2">
                            <legend className="px-1 text-[11px] font-bold text-slate-300">Teks sertifikat {jenisLabel}</legend>
                            {[['judul', 'Judul'], ['pengantar', 'Pengantar'], ['penutup', 'Kalimat penutup']].map(([bidang, label]) => (
                                <label key={bidang} className="block text-[10px] text-slate-400">
                                    {label}
                                    <textarea rows={bidang === 'penutup' ? 3 : 1} value={teksAktif[bidang] ?? ''} onChange={(e) => ubahTeks(bidang, e.target.value)} className={kelasInput} />
                                </label>
                            ))}
                            <p className="text-[10px] text-slate-500">Penampung: {templat.aturan.penampung.map((p) => `{${p}}`).join(' ')}</p>
                        </fieldset>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-800">
                <button type="button" disabled={sibuk || !kotor} onClick={simpan} className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold">Simpan</button>
                <button type="button" disabled={sibuk} onClick={pratinjau} className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold">Pratinjau PDF</button>
                <label className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold cursor-pointer">
                    Unggah latar
                    <input type="file" accept="image/png,image/jpeg" className="sr-only" onChange={unggahLatar} />
                </label>
                {latarUrl && <button type="button" disabled={sibuk} onClick={hapusLatar} className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-rose-300 text-xs font-bold">Hapus latar</button>}
                <button type="button" disabled={sibuk} onClick={resetBawaan} className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs font-bold">Reset ke bawaan</button>
                {kegiatanLain.length > 0 && (
                    <span className="flex items-center gap-1.5">
                        <select aria-label="Salin templat dari kegiatan" value={sumberSalin} onChange={(e) => setSumberSalin(e.target.value)} className={`${kelasInput} w-auto`}>
                            <option value="">Salin dari kegiatan lain...</option>
                            {kegiatanLain.map((k) => <option key={k.id} value={k.id}>{k.nama_kegiatan}</option>)}
                        </select>
                        <button type="button" disabled={sibuk || !sumberSalin} onClick={salinDariKegiatan} className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-slate-200 text-xs font-bold">Salin</button>
                    </span>
                )}
            </div>
        </div>
    );
}
