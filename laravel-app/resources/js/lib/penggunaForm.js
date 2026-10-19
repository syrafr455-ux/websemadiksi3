// Form tambah/edit pengguna per role di /admin/pengguna.
// Field yang dikirim per role HARUS sama dengan Form Request di app/Http/Requests/Pengguna/.

export const ROLE_LABELS = {
    super_admin: 'Super Admin',
    mahasiswa_kipk: 'Mahasiswa KIP-K',
    user_umum: 'Mahasiswa Umum',
    verifikator_berkas: 'Verifikator Berkas',
    penilai_karya: 'Penilai Karya (Juri)',
};

export const VERIFIKATOR_LEVELS = [
    { value: 'fakultas', label: 'Admin Fakultas' },
    { value: 'prodi', label: 'Admin Prodi' },
];

// Field yang dikirim ke server untuk tiap role (selain "role").
export const FIELD_PER_ROLE = {
    user_umum: ['name', 'asal_kampus', 'nim', 'program_studi_teks', 'email', 'phone', 'password'],
    // Mahasiswa KIP-K: sama persis dengan form lama (termasuk nilai bawaan angkatan/prodi/university).
    mahasiswa_kipk: ['name', 'email', 'nim', 'phone', 'angkatan', 'prodi', 'university', 'password'],
    verifikator_berkas: ['name', 'email', 'phone', 'password', 'verifikator_level', 'fakultas_id', 'program_studi_id'],
    penilai_karya: ['name', 'email', 'phone', 'password', 'lomba_ids'],
    super_admin: ['name', 'email', 'password'],
};

/** State form kosong; email selalu dimulai kosong (bukan isian browser). */
export function penggunaKosong(role = 'user_umum') {
    return {
        role,
        name: '',
        email: '',
        password: '',
        phone: '',
        nim: '',
        asal_kampus: '',
        program_studi_teks: '',
        verifikator_level: 'prodi',
        fakultas_id: '',
        program_studi_id: '',
        lomba_ids: [],
        // Nilai bawaan form KIP-K lama.
        angkatan: '2024',
        prodi: 'S1 Sistem Informasi',
        university: 'Universitas Nahdlatul Ulama Surabaya',
    };
}

/** State form edit dari data pengguna di tabel. Password dikosongkan (kosong = tidak diubah). */
export function penggunaKeForm(user) {
    return {
        ...penggunaKosong(user.role),
        name: user.name ?? '',
        email: user.email ?? '',
        phone: user.phone ?? '',
        nim: user.nim ?? '',
        asal_kampus: user.asal_kampus ?? '',
        program_studi_teks: user.program_studi_teks ?? '',
        verifikator_level: user.verifikator_level ?? 'prodi',
        fakultas_id: user.fakultas_id ?? '',
        program_studi_id: user.program_studi_id ?? '',
        lomba_ids: Array.isArray(user.lomba_dinilai) ? user.lomba_dinilai.map((l) => l.id) : [],
    };
}

/** Data yang dikirim: hanya field milik role terpilih. */
export function dataUntukRole(data, { edit = false } = {}) {
    const hasil = edit ? {} : { role: data.role };
    for (const field of FIELD_PER_ROLE[data.role] ?? []) {
        hasil[field] = data[field];
    }
    return hasil;
}

/**
 * Password acak 16 karakter (huruf besar/kecil + angka + simbol), dijamin memuat huruf & angka
 * sesuai aturan server. Memakai crypto.getRandomValues, bukan Math.random.
 */
export function buatPasswordAcak(panjang = 16) {
    const huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    const angka = '23456789';
    const simbol = '!@#$%*-_';
    const semua = huruf + angka + simbol;

    const acak = (n) => {
        const buf = new Uint32Array(1);
        crypto.getRandomValues(buf);
        return buf[0] % n;
    };

    const karakter = [huruf[acak(huruf.length)], angka[acak(angka.length)], simbol[acak(simbol.length)]];
    while (karakter.length < panjang) {
        karakter.push(semua[acak(semua.length)]);
    }

    // Fisher-Yates agar posisi huruf/angka wajib tidak bisa ditebak.
    for (let i = karakter.length - 1; i > 0; i--) {
        const j = acak(i + 1);
        [karakter[i], karakter[j]] = [karakter[j], karakter[i]];
    }

    return karakter.join('');
}
