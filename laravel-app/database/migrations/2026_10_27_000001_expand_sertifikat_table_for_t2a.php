<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Perluas tabel sertifikat
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->foreignId('registration_id')->nullable()->after('kegiatan_id')->constrained('kegiatan_registrations')->nullOnDelete();
            $table->string('penerima_key', 64)->nullable()->after('user_id');
            $table->string('jenis', 32)->default('peserta')->after('presensi_id');
            $table->string('predikat', 100)->nullable()->after('jenis');
            $table->string('asal_institusi')->nullable()->after('nama_penerima');
            $table->string('nim_penerima', 50)->nullable()->after('asal_institusi');
            $table->text('keterangan_publik')->nullable()->after('document_hash');
            $table->string('status', 20)->default('aktif')->after('keterangan_publik');
            $table->text('alasan_pencabutan')->nullable()->after('status');
            $table->timestamp('dicabut_pada')->nullable()->after('alasan_pencabutan');
            $table->foreignId('dicabut_oleh')->nullable()->after('dicabut_pada')->constrained('users')->nullOnDelete();
            $table->json('penandatangan_snapshot')->nullable()->after('file_pdf_path');
            $table->unsignedInteger('nomor_urut')->nullable()->after('nomor_sertifikat');

            $table->index('jenis');
            $table->index('status');
            $table->unique(['kegiatan_id', 'penerima_key', 'jenis'], 'sertifikat_kegiatan_penerima_jenis_unique');
        });

        // 2. Data lama: isi penerima_key, jenis = peserta, status = aktif
        if (Schema::hasTable('sertifikat')) {
            $rows = DB::table('sertifikat')->get();
            foreach ($rows as $row) {
                $penerimaKey = $row->user_id
                    ? 'user:'.$row->user_id
                    : 'ext:'.hash('sha256', Str::lower(trim($row->nama_penerima ?? '')).'|'.Str::lower(trim($row->asal_institusi ?? '')));

                DB::table('sertifikat')->where('id', $row->id)->update([
                    'jenis' => 'peserta',
                    'status' => 'aktif',
                    'penerima_key' => $penerimaKey,
                    'asal_institusi' => $row->asal_institusi ?? 'Universitas Nahdlatul Ulama Surabaya',
                ]);
            }
        }

        // 3. Tabel riwayat perubahan data tampilan
        Schema::create('sertifikat_riwayat_perubahan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sertifikat_id')->constrained('sertifikat')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('kolom_diubah', 50);
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
            $table->text('alasan');
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. Master data penandatangan
        Schema::create('penandatangan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('gelar')->nullable();
            $table->string('jabatan');
            $table->string('jenis_identitas', 20)->default('NIP');
            $table->string('nomor_identitas')->nullable();
            $table->string('institusi');
            $table->string('tanda_tangan_path')->nullable();
            $table->string('mode_tampil', 20)->default('sembunyikan'); // sembunyikan, samarkan, penuh
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pindahkan data penandatangan yang sudah dipakai sertifikat lama ke master dengan mode 'penuh' agar tidak mengubah tampilan lama
        DB::table('penandatangan')->insert([
            [
                'nama' => 'Dr. Wahyu Utomo',
                'gelar' => 'M.Kom.',
                'jabatan' => 'Ketua Panitia / Pembina',
                'jenis_identitas' => 'NIP',
                'nomor_identitas' => '197508122002121001',
                'institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'tanda_tangan_path' => null,
                'mode_tampil' => 'penuh',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Muhammad Fatih',
                'gelar' => null,
                'jabatan' => 'Ketua Pelaksana',
                'jenis_identitas' => 'NIM',
                'nomor_identitas' => '3130021045',
                'institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'tanda_tangan_path' => null,
                'mode_tampil' => 'penuh',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 5. Penugasan penandatangan per kegiatan dan jenis sertifikat
        Schema::create('kegiatan_penandatangan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('penandatangan_id')->constrained('penandatangan')->cascadeOnDelete();
            $table->string('jenis_sertifikat', 32)->nullable(); // null = berlaku untuk semua jenis
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();

            $table->index(['kegiatan_id', 'jenis_sertifikat', 'urutan']);
        });

        // 6. Pengaturan Verifikasi (sub-menu super_admin)
        Schema::create('pengaturan_verifikasi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('tampilkan_asal_institusi')->default(true);
            $table->boolean('tampilkan_jenis_predikat')->default(true);
            $table->boolean('tampilkan_nim_samar')->default(true);
            $table->boolean('tampilkan_penyelenggara')->default(true);
            $table->boolean('tampilkan_keterangan')->default(true);
            $table->boolean('tampilkan_tabel_penandatangan')->default(true);
            $table->text('teks_pengantar')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('pengaturan_verifikasi')->insert([
            'tampilkan_asal_institusi' => true,
            'tampilkan_jenis_predikat' => true,
            'tampilkan_nim_samar' => true,
            'tampilkan_penyelenggara' => true,
            'tampilkan_keterangan' => true,
            'tampilkan_tabel_penandatangan' => true,
            'teks_pengantar' => 'Halaman ini memvalidasi keaslian sertifikat resmi yang diterbitkan oleh sistem SEMADIKSI Universitas Nahdlatul Ulama Surabaya.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_verifikasi');
        Schema::dropIfExists('kegiatan_penandatangan');
        Schema::dropIfExists('penandatangan');
        Schema::dropIfExists('sertifikat_riwayat_perubahan');

        Schema::table('sertifikat', function (Blueprint $table) {
            $table->dropUnique('sertifikat_kegiatan_penerima_jenis_unique');
            $table->dropIndex(['jenis']);
            $table->dropIndex(['status']);
            $table->dropForeign(['registration_id']);
            $table->dropForeign(['dicabut_oleh']);
            $table->dropColumn([
                'registration_id',
                'penerima_key',
                'jenis',
                'predikat',
                'asal_institusi',
                'nim_penerima',
                'keterangan_publik',
                'status',
                'alasan_pencabutan',
                'dicabut_pada',
                'dicabut_oleh',
                'penandatangan_snapshot',
                'nomor_urut',
            ]);
        });
    }
};
