<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ubah default status dari 'approved' ke 'pending' agar pengajuan user
     * tidak langsung disetujui tanpa persetujuan admin.
     */
    public function up(): void
    {
        // Ubah default kolom status dari 'approved' ke 'pending'
        DB::statement("ALTER TABLE members MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE members MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
    }
};
