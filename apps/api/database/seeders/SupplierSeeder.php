<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Per revisi R3 — registered suppliers, replaces free-text input.
     * Names match the strings already present in BatchStokSeeder so
     * legacy rows align with the new master list.
     */
    public function run(): void
    {
        $suppliers = [
            ['nama' => 'PT Dermalab', 'kontak' => 'Budi Santoso', 'telepon' => '0812-1111-2222', 'email' => 'order@dermalab.co.id'],
            ['nama' => 'PT Beauty Indo', 'kontak' => 'Siti Rahayu', 'telepon' => '0812-3333-4444', 'email' => 'sales@beautyindo.co.id'],
            ['nama' => 'CV Kosmetik Jaya', 'kontak' => 'Andi Wijaya', 'telepon' => '0812-5555-6666', 'email' => 'cs@kosmetikjaya.co.id'],
            ['nama' => 'PT E2E', 'kontak' => 'E2E Procurement', 'telepon' => '0812-7777-8888', 'email' => 'procurement@e2e.co.id'],
            ['nama' => 'PT X', 'kontak' => 'X Distribution', 'telepon' => '0812-9999-0000', 'email' => 'hello@ptx.co.id'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['nama' => $s['nama']], $s);
        }
    }
}
