<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom tipe laporan ke tabel progresses.
     * Tipe: hadir | sakit | izin
     *   - sakit: description wajib (alasan sakit)
     *   - izin: description default "Pulang"
     *   - hadir: description = laporan kegiatan kerja
     */
    public function up(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            $table->enum('tipe', ['hadir', 'sakit', 'izin'])
                ->default('hadir')
                ->after('tanggal')
                ->comment('Tipe laporan: hadir=kerja, sakit=tidak masuk sakit, izin=pulang/tidak hadir');
        });
    }

    public function down(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
