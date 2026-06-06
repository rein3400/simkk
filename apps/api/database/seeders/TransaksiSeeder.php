<?php

namespace Database\Seeders;

use App\Models\BukuKas;
use App\Models\Transaksi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TransaksiSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['id' => 'TRX-2505-031', 'pasien_id' => 1, 'terapis_id' => 2, 'status' => 'Lunas',    'total' => 575000, 'komisi_total' => 57100, 'time' => '10:18'],
            ['id' => 'TRX-2505-032', 'pasien_id' => 2, 'terapis_id' => 1, 'status' => 'Menunggu', 'total' => 520000, 'komisi_total' => 83200, 'time' => '11:05'],
            ['id' => 'TRX-2505-033', 'pasien_id' => 3, 'terapis_id' => null, 'status' => 'Draft', 'total' => 0,      'komisi_total' => 0,     'time' => '11:20'],
        ];

        foreach ($rows as $r) {
            $t = Transaksi::create([
                'id_transaksi' => $r['id'],
                'pasien_id'    => $r['pasien_id'],
                'terapis_id'   => $r['terapis_id'],
                'status'       => $r['status'],
                'subtotal'     => $r['total'],
                'diskon'       => 0,
                'metode_bayar' => 'Tunai',
                'total'        => $r['total'],
                'komisi_total' => $r['komisi_total'],
                'waktu'        => $r['time'],
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
            if ($r['status'] === 'Lunas') {
                BukuKas::create([
                    'id_transaksi' => $t->id_transaksi,
                    'tipe'         => 'Debit',
                    'jumlah'       => $r['total'],
                    'deskripsi'    => "Pembayaran {$t->id_transaksi}",
                    'created_at'   => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                ]);
            }
        }
    }
}
