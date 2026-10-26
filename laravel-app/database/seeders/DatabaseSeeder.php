<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Attendance;
use App\Models\BeritaAcara;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormQuestion;
use App\Models\CustomFormSection;
use App\Models\KipInfo;
use App\Models\KipkDocument;
use App\Models\Scholarship;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Buat atau perbarui akun demo tanpa password hardcode.
     *
     * Password dari config('seeding.*') bila diisi. Bila kosong, akun BARU mendapat password acak
     * (dicatat ke $generatedPasswords untuk ditampilkan sekali); akun lama tidak diubah passwordnya.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: string, 1: string}>  $generatedPasswords
     */
    private function seedDemoAccount(string $email, array $attributes, ?string $configuredPassword, array &$generatedPasswords): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill($attributes);

        if (filled($configuredPassword)) {
            $user->password = Hash::make($configuredPassword);
        } elseif (! $user->exists) {
            $password = Str::random(24);
            $user->password = Hash::make($password);
            $generatedPasswords[] = [$email, $password];
        } else {
            $this->command?->line("Akun {$email} sudah ada; password tidak diubah.");
        }

        $user->save();

        return $user;
    }

    /**
     * Buat baris demo hanya bila belum ada (dicari lewat kolom kunci alami), sehingga seeder aman
     * dijalankan ulang: tidak menduplikasi data, tidak gagal pada kolom unik (mis. kode sertifikat),
     * dan tidak menimpa data demo yang sudah diubah di lokal.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<string>  $kolomKunci
     * @param  array<string, mixed>  $atribut
     * @return TModel
     */
    private function sekali(string $model, array $kolomKunci, array $atribut): Model
    {
        return $model::firstOrCreate(Arr::only($atribut, $kolomKunci), Arr::except($atribut, $kolomKunci));
    }

    /**
     * Seed the application's database. Aman dijalankan berulang kali (lihat sekali()).
     */
    public function run(): void
    {
        // Seluruh isi seeder ini adalah akun & data demo. Super admin production dibuat lewat
        // perintah artisan interaktif tersendiri, bukan seeder.
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DatabaseSeeder berisi akun dan data demo dan tidak boleh dijalankan di production.'
            );
        }

        /** @var list<array{0: string, 1: string}> $generatedPasswords */
        $generatedPasswords = [];

        // 1. Users (Admin and Demo Students)
        $admin = $this->seedDemoAccount(
            'admin@semadiksi.ac.id',
            [
                'name' => 'Administrator SEMADIKSI',
                'nim' => 'ADM-001',
                'phone' => '081122334455',
                'angkatan' => '2024',
                'prodi' => 'Biro Kemahasiswaan',
                'university' => 'Universitas Nahdlatul Ulama Surabaya',
                'kip_status' => 'Umum',
                'verification_status' => 'Verified',
                'role' => UserRole::SuperAdmin,
                'is_blocked' => false,
            ],
            config('seeding.admin_password'),
            $generatedPasswords
        );

        $fauzan = $this->seedDemoAccount(
            'fauzan@student.unusa.ac.id',
            [
                'name' => 'Ahmad Fauzan',
                'nim' => '2240021001',
                'phone' => '081234567890',
                'angkatan' => '2024',
                'prodi' => 'S1 Sistem Informasi',
                'university' => 'Universitas Nahdlatul Ulama Surabaya',
                'kip_status' => 'KIP UNUSA',
                'verification_status' => 'Verified',
                'role' => UserRole::MahasiswaKipk,
                'is_blocked' => false,
            ],
            config('seeding.mahasiswa_password'),
            $generatedPasswords
        );

        $budi = $this->seedDemoAccount(
            'budi@student.unusa.ac.id',
            [
                'name' => 'Budi Santoso',
                'nim' => '2240021045',
                'phone' => '081987654321',
                'angkatan' => '2024',
                'prodi' => 'S1 Teknik Informatika',
                'university' => 'Universitas Nahdlatul Ulama Surabaya',
                'kip_status' => 'KIP UNUSA',
                'verification_status' => 'Verified',
                'role' => UserRole::MahasiswaKipk,
                'is_blocked' => false,
            ],
            config('seeding.mahasiswa_password'),
            $generatedPasswords
        );

        if ($generatedPasswords !== []) {
            $this->command?->warn('Password acak dibuat untuk akun demo berikut. Simpan sekarang, password ini tidak akan ditampilkan lagi.');
            $this->command?->table(['Email', 'Password'], $generatedPasswords);
        }

        // 2. Activities (Kegiatan)
        $act1 = $this->sekali(Activity::class, ['title'], [
            'title' => 'Latihan Kepemimpinan Mahasiswa Berprestasi (LKMB) 2026',
            'category' => 'Seminar',
            'type' => 'Offline',
            'date_text' => '16 November 2026',
            'description' => 'Program pelatihan kepemimpinan intensif untuk memperkuat karakter berintegritas, kepemimpinan organisasi, serta kesiapan karier mahasiswa penerima KIP-Kuliah UNUSA.',
            'status' => 'Segera Hadir',
            'price' => 'Gratis',
            'tags' => ['Kepemimpinan', 'Soft Skill', 'KIP UNUSA'],
            'image_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format&fit=crop&q=80',
            'is_latest' => true,
            'is_closed' => false,
            'location' => 'Auditorium Tower Lantai 9 Kampus B UNUSA Jemursari',
            'rundown' => "08:00 - 08:30: Registrasi Peserta & Check-in QR\n08:30 - 09:00: Pembukaan & Sambutan Rektorat\n09:00 - 11:30: Sesi 1: Strategic Mindset for Student Leaders\n11:30 - 13:00: Ishoma\n13:00 - 15:00: Sesi 2: Workshop Problem Solving & Action Plan",
            'speaker' => 'Dr. H. Ahmad Zainuddin, M.Pd (Direktur Kemahasiswaan) & Praktisi HR Nasional',
            'require_file_upload' => false,
            'enable_seat_booking' => true,
            'quota' => 250,
            'xp_points' => 100,
        ]);

        $act2 = $this->sekali(Activity::class, ['title'], [
            'title' => 'SEMADIKSI Peduli: Bakti Sosial & Edukasi Panti Asuhan Akhir Tahun',
            'category' => 'Sosial',
            'type' => 'Offline',
            'date_text' => '20 Desember 2026',
            'description' => 'Aksi pengabdian masyarakat berupa santunan sembako, buku bacaan, dan lokakarya motivasi belajar anak asuh panti binaan bersama mahasiswa KIP-K UNUSA.',
            'status' => 'Segera Hadir',
            'price' => 'Gratis',
            'tags' => ['Pengabdian', 'Sosial', 'Volunteering'],
            'image_url' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?w=800&auto=format&fit=crop&q=80',
            'is_latest' => false,
            'is_closed' => false,
            'location' => 'Panti Asuhan Kasih Bunda Surabaya Timur',
            'rundown' => "07:30 - 08:00: Briefing Relawan\n08:00 - 10:30: Kelas Mengajar Ceria & Lomba Mewarnai\n10:30 - 11:30: Simbolis Penyerahan Bantuan Sosial\n11:30 - 12:00: Doa Bersama & Penutupan",
            'speaker' => 'Pengurus Divisi Sosial Masyarakat SEMADIKSI',
            'require_file_upload' => false,
            'enable_seat_booking' => false,
            'quota' => 100,
            'xp_points' => 75,
        ]);

        $act3 = $this->sekali(Activity::class, ['title'], [
            'title' => 'Webinar Nasional: Menembus Beasiswa Riset & Magang Global',
            'category' => 'Webinar',
            'type' => 'Online',
            'date_text' => '05 Januari 2027',
            'description' => 'Kupas tuntas strategi membuat proposal riset berstandar internasional, esai motivasi, dan seleksi magang di perusahaan multinasional.',
            'status' => 'Pendaftaran Dibuka',
            'price' => 'Gratis',
            'tags' => ['Webinar', 'Global Karier', 'Riset'],
            'image_url' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=80',
            'is_latest' => true,
            'is_closed' => false,
            'location' => 'Zoom Cloud Meetings & Live YouTube UNUSA TV',
            'speaker' => 'Alumni KIP-K Peraih Beasiswa Erasmus Mundus 2025',
            'require_file_upload' => true,
            'file_upload_instruction' => 'Unggah tangkapan layar bukti follow media sosial @semadiksi_unusa dan bagikan poster ke 2 grup WhatsApp.',
            'enable_seat_booking' => false,
            'quota' => 500,
            'xp_points' => 50,
        ]);

        // 3. Activity Registration
        $this->sekali(ActivityRegistration::class, ['user_id', 'activity_id'], [
            'user_id' => $fauzan->id,
            'activity_id' => $act1->id,
            'selected_seat' => 'A-12',
            'payment_status' => 'Gratis',
            'status' => 'Terdaftar',
        ]);

        // 4. Attendances
        $this->sekali(Attendance::class, ['user_id', 'activity_id'], [
            'user_id' => $fauzan->id,
            'activity_id' => $act1->id,
            'activity_title' => $act1->title,
            'activity_category' => 'Seminar',
            'activity_date' => '16 November 2026',
            'student_name' => $fauzan->name,
            'student_nim' => $fauzan->nim,
            'university' => 'Universitas Nahdlatul Ulama Surabaya',
            'student_email' => $fauzan->email,
            'student_phone' => $fauzan->phone,
            'proof_image_url' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=80',
            'proof_file_name' => 'Selfie_Kehadiran_LKMB_AhmadFauzan.jpg',
            'status' => 'Hadir',
            'notes' => 'Kehadiran terverifikasi di Auditorium Tower UNUSA Lantai 9.',
            'device_info' => 'Mobile Web (Chrome Android)',
            'location_name' => 'Auditorium Kampus B UNUSA (GPS Valid)',
        ]);

        // 5. Certificates
        $this->sekali(Certificate::class, ['code'], [
            'user_id' => $fauzan->id,
            'activity_id' => $act1->id,
            'user_name' => $fauzan->name,
            'activity_title' => $act1->title,
            'code' => 'SMD-LKMB-2026-0091',
            'status' => 'Berlaku',
            'issued_date' => '2026-11-16',
            'signatories' => [
                ['name' => 'Dr. H. Ahmad Zainuddin, M.Pd', 'title' => 'Direktur Kemahasiswaan UNUSA'],
                ['name' => 'Muhammad Rifqi Ramadhani', 'title' => 'Ketua Umum SEMADIKSI UNUSA'],
            ],
            'file_url' => 'https://drive.google.com/file/d/1_Sertifikat_LKMB/view',
        ]);

        // 6. Berita Acara
        $this->sekali(BeritaAcara::class, ['title'], [
            'title' => 'Berita Acara Pelantikan Pengurus & Rapat Kerja SEMADIKSI UNUSA Periode 2026/2027',
            'category' => 'Rapat Kerja',
            'status' => 'Selesai',
            'date_text' => '10 Agustus 2026',
            'time_text' => '08:30 - 15:30 WIB',
            'location' => 'Auditorium Tower Lantai 9 Kampus B UNUSA Jemursari',
            'organizer' => 'Biro Kemahasiswaan UNUSA & Pengurus SEMADIKSI',
            'attendee_count' => 145,
            'summary' => 'Pelantikan resmi 48 pengurus SEMADIKSI periode 2026/2027 serta pengesahan Garis Besar Haluan Program Kerja (GBHPK).',
            'content' => "Pelantikan dibuka resmi oleh Wakil Rektor I Bidang Akademik & Kemahasiswaan UNUSA. Agenda pembahasan meliputi:\n1. Pengesahan Struktur Kepengurusan dan Pakta Integritas Organisasi.\n2. Rencana Strategis Penyaluran dan Monitoring Evaluasi (Monev) KIP-K Semester Ganjil.\n3. Digitalisasi Pelaporan Dokumen KIP-K melalui Sistem Portal Terpadu.\nKegiatan berlangsung secara khidmat, tertib, dan menghasilkan dokumen berita acara serta kesepakatan notulensi resmi.",
            'banner_img' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=800&auto=format&fit=crop&q=80',
            'author' => 'Sekretariat Jenderal SEMADIKSI',
        ]);

        // 7. Scholarships
        $this->sekali(Scholarship::class, ['title'], [
            'title' => 'Beasiswa KIP Kuliah Merdeka Jalur Aspirasi & Reguler UNUSA',
            'provider' => 'Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi RI',
            'category' => 'KIP Kuliah',
            'status' => 'Dibuka',
            'open_date' => '01 Juli 2026',
            'close_date' => '30 September 2026',
            'coverage' => 'Pembebasan Biaya Kuliah Penuh (UKT/SPP) 100% dan Biaya Hidup Bulanan',
            'requirements' => [
                'Warga Negara Indonesia terdaftar di DTKS Kemensos RI.',
                'Memiliki Kartu KIP Kuliah / KKS / Bukti SKTM dari kelurahan resmi.',
                'Mahasiswa aktif UNUSA berstatus registrasi valid.',
                'Mempertahankan IPK minimal 3.00 setiap semester evaluasi.',
            ],
            'description' => 'Program bantuan biaya pendidikan dari pemerintah bagi lulusan SMA/SMK sederajat yang memiliki potensi akademik baik namun memiliki keterbatasan ekonomi untuk menempuh pendidikan tinggi.',
            'banner_img' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=80',
            'apply_url' => 'https://kip-kuliah.kemdikbud.go.id/',
        ]);

        // 8. Info KIP
        $this->sekali(KipInfo::class, ['title'], [
            'title' => 'Panduan Wajib Unggah Berkas Persyaratan Pencairan Dana Bantuan KIP-K Semester Ganjil',
            'category' => 'Alur Pencairan',
            'content' => 'Diberitahukan kepada seluruh mahasiswa penerima beasiswa KIP-Kuliah UNUSA angkatan 2023, 2024, dan 2025 bahwa pengumpulan berkas persyaratan pencairan dibuka hingga 28 Oktober 2026.',
            'date_uploaded' => '12 Oktober 2026',
            'author' => 'Tim Pengelola KIP UNUSA',
            'priority' => 'Tinggi',
        ]);

        // 9. KIP-K Documents
        $this->sekali(KipkDocument::class, ['user_id', 'file_name'], [
            'user_id' => $fauzan->id,
            'user_name' => $fauzan->name,
            'user_email' => $fauzan->email,
            'user_nim' => $fauzan->nim,
            'category' => 'Kartu KIP-K',
            'tahap' => 1,
            'title' => 'Kartu KIP-Kuliah Digital Kemdikbudristek',
            'file_name' => 'KIPK_Digital_2240021001.pdf',
            'file_size' => '1.2 MB',
            'file_type' => 'pdf',
            'file_url' => 'https://drive.google.com/file/d/1_Kartu_KIPK/view',
            'uploaded_by' => 'Mahasiswa',
            'status' => 'Disetujui',
            'score' => 100,
            'notes' => 'Nomor KIPK valid dan terdaftar resmi di sistem Puslapdik.',
        ]);

        // 10. Custom Forms (Pencairan, Pelaporan, Monev)
        $formPencairan = $this->sekali(CustomForm::class, ['form_key'], [
            'form_key' => 'pencairan',
            'title' => 'Formulir Pengajuan Pencairan Beasiswa KIP-Kuliah',
            'subtitle' => 'Tahap 1: Verifikasi kelengkapan berkas administrasi dan keaktifan studi mahasiswa',
            'is_active' => true,
        ]);

        $sec1 = $this->sekali(CustomFormSection::class, ['custom_form_id', 'section_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_code' => 'sec-p1',
            'title' => 'Data Identitas & Akademik Mahasiswa',
            'description' => 'Pastikan data diri dan informasi program studi sesuai dengan kartu tanda mahasiswa aktif UNUSA.',
            'after_section_action' => 'next',
            'order_index' => 1,
        ]);

        $this->sekali(CustomFormQuestion::class, ['custom_form_id', 'question_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_id' => $sec1->id,
            'question_code' => 'p_nama',
            'title' => 'Nama Lengkap Mahasiswa',
            'type' => 'Jawaban singkat',
            'required' => true,
            'placeholder' => 'Sesuai KTM / KTP',
            'order_index' => 1,
        ]);

        $this->sekali(CustomFormQuestion::class, ['custom_form_id', 'question_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_id' => $sec1->id,
            'question_code' => 'p_nim',
            'title' => 'Nomor Induk Mahasiswa (NIM)',
            'type' => 'Jawaban singkat',
            'required' => true,
            'placeholder' => 'Contoh: 2240021001',
            'order_index' => 2,
        ]);

        $this->sekali(CustomFormQuestion::class, ['custom_form_id', 'question_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_id' => $sec1->id,
            'question_code' => 'p_prodi',
            'title' => 'Program Studi',
            'type' => 'Drop-down',
            'options' => ['S1 Teknik Informatika', 'S1 Sistem Informasi', 'S1 Manajemen', 'S1 Pendidikan Dokter', 'S1 Keperawatan', 'S1 Gizi', 'D3 Kebidanan'],
            'required' => true,
            'order_index' => 3,
        ]);

        $sec2 = $this->sekali(CustomFormSection::class, ['custom_form_id', 'section_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_code' => 'sec-p2',
            'title' => 'Unggah Berkas Persyaratan Pencairan',
            'description' => 'Unggah dokumen resmi rekomendasi prodi dan surat pernyataan dalam format PDF atau Tautan Google Drive.',
            'after_section_action' => 'submit',
            'order_index' => 2,
        ]);

        $this->sekali(CustomFormQuestion::class, ['custom_form_id', 'question_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_id' => $sec2->id,
            'question_code' => 'p_surat_rekom',
            'title' => 'Tautan Berkas Surat Rekomendasi Program Studi',
            'type' => 'Jawaban singkat',
            'required' => true,
            'placeholder' => 'https://drive.google.com/file/...',
            'help_text' => 'Pastikan hak akses link Google Drive diatur ke "Siapa saja yang memiliki link dapat melihat".',
            'order_index' => 1,
        ]);

        $this->sekali(CustomFormQuestion::class, ['custom_form_id', 'question_code'], [
            'custom_form_id' => $formPencairan->id,
            'section_id' => $sec2->id,
            'question_code' => 'p_surat_pernyataan',
            'title' => 'Tautan Berkas Surat Pernyataan Mahasiswa Penerima KIP-K',
            'type' => 'Jawaban singkat',
            'required' => true,
            'placeholder' => 'https://drive.google.com/file/...',
            'order_index' => 2,
        ]);

        // Form Pelaporan & Form Monev
        $this->sekali(CustomForm::class, ['form_key'], [
            'form_key' => 'pelaporan',
            'title' => 'Formulir Pelaporan Keaktifan Organisasi & Prestasi Lomba',
            'subtitle' => 'Tahap 2: Dokumentasi partisipasi kegiatan kemahasiswaan dan kejuaraan perlombaan',
            'is_active' => true,
        ]);

        $this->sekali(CustomForm::class, ['form_key'], [
            'form_key' => 'monev',
            'title' => 'Formulir Evaluasi Capaian Indeks Prestasi (Monev Akademik)',
            'subtitle' => 'Tahap 3: Pemutakhiran nilai IPS/IPK serta kondisi sosial ekonomi keluarga penerima KIP-K',
            'is_active' => true,
        ]);
    }
}
