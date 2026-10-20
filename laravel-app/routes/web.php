<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - SEMADIKSI Portal
|--------------------------------------------------------------------------
*/

// Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/profil-semadiksi', [HomeController::class, 'profil'])->name('profil.semadiksi');
Route::get('/berita-acara', [HomeController::class, 'beritaAcaraIndex'])->name('berita.acara.index');
Route::get('/berita-acara/{id}', [HomeController::class, 'beritaAcaraShow'])->name('berita.acara.show');
// Public Verification & Dynamic Certificate
Route::match(['get', 'post'], '/verifikasi', [\App\Http\Controllers\CertificateController::class, 'verify'])->name('verifikasi.sertifikat');
Route::match(['get', 'post'], '/verifikasi-sertifikat', [\App\Http\Controllers\CertificateController::class, 'verify'])->name('verifikasi');
Route::get('/sertifikat/{id}/download', [\App\Http\Controllers\CertificateController::class, 'download'])->name('sertifikat.download');

// Two-Phase Attendance Routes
Route::post('/presensi/check-in-ticket', [\App\Http\Controllers\PresensiController::class, 'checkInTicket'])->name('presensi.check-in-ticket');
Route::get('/kegiatan/{kegiatan}/presensi', [\App\Http\Controllers\PresensiController::class, 'showSelfAttendance'])->name('kegiatan.presensi.show');
Route::post('/kegiatan/{kegiatan}/presensi', [\App\Http\Controllers\PresensiController::class, 'submitSelfAttendance'])->name('kegiatan.presensi.submit');
Route::get('/presensi/{kegiatan}', [\App\Http\Controllers\PresensiController::class, 'showSelfAttendance'])->name('presensi.self');
Route::post('/presensi/{kegiatan}', [\App\Http\Controllers\PresensiController::class, 'submitSelfAttendance'])->name('presensi.self.submit');

Route::get('/absensi', [HomeController::class, 'absensiPublic'])->name('absensi.public');
Route::post('/absensi', [HomeController::class, 'storeAbsensiPublic'])->name('absensi.public.store');

// Alamat login yang umum diketik; halaman login portal ada di /masuk.
Route::redirect('/login', '/masuk');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.post');

    // Pendaftaran dua jalur: Peserta Umum (isi manual) & Mahasiswa UNUSA (NIM -> link aktivasi ke email kampus).
    Route::get('/daftar', [\App\Http\Controllers\PendaftaranController::class, 'show'])->name('register');
    Route::post('/daftar', [\App\Http\Controllers\PendaftaranController::class, 'daftarUmum'])->name('register.post');
    Route::post('/daftar/unusa', [\App\Http\Controllers\PendaftaranController::class, 'cekNimUnusa'])->name('daftar.unusa.cek');
    Route::get('/daftar/unusa/aktivasi/{nim}', [\App\Http\Controllers\PendaftaranController::class, 'aktivasi'])->name('daftar.unusa.aktivasi');
    Route::post('/daftar/unusa/aktivasi/{nim}', [\App\Http\Controllers\PendaftaranController::class, 'selesaikanAktivasi'])->name('daftar.unusa.aktivasi.simpan');

    Route::get('/admin/masuk', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/masuk', [AuthController::class, 'adminLogin'])->name('admin.login.post');

    // Lupa kata sandi (semua role): respons netral, rate limit per IP & per email/NIM di controller.
    Route::get('/lupa-kata-sandi', [\App\Http\Controllers\LupaKataSandiController::class, 'form'])->name('password.request');
    Route::post('/lupa-kata-sandi', [\App\Http\Controllers\LupaKataSandiController::class, 'kirim'])->name('password.email');
    Route::get('/atur-ulang-kata-sandi/{token}', [\App\Http\Controllers\LupaKataSandiController::class, 'formAturUlang'])->name('password.reset');
    Route::post('/atur-ulang-kata-sandi', [\App\Http\Controllers\LupaKataSandiController::class, 'simpan'])->middleware('throttle:10,1')->name('password.update');
});

// Verifikasi email (pendaftar jalur Peserta Umum)
Route::middleware('auth')->group(function () {
    Route::get('/email/verifikasi', [\App\Http\Controllers\VerifikasiEmailController::class, 'pemberitahuan'])->name('verification.notice');
    Route::get('/email/verifikasi/{id}/{hash}', [\App\Http\Controllers\VerifikasiEmailController::class, 'verifikasi'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verifikasi/kirim-ulang', [\App\Http\Controllers\VerifikasiEmailController::class, 'kirimUlang'])->middleware('throttle:6,1')->name('verification.send');
});

// Authenticated Logout
Route::post('/keluar', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Student Dashboard Routes
Route::middleware(['auth', 'email.terverifikasi'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Klaim status penerima KIP Kuliah (akun user_umum jalur UNUSA)
    Route::post('/klaim-kip', [\App\Http\Controllers\KlaimKipController::class, 'store'])->name('klaim-kip');

    Route::get('/kegiatan', [DashboardController::class, 'kegiatan'])->name('kegiatan');
    Route::get('/kegiatan/{id}', [DashboardController::class, 'kegiatanShow'])->name('kegiatan.show');
    Route::post('/kegiatan/{id}/daftar', [DashboardController::class, 'registerKegiatan'])->name('kegiatan.daftar');

    Route::get('/kegiatan-saya', [DashboardController::class, 'kegiatanSaya'])->name('kegiatan.saya');
    Route::post('/kegiatan-saya/{registrationId}/upload-karya', [DashboardController::class, 'uploadKarya'])->name('kegiatan.upload-karya');
    Route::delete('/kegiatan-saya/{id}', [DashboardController::class, 'cancelKegiatanSaya'])->name('kegiatan.cancel');

    Route::get('/sertifikat', [DashboardController::class, 'sertifikat'])->name('sertifikat');

    Route::get('/absensi', [DashboardController::class, 'absensi'])->name('absensi');
    Route::post('/absensi', [DashboardController::class, 'storeAbsensi'])->name('absensi.store');

    Route::get('/pengajuan-pencairan', [DashboardController::class, 'pencairan'])->name('pencairan');
    Route::post('/pengajuan-pencairan', [DashboardController::class, 'storePencairan'])->name('pencairan.store');

    Route::get('/pelaporan', [DashboardController::class, 'pelaporan'])->name('pelaporan');
    Route::post('/pelaporan', [DashboardController::class, 'storePelaporan'])->name('pelaporan.store');

    Route::get('/monev-akademik', [DashboardController::class, 'monev'])->name('monev');
    Route::post('/monev-akademik', [DashboardController::class, 'storeMonev'])->name('monev.store');

    Route::get('/berkas-kipk', [DashboardController::class, 'berkas'])->name('berkas');
    Route::post('/berkas-kipk', [DashboardController::class, 'uploadBerkas'])->name('berkas.upload');
    Route::get('/berkas-kipk/{id}/download', [DashboardController::class, 'downloadBerkas'])->name('berkas.download');

    Route::get('/info-beasiswa', [DashboardController::class, 'beasiswa'])->name('beasiswa');
    Route::get('/info-kip', [DashboardController::class, 'infoKip'])->name('info.kip');

    Route::get('/profil', [DashboardController::class, 'profil'])->name('profil');
    Route::put('/profil', [DashboardController::class, 'updateProfil'])->name('profil.update');
    Route::put('/profil/password', [DashboardController::class, 'updatePassword'])->name('profil.password');
});

// Event Show & Booking Checkout Routes
Route::get('/kegiatan/{slugOrId}', [\App\Http\Controllers\KegiatanBookingController::class, 'show'])->name('kegiatan.detail');
Route::get('/kegiatan/{id}/status-kursi', [\App\Http\Controllers\KegiatanBookingController::class, 'statusKursi'])->whereNumber('id')->middleware('throttle:60,1')->name('kegiatan.status-kursi');
Route::get('/kegiatan/{id}/peserta-tanpa-kursi', [\App\Http\Controllers\KegiatanBookingController::class, 'pesertaTanpaKursi'])->whereNumber('id')->middleware('auth')->name('kegiatan.peserta-tanpa-kursi');
Route::post('/kegiatan/{id}/checkout', [\App\Http\Controllers\KegiatanBookingController::class, 'checkout'])->name('kegiatan.checkout')->middleware(['auth', 'email.terverifikasi']);
Route::post('/kegiatan/{id}/reselect-seat', [\App\Http\Controllers\KegiatanBookingController::class, 'reselectSeat'])->name('kegiatan.reselect-seat')->middleware('auth');
Route::post('/kegiatan/{id}/pilih-kursi', [\App\Http\Controllers\KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.pilih-kursi');
Route::post('/kegiatan/{id}/book-seats', [\App\Http\Controllers\KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.book-seats');
Route::post('/kegiatan/{id}/alokasi-kursi', [\App\Http\Controllers\KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.alokasi-kursi');

// Admin & Staff Dashboard Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // 1. Super Admin Exclusive Routes
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Enterprise Kegiatan CRUD & Seat Setup
        Route::get('/kegiatan', [\App\Http\Controllers\Admin\KegiatanController::class, 'index'])->name('kegiatan');
        Route::post('/kegiatan', [\App\Http\Controllers\Admin\KegiatanController::class, 'store'])->name('kegiatan.store');
        Route::get('/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'show'])->name('kegiatan.show');
        Route::match(['put', 'patch'], '/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'update'])->name('kegiatan.update');
        Route::delete('/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'destroy'])->name('kegiatan.delete');

        // Dynamic Venue Layout & Booking Lifecycle Config (Super Admin Only)
        Route::match(['put', 'patch'], '/kegiatan/{id}/venue-config', [\App\Http\Controllers\Admin\KegiatanSeatController::class, 'updateVenueConfig'])->name('kegiatan.venue-config');
        Route::post('/kegiatan/{id}/venue-config/pratinjau', [\App\Http\Controllers\Admin\KegiatanSeatController::class, 'pratinjau'])->name('kegiatan.venue-config.pratinjau');

        // Certificate Template & Dynamic Generator Routes (Admin)
        Route::post('/kegiatan/{id}/template-sertifikat', [\App\Http\Controllers\CertificateController::class, 'uploadTemplate'])->name('kegiatan.certificate-template');
        Route::post('/kegiatan/{id}/schema-sertifikat', [\App\Http\Controllers\CertificateController::class, 'updateSchemaConfig'])->name('kegiatan.certificate-schema');
        Route::post('/kegiatan/{id}/generate-sertifikat', [\App\Http\Controllers\CertificateController::class, 'generateBatch'])->name('kegiatan.certificate-batch');

        // User Matrix & Tracking
        Route::get('/pengguna', [\App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users');
        Route::post('/pengguna', [\App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('users.store');
        Route::match(['put', 'patch'], '/pengguna/{id}', [\App\Http\Controllers\Admin\UserManagementController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::match(['post', 'patch'], '/pengguna/{id}/toggle-block', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleBlock'])->name('users.toggle-block');
        Route::match(['post', 'patch'], '/pengguna/{id}/toggle_block', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleBlock'])->name('users.toggle_block');
        Route::match(['post', 'patch'], '/pengguna/{id}/verify', [\App\Http\Controllers\Admin\UserManagementController::class, 'verify'])->name('users.verify');

        // Data Mahasiswa UNUSA (master hasil impor CSV)
        Route::get('/mahasiswa-unusa', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'index'])->name('mahasiswa-unusa');
        Route::get('/mahasiswa-unusa/template', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'template'])->name('mahasiswa-unusa.template');
        Route::post('/mahasiswa-unusa/unggah', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'unggah'])->name('mahasiswa-unusa.unggah');
        Route::get('/mahasiswa-unusa/pratinjau', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'pratinjau'])->name('mahasiswa-unusa.pratinjau');
        Route::post('/mahasiswa-unusa/impor', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'simpan'])->name('mahasiswa-unusa.impor');
        Route::post('/mahasiswa-unusa/batal', [\App\Http\Controllers\Admin\MasterMahasiswaController::class, 'batal'])->name('mahasiswa-unusa.batal');

        // Daftar Penerima KIP-K (impor berbasis nomor pendaftaran SIM KIP Kuliah)
        Route::get('/penerima-kipk', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'index'])->name('penerima-kipk');
        Route::get('/penerima-kipk/template', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'template'])->name('penerima-kipk.template');
        Route::post('/penerima-kipk/unggah', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'unggah'])->name('penerima-kipk.unggah');
        Route::get('/penerima-kipk/pratinjau', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'pratinjau'])->name('penerima-kipk.pratinjau');
        Route::post('/penerima-kipk/impor', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'simpan'])->name('penerima-kipk.impor');
        Route::post('/penerima-kipk/batal', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'batal'])->name('penerima-kipk.batal');
        Route::post('/penerima-kipk/{id}/nomor', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'bukaNomor'])->whereNumber('id')->name('penerima-kipk.nomor');
        Route::post('/penerima-kipk/{id}/tinjau', [\App\Http\Controllers\Admin\PenerimaKipkController::class, 'tinjau'])->whereNumber('id')->name('penerima-kipk.tinjau');
        Route::post('/pengguna/{id}/status-kip', [\App\Http\Controllers\Admin\UserManagementController::class, 'statusKip'])->whereNumber('id')->name('users.status-kip');
        Route::post('/pengguna/{id}/akademik', [\App\Http\Controllers\Admin\UserManagementController::class, 'akademik'])->whereNumber('id')->name('users.akademik');

        // Legacy & Admin Certificates
        Route::get('/sertifikat', [\App\Http\Controllers\Admin\DashboardController::class, 'certificates'])->name('certificates');
        Route::post('/sertifikat', [\App\Http\Controllers\Admin\DashboardController::class, 'storeCertificate'])->name('certificates.store');
        Route::delete('/sertifikat/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'deleteCertificate'])->name('certificates.delete');

        // Admin Attendances
        Route::get('/absensi', [\App\Http\Controllers\Admin\DashboardController::class, 'attendances'])->name('attendances');
        Route::match(['post', 'patch'], '/absensi/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.update');
        Route::match(['post', 'patch'], '/absensi/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.status');
    });

    // 2. Verifikasi Berkas KIP-K & Monev Submissions (Role super_admin & verifikator_berkas)
    Route::middleware(['role:super_admin,verifikator_berkas'])->group(function () {
        Route::get('/berkas', [\App\Http\Controllers\Admin\DashboardController::class, 'berkas'])->name('berkas');
        Route::get('/berkas/{id}/download', [\App\Http\Controllers\Admin\DashboardController::class, 'downloadDoc'])->name('berkas.download');
        Route::match(['post', 'patch'], '/berkas/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.update');
        Route::match(['post', 'patch'], '/berkas/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.status');

        Route::get('/submissions', [\App\Http\Controllers\Admin\DashboardController::class, 'submissions'])->name('submissions');
        Route::match(['post', 'patch'], '/submissions/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.update');
        Route::match(['post', 'patch'], '/submissions/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.status');
    });

    // 3. Penjurian & Penilaian Karya (Role super_admin & penilai_karya)
    Route::middleware(['role:super_admin,penilai_karya'])->group(function () {
        Route::get('/penilaian', [\App\Http\Controllers\PenilaianKaryaController::class, 'index'])->name('penilaian');
        Route::post('/penilaian/{id}/score', [\App\Http\Controllers\PenilaianKaryaController::class, 'updateScore'])->name('penilaian.score');
        Route::get('/penilaian/{kegiatanId}/export', [\App\Http\Controllers\PenilaianKaryaController::class, 'exportCsv'])->name('penilaian.export');
        Route::post('/penilaian/{kegiatanId}/import', [\App\Http\Controllers\PenilaianKaryaController::class, 'importCsv'])->name('penilaian.import');
    });
});
