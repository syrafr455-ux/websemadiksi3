import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    BATAS_TEKS,
    DATA_CONTOH,
    HALAMAN_MM,
    JENIS_TEKS,
    LABEL_BIDANG,
    LABEL_ELEMEN,
    LANGKAH_PANAH,
    LANGKAH_PANAH_SHIFT,
    QR_MAKS_MM,
    QR_MIN_MM,
    WAJIB,
    adaPerubahan,
    aturSisiQr,
    galatTeks,
    geserElemen,
    isiPenampung,
    jepitElemen,
    menutupiQr,
    normalisasiKeadaan,
    panjangTeks,
    penampungAsing,
    pikselKePersen,
    sembunyikanElemen,
    sisiQrMm,
    snapTengah,
    teksUntukDisimpan,
    ubahUkuran,
} from '@/lib/templatSertifikat';

const LABEL_JENIS = { peserta: 'Peserta', juri: 'Juri', pembicara: 'Pembicara', juara: 'Juara' };
const RATA = { kiri: 'left', tengah: 'center', kanan: 'right' };
const MM_PER_PT = 0.3528;

const kelasInput = 'w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm';
const kelasTombol = 'px-4 py-2 rounded-xl text-sm font-bold disabled:opacity-50';
const kelasTombolBiasa = `${kelasTombol} bg-slate-800 hover:bg-slate-700 text-slate-100`;

/** Pesan galat dari respons axios; respons berupa Blob (pratinjau PDF) dibaca dulu sebagai JSON. */
async function pesanGalat(err) {
    let data = err?.response?.data;
    if (typeof Blob !== 'undefined' && data instanceof Blob) {
        try {
            data = JSON.parse(await data.text());
        } catch {
            data = null;
        }
    }
    if (data?.errors) return { umum: Object.entries(data.errors).filter(([k]) => !k.startsWith('teks.')).flatMap(([, v]) => v), perKolom: Object.fromEntries(Object.entries(data.errors).filter(([k]) => k.startsWith('teks.')).map(([k, v]) => [k.slice(5), v[0]])) };
    return { umum: [data?.message || 'Permintaan gagal. Periksa koneksi lalu coba lagi.'], perKolom: {} };
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

export default function EditorTemplatSertifikat({ kegiatanId, templat, terpilihAwal = 'nama' }) {
    const bawaanTeks = templat.bawaan.teks;
    const batasTeks = templat.aturan.batas_teks ?? BATAS_TEKS;

    // Satu sumber kebenaran: keadaan awal dan keadaan sekarang sama-sama lewat normalisasiKeadaan.
    const [awal, setAwal] = useState(() => normalisasiKeadaan(templat, bawaanTeks));
    const [teksTersimpan, setTeksTersimpan] = useState(templat.teks);
    const [orientasi, setOrientasi] = useState(templat.orientasi);
    const [layout, setLayout] = useState(templat.layout);
    const [teks, setTeks] = useState(templat.teks);
    const [latarUrl, setLatarUrl] = useState(templat.latar_url);
    const [jenisTeks, setJenisTeks] = useState('peserta');
    const [terpilih, setTerpilih] = useState(terpilihAwal);
    const [garisTengah, setGarisTengah] = useState(false);
    const [sibuk, setSibuk] = useState(false);
    const [pesan, setPesan] = useState(null);
    const [peringatan, setPeringatan] = useState(null);
    const [galatUmum, setGalatUmum] = useState([]);
    const [galatServer, setGalatServer] = useState({});
    const [lebarKanvas, setLebarKanvas] = useState(640);
    const kanvasRef = useRef(null);
    const gerak = useRef(null);

    const sekarang = useMemo(() => normalisasiKeadaan({ orientasi, layout, teks }, bawaanTeks), [orientasi, layout, teks, bawaanTeks]);
    const kotor = adaPerubahan(awal, sekarang);
    const galatKolom = useMemo(() => ({ ...galatServer, ...galatTeks(teks, teksTersimpan, batasTeks) }), [galatServer, teks, teksTersimpan, batasTeks]);
    const adaGalatKolom = Object.keys(galatKolom).length > 0;

    const [w, h] = HALAMAN_MM[orientasi];
    const jenisLabel = LABEL_JENIS[jenisTeks];
    const teksAktif = teks[jenisTeks] || {};
    const tumpang = menutupiQr(layout);
    const tersembunyi = Object.keys(layout).filter((k) => !layout[k].tampil);

    const setElemen = useCallback((kunci, el) => setLayout((prev) => ({ ...prev, [kunci]: el })), []);

    useEffect(() => {
        const el = kanvasRef.current;
        if (!el || typeof ResizeObserver === 'undefined') return undefined;
        const pengamat = new ResizeObserver(() => setLebarKanvas(el.getBoundingClientRect().width));
        pengamat.observe(el);
        return () => pengamat.disconnect();
    }, []);

    // Peringatan keluar halaman hanya bila ada perubahan nyata dibanding yang tersimpan.
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

    /** Ambil keadaan dari data server dan jadikan baseline baru (setelah simpan, reset, atau ganti latar). */
    const muatDari = (data) => {
        setAwal(normalisasiKeadaan(data, bawaanTeks));
        setTeksTersimpan(data.teks);
        setOrientasi(data.orientasi);
        setLayout(data.layout);
        setTeks(data.teks);
        setLatarUrl(data.latar_url);
        setGalatServer({});
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
        const snap = snapTengah(baru, true);
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

    const ubahProp = (kunci, tambahan) => setElemen(kunci, jepitElemen({ ...layout[kunci], ...tambahan }, kunci, orientasi));

    const ubahTeks = (bidang, nilai) => {
        setTeks((prev) => ({ ...prev, [jenisTeks]: { ...prev[jenisTeks], [bidang]: nilai } }));
        setGalatServer((prev) => {
            const { [`${jenisTeks}.${bidang}`]: _, ...sisa } = prev;
            return sisa;
        });
    };

    // --- Aksi ke server ---
    const jalankan = async (fungsi, pesanSukses) => {
        setSibuk(true);
        setPesan(null);
        setGalatUmum([]);
        try {
            const hasil = await fungsi();
            if (pesanSukses) setPesan(pesanSukses);
            return hasil;
        } catch (err) {
            const { umum, perKolom } = await pesanGalat(err);
            setGalatUmum(umum);
            setGalatServer(perKolom);
            return null;
        } finally {
            setSibuk(false);
        }
    };

    const alamat = `/admin/kegiatan/${kegiatanId}/sertifikat`;
    const badanTemplat = (jenis) => ({ jenis, orientasi, layout, teks: teksUntukDisimpan(teks, bawaanTeks) });

    const simpan = () => jalankan(async () => {
        const { data } = await window.axios.put(`${alamat}/templat`, badanTemplat(null));
        muatDari(data.templat);
    }, 'Templat disimpan.');

    const kembalikanBawaan = () => {
        if (!window.confirm('Kembalikan tata letak, teks, dan latar kegiatan ini ke bawaan sistem?')) return;
        jalankan(async () => {
            let terakhir = null;
            for (const jenis of [null, ...Object.keys(templat.penimpa || {})]) {
                const { data } = await window.axios.delete(`${alamat}/templat`, { data: { jenis } });
                terakhir = data.templat;
            }
            muatDari(terakhir);
            setPeringatan(null);
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
            const baru = data.templat;
            setLatarUrl(baru.latar_url);
            setPeringatan(data.peringatan);
            // Server sudah menyimpan latar dan orientasinya; perubahan lain yang belum disimpan tetap dipertahankan.
            setAwal(normalisasiKeadaan(baru, bawaanTeks));
            setTeksTersimpan(baru.teks);
            if (baru.orientasi !== orientasi) {
                setLayout((prev) => Object.fromEntries(Object.entries(prev).map(([k, el]) => [k, jepitElemen(el, k, baru.orientasi)])));
                setOrientasi(baru.orientasi);
            }
        }, 'Latar diunggah.');
    };

    const pratinjau = () => jalankan(async () => {
        const { data } = await window.axios.post(`${alamat}/pratinjau`, badanTemplat(jenisTeks), { responseType: 'blob' });
        window.open(URL.createObjectURL(new Blob([data], { type: 'application/pdf' })), '_blank', 'noopener');
    });

    const el = layout[terpilih];
    const pxPerMm = lebarKanvas / w;

    return (
        <div className="space-y-4">
            {kotor && <div role="status" className="px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200">Ada perubahan yang belum disimpan.</div>}
            {pesan && <div role="status" className="px-4 py-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-200">{pesan}</div>}
            {peringatan && <div role="status" className="px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200">{peringatan}</div>}
            {galatUmum.length > 0 && (
                <ul role="alert" className="px-6 py-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-sm text-rose-200 list-disc">
                    {galatUmum.map((g) => <li key={g}>{g}</li>)}
                </ul>
            )}
            {tumpang.length > 0 && (
                <div className="px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200">
                    Menutupi kode QR: {tumpang.map((k) => LABEL_ELEMEN[k]).join(', ')}. QR tetap dirender di atas pelat putih.
                </div>
            )}

            <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-4">
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
                                className={`absolute cursor-move overflow-hidden outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${terpilih === kunci ? 'ring-2 ring-emerald-500 bg-emerald-500/10' : 'ring-1 ring-slate-400/60'}`}
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
                    <p className="mt-2 text-xs text-slate-400">
                        Klik elemen lalu seret ke posisi yang diinginkan. Panah menggeser {LANGKAH_PANAH}%, Shift+panah {LANGKAH_PANAH_SHIFT}%.
                    </p>
                    {tersembunyi.length > 0 && (
                        <p className="mt-1 text-xs text-slate-300">
                            Disembunyikan:{' '}
                            {tersembunyi.map((k) => (
                                <button key={k} type="button" onClick={() => { ubahProp(k, { tampil: true }); setTerpilih(k); }} className="mr-2 underline text-emerald-300 hover:text-emerald-200">
                                    {LABEL_ELEMEN[k]} (tampilkan)
                                </button>
                            ))}
                        </p>
                    )}
                </div>

                <div>
                    {el && (
                        <fieldset className="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
                            <legend className="px-1 text-sm font-bold text-white">{LABEL_ELEMEN[terpilih]}</legend>
                            {terpilih === 'qr' ? (
                                <>
                                    <label className="block text-sm text-slate-300">
                                        Ukuran (mm)
                                        <input
                                            type="number"
                                            min={QR_MIN_MM}
                                            max={QR_MAKS_MM}
                                            step="1"
                                            value={Math.round(sisiQrMm(el, orientasi) * 10) / 10}
                                            onChange={(e) => setElemen('qr', aturSisiQr(layout.qr, orientasi, Number(e.target.value)))}
                                            className={`${kelasInput} mt-1`}
                                        />
                                    </label>
                                    <p className="text-xs text-slate-400">QR selalu persegi, {QR_MIN_MM} sampai {QR_MAKS_MM} mm, dan berada di dalam halaman.</p>
                                </>
                            ) : (
                                <>
                                    <label className="block text-sm text-slate-300">
                                        Ukuran huruf (pt)
                                        <input type="number" min="6" max="72" step="1" value={el.ukuran_font} onChange={(e) => ubahProp(terpilih, { ukuran_font: Number(e.target.value) })} className={`${kelasInput} mt-1`} />
                                    </label>
                                    <label className="block text-sm text-slate-300">
                                        Rata teks
                                        <select value={el.rata} onChange={(e) => ubahProp(terpilih, { rata: e.target.value })} className={`${kelasInput} mt-1`}>
                                            <option value="kiri">Kiri</option>
                                            <option value="tengah">Tengah</option>
                                            <option value="kanan">Kanan</option>
                                        </select>
                                    </label>
                                    <label className="block text-sm text-slate-300">
                                        Warna
                                        <input type="color" value={el.warna} onChange={(e) => ubahProp(terpilih, { warna: e.target.value })} className="mt-1 w-full h-10 rounded-xl bg-slate-950 border border-slate-700" />
                                    </label>
                                </>
                            )}
                            {WAJIB.includes(terpilih)
                                ? <p className="text-xs text-slate-400">Elemen ini selalu tampil di sertifikat.</p>
                                : <button type="button" onClick={() => setLayout((prev) => sembunyikanElemen(prev, terpilih, orientasi))} className={kelasTombolBiasa}>Sembunyikan</button>}
                        </fieldset>
                    )}
                </div>
            </div>

            <details className="bg-slate-950 border border-slate-800 rounded-xl">
                <summary className="cursor-pointer px-4 py-3 text-sm font-bold text-white">Ubah teks sertifikat (opsional)</summary>
                <div className="px-4 pb-4 space-y-3">
                    <label className="block text-sm text-slate-300 max-w-xs">
                        Jenis sertifikat
                        <select value={jenisTeks} onChange={(e) => setJenisTeks(e.target.value)} className={`${kelasInput} mt-1`}>
                            {JENIS_TEKS.map((j) => <option key={j} value={j}>{LABEL_JENIS[j]}</option>)}
                        </select>
                    </label>
                    {Object.keys(batasTeks).map((bidang) => {
                        const kunci = `${jenisTeks}.${bidang}`;
                        const nilai = teksAktif[bidang] ?? '';
                        const panjang = panjangTeks(String(nilai).trim());
                        const melebihi = panjang > batasTeks[bidang];
                        const berubah = nilai !== (teksTersimpan[jenisTeks]?.[bidang] ?? '');
                        const asing = berubah ? penampungAsing(nilai, templat.aturan.penampung) : [];
                        const galat = galatKolom[kunci] || (asing.length > 0 ? `Penampung tidak dikenal: ${asing.map((a) => `{${a}}`).join(', ')}` : null);
                        return (
                            <div key={bidang}>
                                <label htmlFor={`teks-${bidang}`} className="block text-sm text-slate-300">{LABEL_BIDANG[bidang]}</label>
                                <textarea
                                    id={`teks-${bidang}`}
                                    rows={bidang === 'penutup' ? 3 : 2}
                                    value={nilai}
                                    placeholder={bawaanTeks[jenisTeks]?.[bidang]}
                                    aria-invalid={galat ? 'true' : undefined}
                                    aria-describedby={`teks-${bidang}-info`}
                                    onChange={(e) => ubahTeks(bidang, e.target.value)}
                                    className={`${kelasInput} mt-1 ${galat ? 'border-rose-400' : ''}`}
                                />
                                <div id={`teks-${bidang}-info`} className="mt-1 flex items-start justify-between gap-3 text-xs">
                                    <span className={galat ? 'text-rose-300' : 'text-slate-400'}>{galat}</span>
                                    <span className={melebihi ? 'text-rose-300 font-bold' : 'text-slate-400'}>{panjang}/{batasTeks[bidang]}</span>
                                </div>
                            </div>
                        );
                    })}
                    <p className="text-xs text-slate-400">Penampung: {templat.aturan.penampung.map((p) => `{${p}}`).join(' ')}. Kolom dikosongkan berarti memakai teks bawaan.</p>
                </div>
            </details>

            <div className="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-800">
                <label className={`${kelasTombolBiasa} cursor-pointer ${sibuk ? 'opacity-50' : ''}`}>
                    Unggah Latar
                    <input type="file" accept="image/png,image/jpeg" className="sr-only" disabled={sibuk} onChange={unggahLatar} />
                </label>
                <button type="button" disabled={sibuk || !kotor || adaGalatKolom} onClick={simpan} className={`${kelasTombol} bg-emerald-700 hover:bg-emerald-600 text-white`}>Simpan</button>
                <button type="button" disabled={sibuk || adaGalatKolom} onClick={pratinjau} className={kelasTombolBiasa}>Pratinjau PDF</button>
                <button type="button" disabled={sibuk} onClick={kembalikanBawaan} className={`${kelasTombol} bg-slate-800 hover:bg-slate-700 text-amber-200`}>Kembalikan ke Bawaan</button>
            </div>
        </div>
    );
}
