<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Office;
use App\Models\OfficeLocation;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        // Office 1: Kantor Pusat Jakarta
        $office1 = Office::create([
            'code' => 'HQ001',
            'name' => 'Kantor Pusat Jakarta'
        ]);

        OfficeLocation::create([
            'office_id' => $office1->id,
            'alamat' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'latitude' => -6.200000,  // Monas area
            'longitude' => 106.816666,
            'radius_meters' => 100,
            'is_active' => true
        ]);

        // Office 2: Cabang Bandung
        $office2 = Office::create([
            'code' => 'BDG001',
            'name' => 'Kantor Cabang Bandung'
        ]);

        OfficeLocation::create([
            'office_id' => $office2->id,
            'alamat' => 'Jl. Asia Afrika No. 45, Bandung',
            'latitude' => -6.921478,  // Bandung city center
            'longitude' => 107.607140,
            'radius_meters' => 150,
            'is_active' => true
        ]);

        // Office 3: Cabang Surabaya
        $office3 = Office::create([
            'code' => 'SBY001',
            'name' => 'Kantor Cabang Surabaya'
        ]);

        OfficeLocation::create([
            'office_id' => $office3->id,
            'alamat' => 'Jl. Tunjungan No. 88, Surabaya',
            'latitude' => -7.250445,  // Surabaya center
            'longitude' => 112.768845,
            'radius_meters' => 100,
            'is_active' => true
        ]);
    }
}