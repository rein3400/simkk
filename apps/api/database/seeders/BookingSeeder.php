<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Per revisi R1 — populate dashboard "Jadwal booking" widget.
     * Creates 5 sample bookings across the next 5 days so the dashboard
     * has something to render.
     */
    public function run(): void
    {
        $terapis = Terapis::take(2)->get();
        $pasien = Pasien::take(3)->get();
        $layanan = Layanan::first();
        $manajer = User::where('level', 'Manajer')->first();

        if ($terapis->isEmpty() || $pasien->isEmpty() || !$manajer) {
            return;
        }

        $samples = [
            ['days' => 0, 'hour' => 10, 'duration' => 60, 'terapis_idx' => 0, 'pasien_idx' => 0, 'status' => 'confirmed', 'source' => 'walk_in'],
            ['days' => 0, 'hour' => 14, 'duration' => 90, 'terapis_idx' => 1, 'pasien_idx' => 1, 'status' => 'booked', 'source' => 'phone'],
            ['days' => 1, 'hour' => 11, 'duration' => 60, 'terapis_idx' => 0, 'pasien_idx' => 2, 'status' => 'booked', 'source' => 'web'],
            ['days' => 2, 'hour' => 13, 'duration' => 60, 'terapis_idx' => 1, 'pasien_idx' => 0, 'status' => 'booked', 'source' => 'walk_in'],
            ['days' => 3, 'hour' => 15, 'duration' => 30, 'terapis_idx' => 0, 'pasien_idx' => 1, 'status' => 'booked', 'source' => 'phone'],
        ];

        foreach ($samples as $s) {
            $scheduledAt = CarbonImmutable::now()
                ->addDays($s['days'])
                ->setHour($s['hour'])
                ->setMinute(0)
                ->setSecond(0);

            Booking::firstOrCreate(
                [
                    'terapis_id'   => $terapis[$s['terapis_idx']]->id,
                    'scheduled_at' => $scheduledAt,
                ],
                [
                    'pasien_id'    => $pasien[$s['pasien_idx']]->id,
                    'layanan_id'   => $layanan?->id,
                    'duration_min' => $s['duration'],
                    'status'       => $s['status'],
                    'source'       => $s['source'],
                    'created_by'   => $manajer->id,
                ]
            );
        }
    }
}
