<?php

use App\Http\Controllers\Admin\KegiatanController;
use App\Http\Controllers\Admin\KegiatanSeatController;
use App\Http\Controllers\Admin\MasterMahasiswaController;
use App\Http\Controllers\Admin\PenerimaKipkController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\KeycloakController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KegiatanBookingController;
use App\Http\Controllers\KlaimKipController;
use App\Http\Controllers\LupaKataSandiController;
use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\PenilaianKaryaController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\VerifikasiEmailController;
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
Route::match(['get', 'post'], '/verifikasi', [CertificateController::class, 'verify'])->name('verifikasi.sertifikat');
Route::match(['get', 'post'], '/verifikasi-sertifikat', [CertificateController::class, 'verify'])->name('verifikasi');
Route::get('/sertifikat/{id}/download', [CertificateController::class, 'download'])->name('sertifikat.download');

// Two-Phase Attendance Routes
Route::post('/presensi/check-in-ticket', [PresensiController::class, 'checkInTicket'])->name('presensi.check-in-ticket');
Route::get('/kegiatan/{kegiatan}/presensi', [PresensiController::class, 'showSelfAttendance'])->name('kegiatan.presensi.show');
Route::post('/kegiatan/{kegiatan}/presensi', [PresensiController::class, 'submitSelfAttendance'])->name('kegiatan.presensi.submit');
Route::get('/presensi/{kegiatan}', [PresensiController::class, 'showSelfAttendance'])->name('presensi.self');
Route::post('/presensi/{kegiatan}', [PresensiController::class, 'submitSelfAttendance'])->name('presensi.self.submit');

Route::get('/absensi', [HomeController::class, 'absensiPublic'])->name('absensi.public');
Route::post('/absensi', [HomeController::class, 'storeAbsensiPublic'])->name('absensi.public.store');

// Alamat login yang umum diketik; halaman login portal ada di /masuk.
Route::redirect('/login', '/masuk');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.post');

    // SSO Keycloak UNUSA (OAuth2 / PKCE)
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/auth/login', [KeycloakController::class, 'redirect'])->name('auth.login');
        Route::get('/auth/login/callback', [KeycloakController::class, 'callback'])->name('auth.login.callback');
    });

    // Pendaftaran Mahasiswa UNUSA: NIM -> kartu tersamar -> link aktivasi ke email kampus -> buat kata sandi.
    Route::get('/daftar', [PendaftaranController::class, 'show'])->name('register');
    Route::post('/daftar/unusa', [PendaftaranController::class, 'cekNimUnusa'])->name('daftar.unusa.cek');
    Route::post('/daftar/unusa/kirim', [PendaftaranController::class, 'kirimLink'])->name('daftar.unusa.kirim');
    Route::get('/daftar/unusa/aktivasi/{token}', [PendaftaranController::class, 'aktivasi'])->name('daftar.unusa.aktivasi');
    Route::post('/daftar/unusa/aktivasi/{token}', [PendaftaranController::class, 'selesaikanAktivasi'])->name('daftar.unusa.aktivasi.simpan');

    // Jalur Peserta Umum: 404 selama config unusa.registrasi_umum_aktif (env REGISTRASI_UMUM_AKTIF) nonaktif.
    Route::get('/daftar/umum', [PendaftaranController::class, 'showUmum'])->name('register.umum');
    Route::post('/daftar', [PendaftaranController::class, 'daftarUmum'])->name('register.post');

    Route::get('/admin/masuk', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/masuk', [AuthController::class, 'adminLogin'])->name('admin.login.post');

    // Lupa kata sandi (semua role): respons netral, rate limit per IP & per email/NIM di controller.
    Route::get('/lupa-kata-sandi', [LupaKataSandiController::class, 'form'])->name('password.request');
    Route::post('/lupa-kata-sandi', [LupaKataSandiController::class, 'kirim'])->name('password.email');
    Route::get('/atur-ulang-kata-sandi/{token}', [LupaKataSandiController::class, 'formAturUlang'])->name('password.reset');
    Route::post('/atur-ulang-kata-sandi', [LupaKataSandiController::class, 'simpan'])->middleware('throttle:10,1')->name('password.update');
});

// Verifikasi email (pendaftar jalur Peserta Umum)
Route::middleware('auth')->group(function () {
    Route::get('/email/verifikasi', [VerifikasiEmailController::class, 'pemberitahuan'])->name('verification.notice');
    Route::get('/email/verifikasi/{id}/{hash}', [VerifikasiEmailController::class, 'verifikasi'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verifikasi/kirim-ulang', [VerifikasiEmailController::class, 'kirimUlang'])->middleware('throttle:6,1')->name('verification.send');
});

// Two-Factor Authentication (2FA) Routes
Route::middleware('auth')->prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
    Route::get('/challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [TwoFactorController::class, 'verify'])->name('challenge.verify');
});

// Authenticated Logout
Route::post('/keluar', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Student Dashboard Routes
Route::middleware(['auth', 'email.terverifikasi'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Klaim status penerima KIP Kuliah (akun user_umum jalur UNUSA)
    Route::post('/klaim-kip', [KlaimKipController::class, 'store'])->name('klaim-kip');

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
Route::get('/kegiatan/{slugOrId}', [KegiatanBookingController::class, 'show'])->name('kegiatan.detail');
Route::get('/kegiatan/{id}/status-kursi', [KegiatanBookingController::class, 'statusKursi'])->whereNumber('id')->middleware('throttle:60,1')->name('kegiatan.status-kursi');
Route::get('/kegiatan/{id}/peserta-tanpa-kursi', [KegiatanBookingController::class, 'pesertaTanpaKursi'])->whereNumber('id')->middleware('auth')->name('kegiatan.peserta-tanpa-kursi');
Route::post('/kegiatan/{id}/checkout', [KegiatanBookingController::class, 'checkout'])->name('kegiatan.checkout')->middleware(['auth', 'email.terverifikasi']);
Route::post('/kegiatan/{id}/reselect-seat', [KegiatanBookingController::class, 'reselectSeat'])->name('kegiatan.reselect-seat')->middleware('auth');
Route::post('/kegiatan/{id}/pilih-kursi', [KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.pilih-kursi');
Route::post('/kegiatan/{id}/book-seats', [KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.book-seats');
Route::post('/kegiatan/{id}/alokasi-kursi', [KegiatanBookingController::class, 'bookSeats'])->whereNumber('id')->middleware('auth')->name('kegiatan.alokasi-kursi');

// Admin & Staff Dashboard Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // 1. Super Admin Exclusive Routes
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Enterprise Kegiatan CRUD & Seat Setup
        Route::get('/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan');
        Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('kegiatan.store');
        Route::get('/kegiatan/{id}', [KegiatanController::class, 'show'])->name('kegiatan.show');
        Route::match(['put', 'patch'], '/kegiatan/{id}', [KegiatanController::class, 'update'])->name('kegiatan.update');
        Route::delete('/kegiatan/{id}', [KegiatanController::class, 'destroy'])->name('kegiatan.delete');

        // Dynamic Venue Layout & Booking Lifecycle Config (Super Admin Only)
        Route::match(['put', 'patch'], '/kegiatan/{id}/venue-config', [KegiatanSeatController::class, 'updateVenueConfig'])->name('kegiatan.venue-config');
        Route::post('/kegiatan/{id}/venue-config/pratinjau', [KegiatanSeatController::class, 'pratinjau'])->name('kegiatan.venue-config.pratinjau');

        // Certificate Template & Dynamic Generator Routes (Admin)
        Route::post('/kegiatan/{id}/template-sertifikat', [CertificateController::class, 'uploadTemplate'])->name('kegiatan.certificate-template');
        Route::post('/kegiatan/{id}/schema-sertifikat', [CertificateController::class, 'updateSchemaConfig'])->name('kegiatan.certificate-schema');
        Route::post('/kegiatan/{id}/generate-sertifikat', [CertificateController::class, 'generateBatch'])->name('kegiatan.certificate-batch');

        // User Matrix & Tracking
        Route::get('/pengguna', [UserManagementController::class, 'index'])->name('users');
        Route::post('/pengguna', [UserManagementController::class, 'store'])->name('users.store');
        Route::match(['put', 'patch'], '/pengguna/{id}', [UserManagementController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::match(['post', 'patch'], '/pengguna/{id}/toggle-block', [UserManagementController::class, 'toggleBlock'])->name('users.toggle-block');
        Route::match(['post', 'patch'], '/pengguna/{id}/toggle_block', [UserManagementController::class, 'toggleBlock'])->name('users.toggle_block');
        Route::match(['post', 'patch'], '/pengguna/{id}/verify', [UserManagementController::class, 'verify'])->name('users.verify');
        Route::post('/pengguna/{id}/reset-2fa', [UserManagementController::class, 'resetTwoFactor'])->whereNumber('id')->name('users.reset-2fa');

        // Data Mahasiswa UNUSA (master hasil impor CSV)
        Route::get('/mahasiswa-unusa', [MasterMahasiswaController::class, 'index'])->name('mahasiswa-unusa');
        Route::get('/mahasiswa-unusa/template', [MasterMahasiswaController::class, 'template'])->name('mahasiswa-unusa.template');
        Route::post('/mahasiswa-unusa/unggah', [MasterMahasiswaController::class, 'unggah'])->name('mahasiswa-unusa.unggah');
        Route::get('/mahasiswa-unusa/pratinjau', [MasterMahasiswaController::class, 'pratinjau'])->name('mahasiswa-unusa.pratinjau');
        Route::post('/mahasiswa-unusa/impor', [MasterMahasiswaController::class, 'simpan'])->name('mahasiswa-unusa.impor');
        Route::post('/mahasiswa-unusa/batal', [MasterMahasiswaController::class, 'batal'])->name('mahasiswa-unusa.batal');

        // Daftar Penerima KIP-K (impor berbasis nomor pendaftaran SIM KIP Kuliah)
        Route::get('/penerima-kipk', [PenerimaKipkController::class, 'index'])->name('penerima-kipk');
        Route::get('/penerima-kipk/template', [PenerimaKipkController::class, 'template'])->name('penerima-kipk.template');
        Route::post('/penerima-kipk/unggah', [PenerimaKipkController::class, 'unggah'])->name('penerima-kipk.unggah');
        Route::get('/penerima-kipk/pratinjau', [PenerimaKipkController::class, 'pratinjau'])->name('penerima-kipk.pratinjau');
        Route::post('/penerima-kipk/impor', [PenerimaKipkController::class, 'simpan'])->name('penerima-kipk.impor');
        Route::post('/penerima-kipk/batal', [PenerimaKipkController::class, 'batal'])->name('penerima-kipk.batal');
        Route::post('/penerima-kipk/{id}/nomor', [PenerimaKipkController::class, 'bukaNomor'])->whereNumber('id')->name('penerima-kipk.nomor');
        Route::post('/penerima-kipk/{id}/tinjau', [PenerimaKipkController::class, 'tinjau'])->whereNumber('id')->name('penerima-kipk.tinjau');
        Route::post('/pengguna/{id}/status-kip', [UserManagementController::class, 'statusKip'])->whereNumber('id')->name('users.status-kip');
        Route::post('/pengguna/{id}/akademik', [UserManagementController::class, 'akademik'])->whereNumber('id')->name('users.akademik');

        // Legacy & Admin Certificates
        Route::get('/sertifikat', [App\Http\Controllers\Admin\DashboardController::class, 'certificates'])->name('certificates');
        Route::post('/sertifikat', [App\Http\Controllers\Admin\DashboardController::class, 'storeCertificate'])->name('certificates.store');
        Route::delete('/sertifikat/{id}', [App\Http\Controllers\Admin\DashboardController::class, 'deleteCertificate'])->name('certificates.delete');

        // Admin Attendances
        Route::get('/absensi', [App\Http\Controllers\Admin\DashboardController::class, 'attendances'])->name('attendances');
        Route::get('/absensi/export', [App\Http\Controllers\Admin\DashboardController::class, 'exportAttendances'])->name('attendances.export');
        Route::get('/presensi/{id}/signature', [App\Http\Controllers\Admin\DashboardController::class, 'signaturePreview'])->name('attendances.signature');
        Route::match(['post', 'patch'], '/absensi/{id}', [App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.update');
        Route::match(['post', 'patch'], '/absensi/{id}/status', [App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.status');
    });

    // 2. Verifikasi Berkas KIP-K & Monev Submissions (Role super_admin & verifikator_berkas)
    Route::middleware(['role:super_admin,verifikator_berkas'])->group(function () {
        Route::get('/berkas', [App\Http\Controllers\Admin\DashboardController::class, 'berkas'])->name('berkas');
        Route::get('/berkas/{id}/download', [App\Http\Controllers\Admin\DashboardController::class, 'downloadDoc'])->name('berkas.download');
        Route::match(['post', 'patch'], '/berkas/{id}', [App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.update');
        Route::match(['post', 'patch'], '/berkas/{id}/status', [App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.status');

        Route::get('/submissions', [App\Http\Controllers\Admin\DashboardController::class, 'submissions'])->name('submissions');
        Route::match(['post', 'patch'], '/submissions/{id}', [App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.update');
        Route::match(['post', 'patch'], '/submissions/{id}/status', [App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.status');
    });

    // 3. Penjurian & Penilaian Karya (Role super_admin & penilai_karya)
    Route::middleware(['role:super_admin,penilai_karya'])->group(function () {
        Route::get('/penilaian', [PenilaianKaryaController::class, 'index'])->name('penilaian');
        Route::post('/penilaian/{id}/score', [PenilaianKaryaController::class, 'updateScore'])->name('penilaian.score');
        Route::get('/penilaian/{kegiatanId}/export', [PenilaianKaryaController::class, 'export'])->name('penilaian.export');
        Route::get('/penilaian/{kegiatanId}/export/xlsx', [PenilaianKaryaController::class, 'exportXlsx'])->name('penilaian.export.xlsx');
        Route::get('/penilaian/{kegiatanId}/export/csv', [PenilaianKaryaController::class, 'exportCsv'])->name('penilaian.export.csv');
        Route::post('/penilaian/{kegiatanId}/import', [PenilaianKaryaController::class, 'import'])->name('penilaian.import');
        Route::post('/penilaian/{kegiatanId}/import/xlsx', [PenilaianKaryaController::class, 'importXlsx'])->name('penilaian.import.xlsx');
        Route::post('/penilaian/{kegiatanId}/import/csv', [PenilaianKaryaController::class, 'importCsv'])->name('penilaian.import.csv');
    });
});
