<?php

namespace Database\Seeders;

use App\Models\Pasien;
use Illuminate\Database\Seeder;

class PasienSeeder extends Seeder
{
    public function run(): void
    {
        // F-007 fix: assign 2-3 patients to 'dr. Melati' so the seeded 'terapis' login
        // can actually exercise the addTreatment flow. Other patients get NULL assignment
        // (Manajer can still write; Terapis would get 403 — by design).
        $pasien = [
            ['nama_pasien' => 'Alya Maharani', 'usia' => 29, 'alamat' => 'Jl. Pahlawan No. 12, Samarinda',     'nomor_telp' => '0812-4400-1188', 'rekam_medis_id' => 'RM-2026-0018', 'keluhan' => 'Bekas jerawat dan tekstur kulit',       'last_visit' => '25 Mei 2026', 'risk_note' => 'Sensitif terhadap retinol tinggi',     'assigned_terapis_id' => 5],
            ['nama_pasien' => 'Dewi Lestari',  'usia' => 34, 'alamat' => 'Jl. Sudirman No. 88, Samarinda',     'nomor_telp' => '0821-8890-7712', 'rekam_medis_id' => 'RM-2026-0021', 'keluhan' => 'Melasma ringan',                          'last_visit' => '24 Mei 2026', 'risk_note' => 'Wajib sunscreen pasca tindakan',       'assigned_terapis_id' => 5],
            ['nama_pasien' => 'Yuni Kartika',  'usia' => 26, 'alamat' => 'Jl. Mulawarman No. 5, Samarinda',    'nomor_telp' => '0813-5500-9821', 'rekam_medis_id' => 'RM-2026-0025', 'keluhan' => 'Komedo area T-zone',                      'last_visit' => '22 Mei 2026', 'risk_note' => 'Tidak ada alergi aktif',                'assigned_terapis_id' => null],
            ['nama_pasien' => 'Bella Anggraini','usia' => 31,'alamat' => 'Jl. Gatot Subroto No. 21, Samarinda', 'nomor_telp' => '0852-7710-4410', 'rekam_medis_id' => 'RM-2026-0029', 'keluhan' => 'Kulit kusam',                              'last_visit' => '20 Mei 2026', 'risk_note' => 'Hindari scrub 3 hari',                   'assigned_terapis_id' => null],
            ['nama_pasien' => 'Sarah Amalia',  'usia' => 37, 'alamat' => 'Jl. Bhayangkara No. 9, Samarinda',   'nomor_telp' => '0811-6200-3498', 'rekam_medis_id' => 'RM-2026-0030', 'keluhan' => 'Fine lines',                              'last_visit' => '19 Mei 2026', 'risk_note' => 'Konsultasi dokter sebelum needle',       'assigned_terapis_id' => null],
            ['nama_pasien' => 'Citra Ananda',  'usia' => 23, 'alamat' => 'Jl. Diponegoro No. 14, Samarinda',   'nomor_telp' => '0822-1200-6811', 'rekam_medis_id' => 'RM-2026-0034', 'keluhan' => 'Hydration maintenance',                   'last_visit' => '18 Mei 2026', 'risk_note' => 'Aman untuk hydrating facial',            'assigned_terapis_id' => null],
        ];

        foreach ($pasien as $p) {
            Pasien::create($p);
        }
    }
}
