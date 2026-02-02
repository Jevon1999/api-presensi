<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use App\Models\User;
use App\Models\Office;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@test.com')->first();
        $officeJakarta = Office::where('code', 'HQ001')->first();
        $officeBandung = Office::where('code', 'BDG001')->first();
        $officeSurabaya = Office::where('code', 'SBY001')->first();

        // Member Jakarta
        Member::create([
            'no_hp' => '+6281234567890',
            'office_id' => $officeJakarta->id,
            'nama_lengkap' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMK Negeri 1 Jakarta',
            'tanggal_mulai_magang' => '2026-01-01',
            'tanggal_selesai_magang' => '2026-06-30',
            'status_aktif' => true,
            'created_by' => $admin->id
        ]);

        Member::create([
            'no_hp' => '+6281234567891',
            'office_id' => $officeJakarta->id,
            'nama_lengkap' => 'Siti Rahayu',
            'jenis_kelamin' => 'P',
            'asal_sekolah' => 'SMK Negeri 2 Jakarta',
            'tanggal_mulai_magang' => '2026-01-01',
            'tanggal_selesai_magang' => '2026-06-30',
            'status_aktif' => true,
            'created_by' => $admin->id
        ]);

        // Member Bandung
        Member::create([
            'no_hp' => '+6282234567890',
            'office_id' => $officeBandung->id,
            'nama_lengkap' => 'Andi Wijaya',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMK Negeri 1 Bandung',
            'tanggal_mulai_magang' => '2026-01-15',
            'tanggal_selesai_magang' => '2026-07-15',
            'status_aktif' => true,
            'created_by' => $admin->id
        ]);

        Member::create([
            'no_hp' => '+6282234567891',
            'office_id' => $officeBandung->id,
            'nama_lengkap' => 'Dewi Lestari',
            'jenis_kelamin' => 'P',
            'asal_sekolah' => 'SMK Negeri 2 Bandung',
            'tanggal_mulai_magang' => '2026-01-15',
            'tanggal_selesai_magang' => '2026-07-15',
            'status_aktif' => true,
            'created_by' => $admin->id
        ]);

        // Member Surabaya
        Member::create([
            'no_hp' => '+6283134567890',
            'office_id' => $officeSurabaya->id,
            'nama_lengkap' => 'Rudi Hartono',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMK Negeri 1 Surabaya',
            'tanggal_mulai_magang' => '2026-02-01',
            'tanggal_selesai_magang' => '2026-08-01',
            'status_aktif' => true,
            'created_by' => $admin->id
        ]);

        // Member tidak aktif (untuk testing)
        Member::create([
            'no_hp' => '+6281999999999',
            'office_id' => $officeJakarta->id,
            'nama_lengkap' => 'Member Nonaktif',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMK Test',
            'tanggal_mulai_magang' => '2025-01-01',
            'tanggal_selesai_magang' => '2025-12-31',
            'status_aktif' => false,
            'created_by' => $admin->id
        ]);
    }
}