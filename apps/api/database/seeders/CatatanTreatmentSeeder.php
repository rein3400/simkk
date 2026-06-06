<?php

namespace Database\Seeders;

use App\Models\CatatanTreatment;
use Illuminate\Database\Seeder;

class CatatanTreatmentSeeder extends Seeder
{
    public function run(): void
    {
        $notes = [
            ['pasien_id' => 1, 'tanggal' => '18 Mei', 'terapis' => 'Sinta', 'judul' => 'Acne Calm Facial',  'catatan' => 'Kemerahan turun, lanjut barrier repair.'],
            ['pasien_id' => 1, 'tanggal' => '25 Mei', 'terapis' => 'Rani',  'judul' => 'Bright Peel Mild',  'catatan' => 'Patch test aman, foto after tersimpan.'],
            ['pasien_id' => 2, 'tanggal' => '24 Mei', 'terapis' => 'Sinta', 'judul' => 'Glow Infusion',     'catatan' => 'Pigment spot dipantau 14 hari.'],
        ];

        foreach ($notes as $n) {
            CatatanTreatment::create($n);
        }
    }
}
