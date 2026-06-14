<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'kasir',   'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Nadia Putri',   'level' => 'Kasir',   'shift' => 'Pagi'],
            ['username' => 'terapis', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'dr. Melati',    'level' => 'Terapis', 'shift' => 'Treatment A'],
            ['username' => 'gudang',  'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Raka Pramana',  'level' => 'Gudang',  'shift' => 'Gudang'],
            // Placeholder name — rename via Admin → User to match the real
            // manager on duty. See apps/api/app/Http/Controllers/Api/UserAdminController.php
            // (`update`) for the rename endpoint.
            ['username' => 'manajer', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Manajer Klinik', 'level' => 'Manajer', 'shift' => 'Audit'],
        ];

        foreach ($users as $u) {
            User::create($u);
        }
    }
}
