<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Activities (Kegiatan)
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('Seminar');
            $table->string('type')->default('Offline');
            $table->string('date_text')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('Segera Hadir');
            $table->string('price')->default('Gratis');
            $table->jsonb('tags')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('location')->nullable();
            $table->text('rundown')->nullable();
            $table->string('speaker')->nullable();
            $table->boolean('require_file_upload')->default(false);
            $table->text('file_upload_instruction')->nullable();
            $table->boolean('enable_seat_booking')->default(false);
            $table->jsonb('seat_layout_config')->nullable();
            $table->integer('quota')->default(100);
            $table->integer('xp_points')->default(50);
            $table->timestamps();
        });

        // 2. Activity Registrations (Pendaftaran Kegiatan Mahasiswa)
        Schema::create('activity_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('selected_seat')->nullable();
            $table->string('uploaded_file_url')->nullable();
            $table->string('payment_status')->default('Gratis'); // Gratis, Pending, Lunas
            $table->string('status')->default('Terdaftar'); // Terdaftar, Selesai, Batal
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'activity_id']);
        });

        // 3. Attendances (Absensi & Presensi Kegiatan)
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_title');
            $table->string('activity_category')->nullable();
            $table->string('activity_date')->nullable();
            $table->string('student_name');
            $table->string('student_nim');
            $table->string('university')->default('Universitas Nahdlatul Ulama Surabaya');
            $table->string('student_email')->nullable();
            $table->string('student_phone')->nullable();
            $table->text('proof_image_url');
            $table->string('proof_file_name')->nullable();
            $table->string('status')->default('Hadir'); // Hadir, Menunggu Verifikasi, Ditolak
            $table->text('notes')->nullable();
            $table->string('device_info')->nullable();
            $table->string('location_name')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        // 4. Certificates (Sertifikat Kegiatan & Validasi)
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('activity_title');
            $table->string('code')->unique(); // Unique Certificate Verification Code
            $table->string('status')->default('Berlaku'); // Berlaku, Dicabut
            $table->jsonb('signatories')->nullable();
            $table->date('issued_date')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamps();
        });

        // 5. Berita Acara
        Schema::create('berita_acara', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('Rapat Kerja');
            $table->string('status')->default('Selesai'); // Selesai, Akan Datang
            $table->string('date_text');
            $table->string('time_text')->nullable();
            $table->string('location');
            $table->string('organizer');
            $table->integer('attendee_count')->nullable()->default(0);
            $table->text('summary');
            $table->text('content');
            $table->string('banner_img')->nullable();
            $table->string('attachment_file_name')->nullable();
            $table->string('attachment_file_url')->nullable();
            $table->string('external_link')->nullable();
            $table->string('author')->default('Pengurus SEMADIKSI');
            $table->timestamps();
        });

        // 6. Scholarships (Info Beasiswa)
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('provider');
            $table->string('category')->default('KIP Kuliah');
            $table->string('status')->default('Dibuka'); // Dibuka, Segera Dibuka, Ditutup
            $table->string('open_date');
            $table->string('close_date');
            $table->string('coverage');
            $table->jsonb('requirements')->nullable();
            $table->text('selection_stages')->nullable();
            $table->text('description');
            $table->string('banner_img')->nullable();
            $table->string('guide_file_name')->nullable();
            $table->string('guide_file_url')->nullable();
            $table->string('apply_url')->nullable();
            $table->timestamps();
        });

        // 7. KIP Infos (Informasi, Regulasi & FAQ KIP-K)
        Schema::create('kip_infos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('Pengumuman');
            $table->text('content');
            $table->string('date_uploaded')->nullable();
            $table->string('author')->default('Biro Kemahasiswaan');
            $table->string('attachment_file_name')->nullable();
            $table->string('attachment_url')->nullable();
            $table->string('priority')->default('Normal'); // Tinggi, Normal
            $table->timestamps();
        });

        // 8. KIP-K Documents (Berkas KIP-K Tahap 1, 2, 3)
        Schema::create('kipk_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('user_name');
            $table->string('user_email')->nullable();
            $table->string('user_nim')->nullable();
            $table->string('category'); // Kartu KIP-K, SKTM, Keaktifan Ormawa, dsb.
            $table->smallInteger('tahap')->default(1); // 1=Pencairan, 2=Pelaporan, 3=Monev
            $table->string('title');
            $table->string('file_name');
            $table->string('file_size')->nullable();
            $table->string('file_type')->nullable(); // pdf, image, doc
            $table->text('file_url');
            $table->string('uploaded_by')->default('Mahasiswa'); // Mahasiswa, Admin
            $table->string('status')->default('Menunggu Review'); // Disetujui, Perlu Perbaikan, Menunggu Review
            $table->integer('score')->default(0); // 0 - 100
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamps();
        });

        // 9. Custom Form Builder (Pencairan, Pelaporan, Monev, dll)
        Schema::create('custom_forms', function (Blueprint $table) {
            $table->id();
            $table->string('form_key')->unique(); // 'pencairan', 'pelaporan', 'monev', etc.
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('section_code')->nullable(); // e.g. 'sec-p1'
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('after_section_action')->default('next'); // next, submit
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('custom_form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('custom_form_sections')->nullOnDelete();
            $table->string('question_code')->nullable();
            $table->string('title');
            $table->string('type'); // Jawaban singkat, Paragraf, Pilihan ganda, Kotak Centang, Drop-down, Upload file, Skala linier, Tanggal
            $table->jsonb('options')->nullable();
            $table->boolean('required')->default(true);
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('custom_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('applicant_name')->nullable();
            $table->string('applicant_nim')->nullable();
            $table->string('applicant_email')->nullable();
            $table->jsonb('answers'); // { question_id: answer_val, ... }
            $table->string('status')->default('Menunggu Review'); // Disetujui, Perlu Perbaikan, Menunggu Review
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_form_submissions');
        Schema::dropIfExists('custom_form_questions');
        Schema::dropIfExists('custom_form_sections');
        Schema::dropIfExists('custom_forms');
        Schema::dropIfExists('kipk_documents');
        Schema::dropIfExists('kip_infos');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('berita_acara');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('activity_registrations');
        Schema::dropIfExists('activities');
    }
};
