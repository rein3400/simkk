<?php

namespace Database\Seeders;

use App\Models\Terapis;
use Illuminate\Database\Seeder;

class TerapisSeeder extends Seeder
{
    public function run(): void
    {
        $terapis = [
            ['nama' => 'Sinta Ayu',     'spesialisasi' => 'Acne care',     'status' => 'Tersedia',  'gaji_pokok' => 2500000],
            ['nama' => 'Rani Wulandari', 'spesialisasi' => 'Brightening',   'status' => 'Tersedia',  'gaji_pokok' => 2500000],
            ['nama' => 'Maya Cahyani',   'spesialisasi' => 'Recovery',      'status' => 'Treatment', 'gaji_pokok' => 2500000],
            ['nama' => 'Lina Paramitha', 'spesialisasi' => 'Hydration',     'status' => 'Istirahat', 'gaji_pokok' => 2500000],
            // F-005 fix: link the seeded 'terapis' login (dr. Melati) to a Terapis row.
            ['nama' => 'dr. Melati',     'spesialisasi' => 'Dermatologi',   'status' => 'Tersedia',  'gaji_pokok' => 2500000],
        ];

        foreach ($terapis as $t) {
            Terapis::create($t);
        }
    }
}
