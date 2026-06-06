<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            // F-007 fix: Terapis must seed before Pasien so assigned_terapis_id FKs resolve.
            TerapisSeeder::class,
            PasienSeeder::class,
            ProdukSeeder::class,
            LayananSeeder::class,
            BatchStokSeeder::class,
            TransaksiSeeder::class,
            CatatanTreatmentSeeder::class,
            FotoKlinisSeeder::class,
        ]);
    }
}
