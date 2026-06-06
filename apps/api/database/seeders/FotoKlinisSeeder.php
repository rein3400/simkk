<?php

namespace Database\Seeders;

use App\Models\FotoKlinis;
use Illuminate\Database\Seeder;

class FotoKlinisSeeder extends Seeder
{
    public function run(): void
    {
        $photos = [
            ['pasien_id' => 1, 'label' => 'Before', 'tanggal' => '18 Mei', 'object_ref' => 'local://clinical/RM-2026-0018/before-1805.jpg'],
            ['pasien_id' => 1, 'label' => 'After',  'tanggal' => '25 Mei', 'object_ref' => 'local://clinical/RM-2026-0018/after-2505.jpg'],
            ['pasien_id' => 2, 'label' => 'Before', 'tanggal' => '24 Mei', 'object_ref' => 'local://clinical/RM-2026-0021/before-2405.jpg'],
            ['pasien_id' => 2, 'label' => 'After',  'tanggal' => '24 Mei', 'object_ref' => 'local://clinical/RM-2026-0021/after-2405.jpg'],
        ];

        foreach ($photos as $p) {
            FotoKlinis::create($p);
        }
    }
}
