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
            ['username' => 'manajer', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Mira Santoso',  'level' => 'Manajer', 'shift' => 'Audit'],
        ];

        foreach ($users as $u) {
            User::create($u);
        }
    }
}
