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

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.post');

    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->name('register.post');

    Route::get('/admin/masuk', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/masuk', [AuthController::class, 'adminLogin'])->name('admin.login.post');
});

// Authenticated Logout
Route::post('/keluar', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Student Dashboard Routes
Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

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

    Route::get('/info-beasiswa', [DashboardController::class, 'beasiswa'])->name('beasiswa');
    Route::get('/info-kip', [DashboardController::class, 'infoKip'])->name('info.kip');

    Route::get('/profil', [DashboardController::class, 'profil'])->name('profil');
    Route::put('/profil', [DashboardController::class, 'updateProfil'])->name('profil.update');
    Route::put('/profil/password', [DashboardController::class, 'updatePassword'])->name('profil.password');
});

// Event Show & Booking Checkout Routes
Route::get('/kegiatan/{slugOrId}', [\App\Http\Controllers\KegiatanBookingController::class, 'show'])->name('kegiatan.detail');
Route::post('/kegiatan/{id}/checkout', [\App\Http\Controllers\KegiatanBookingController::class, 'checkout'])->name('kegiatan.checkout')->middleware('auth');

// Admin & Staff Dashboard Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Enterprise Kegiatan CRUD & Seat Setup
    Route::get('/kegiatan', [\App\Http\Controllers\Admin\KegiatanController::class, 'index'])->name('kegiatan');
    Route::post('/kegiatan', [\App\Http\Controllers\Admin\KegiatanController::class, 'store'])->name('kegiatan.store');
    Route::get('/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'show'])->name('kegiatan.show');
    Route::match(['put', 'patch'], '/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'update'])->name('kegiatan.update');
    Route::delete('/kegiatan/{id}', [\App\Http\Controllers\Admin\KegiatanController::class, 'destroy'])->name('kegiatan.delete');

    // Certificate Template & Dynamic Generator Routes (Admin)
    Route::post('/kegiatan/{id}/template-sertifikat', [\App\Http\Controllers\CertificateController::class, 'uploadTemplate'])->name('kegiatan.certificate-template');
    Route::post('/kegiatan/{id}/schema-sertifikat', [\App\Http\Controllers\CertificateController::class, 'updateSchemaConfig'])->name('kegiatan.certificate-schema');
    Route::post('/kegiatan/{id}/generate-sertifikat', [\App\Http\Controllers\CertificateController::class, 'generateBatch'])->name('kegiatan.certificate-batch');

    // User Matrix & Tracking
    Route::get('/pengguna', [\App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users');
    Route::post('/pengguna', [\App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('users.store');
    Route::match(['post', 'patch'], '/pengguna/{id}/toggle-block', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleBlock'])->name('users.toggle-block');
    Route::match(['post', 'patch'], '/pengguna/{id}/toggle_block', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleBlock'])->name('users.toggle_block');
    Route::match(['post', 'patch'], '/pengguna/{id}/verify', [\App\Http\Controllers\Admin\UserManagementController::class, 'verify'])->name('users.verify');

    // Penjurian & Penilaian Karya (Role penilai_karya & super_admin)
    Route::middleware(['role:super_admin,penilai_karya'])->group(function () {
        Route::get('/penilaian', [\App\Http\Controllers\PenilaianKaryaController::class, 'index'])->name('penilaian');
        Route::post('/penilaian/{id}/score', [\App\Http\Controllers\PenilaianKaryaController::class, 'updateScore'])->name('penilaian.score');
        Route::get('/penilaian/{kegiatanId}/export', [\App\Http\Controllers\PenilaianKaryaController::class, 'exportCsv'])->name('penilaian.export');
        Route::post('/penilaian/{kegiatanId}/import', [\App\Http\Controllers\PenilaianKaryaController::class, 'importCsv'])->name('penilaian.import');
    });

    // Legacy & Other Modules
    Route::get('/sertifikat', [\App\Http\Controllers\Admin\DashboardController::class, 'certificates'])->name('certificates');
    Route::post('/sertifikat', [\App\Http\Controllers\Admin\DashboardController::class, 'storeCertificate'])->name('certificates.store');
    Route::delete('/sertifikat/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'deleteCertificate'])->name('certificates.delete');

    Route::get('/absensi', [\App\Http\Controllers\Admin\DashboardController::class, 'attendances'])->name('attendances');
    Route::match(['post', 'patch'], '/absensi/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.update');
    Route::match(['post', 'patch'], '/absensi/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateAttendanceStatus'])->name('attendances.status');

    Route::get('/berkas', [\App\Http\Controllers\Admin\DashboardController::class, 'berkas'])->name('berkas');
    Route::match(['post', 'patch'], '/berkas/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.update');
    Route::match(['post', 'patch'], '/berkas/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateDocStatus'])->name('berkas.status');

    Route::get('/submissions', [\App\Http\Controllers\Admin\DashboardController::class, 'submissions'])->name('submissions');
    Route::match(['post', 'patch'], '/submissions/{id}', [\App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.update');
    Route::match(['post', 'patch'], '/submissions/{id}/status', [\App\Http\Controllers\Admin\DashboardController::class, 'updateSubmissionStatus'])->name('submissions.status');
});
