# Panduan Bahasa dan Tampilan SEMADIKSI

Panduan ini merangkum prinsip penyelarasan bahasa dan antarmuka agar terasa alami, profesional, dan bebas dari pola generik kecerdasan buatan (AI slop). Diadaptasi dari prinsip *blader/humanizer* dan *ZeroSlop* untuk konteks portal akademik KIP-K Universitas Nahdlatul Ulama Surabaya.

---

## 1. Pedoman Bahasa dan Komunikasi

### A. Pola yang Dihindari (Anti-AI Slop)
1. **Pernyataan Kontras Semu ("Bukan X, melainkan Y")**: Hindari kalimat retoris seperti *"Bukan sekadar portal, ini adalah wujud nyata..."*. Tuliskan fakta langsung.
2. **Kata Pembuka Bertele-tele**: Hindari *"Mari kita telusuri"*, *"Perlu diingat bahwa"*, *"Tahukah Anda"*. Langsung sampaikan inti informasi.
3. **Kata Klise & Hiperbola**: Hindari istilah pemasaran klise seperti *revolusioner*, *seamless*, *solusi terpadu*, *optimalkan*, *ekosistem paripurna*, *prestasi gemilang*.
4. **Pola Ritme Tiga Serangkai yang Dipaksakan**: Hindari memaksakan tiga kata sifat berurutan (misal: *"aman, cepat, dan terpercaya"* atau *"inovasi, inspirasi, integritas"*).
5. **Hiasan Emoji Berlebihan**: Hindari deretan emoji pada judul, tombol, dan badan teks.
6. **Data atau Testimoni Karangan**: Jangan membuat angka statistik, metrik kepuasan, atau kutipan testimoni fiktif yang tidak berasal dari basis data nyata.

### B. Pola Bahasa yang Diterapkan
- **Lugas dan Bersahabat**: Gunakan bahasa Indonesia sehari-hari yang baku, lugas, dan bersahabat dengan kalimat ringkas.
- **Konsistensi Istilah**: Gunakan istilah resmi secara konsisten: **KIP-K** (bukan KIPK/Kip-Kuliah acak), **kegiatan** (bukan event/aktivitas campur aduk), **sertifikat**, dan **presensi**.
- **Pesan Galat Informatif**: Sebutkan apa yang tidak berjalan dan langkah perbaikan berikutnya (misal: *"NIM atau kata sandi tidak cocok. Silakan periksa kembali atau atur ulang kata sandi."*).
- **Empty State yang Bersolusi**: Jelaskan kondisi secara netral dan tawarkan aksi nyata (misal: tombol *"Daftar Kegiatan"* atau tautan hubungi admin, bukan sekadar *"Data kosong"*).

---

## 2. Pedoman Tampilan Antarmuka

### A. Pola Tampilan yang Dihindari
1. **Latar Belakang Blob dan Gradien Ungu-Biru AI**: Hindari gradien ungu-ke-biru bawaan generator AI dan lingkaran dekoratif buram (*organic-blob*) yang mengaburkan teks.
2. **Glassmorphism Berlebihan**: Jangan gunakan kartu semi-transparan buram (*backdrop-blur-xl*) pada elemen yang membutuhkan keterbacaan tinggi.
3. **Kartu Tiga Kolom Seragam dengan Ikon di Lingkaran**: Hindari tata letak template monoton dengan ikon material di dalam bulatan berwarna pastel.
4. **Teks Rata Tengah di Seluruh Blok**: Gunakan rata kiri untuk paragraf dan deskripsi agar nyaman dibaca.
5. **Sudut Membulat Ekstrem dan Bayangan Berat**: Hindari `rounded-3xl` dan `shadow-2xl` pada kartu umum.

### B. Pola Tampilan yang Diterapkan
- **Identitas Merek UNUSA**: Gunakan palet hijau UNUSA (`#0d631b`, container `#2e7d32`), aksen emas/amber (`#795900`, `#fec330`), dan latar netral bersih (`#f9f9f9` / `#ffffff`).
- **Tipografi Tegas**: Gunakan font **Plus Jakarta Sans** dengan kontras bobot yang proporsional (*medium* untuk teks berjalan, *semibold/bold* untuk judul).
- **Hierarki dan Spasi Proporsional**: Berikan ruang bernapas (*whitespace*) yang teratur antar bagian tanpa ornamen yang mengalihkan perhatian.
- **Aset Otentik**: Prioritaskan lambang resmi UNUSA dan ikon fungsional yang relevan.

---

## 3. Catatan Rujukan Pihak Ketiga
- **blader/humanizer**: Panduan perbaikan teks berbasis Wikipedia *Signs of AI Writing* ditelaah dan prinsip eliminasi *staging*, *triads*, dan *inflated claims* diterapkan pada naskah UI portal.
- **ZeroSlop (manavmishra/ZeroSlop)**: Prinsip integritas klaim faktual, eliminasi filler, dan penolakan over-koreksi diterapkan.
- **Swiftful-design**: Tidak ditemukan di repositori publik dan pustaka SwiftUI tidak relevan untuk arsitektur web Laravel & React, sehingga dilewati.
