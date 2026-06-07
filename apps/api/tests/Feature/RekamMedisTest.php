<?php

namespace Tests\Feature;

use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RekamMedisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1x1 transparent PNG, base64-encoded. Valid for getimagesizefromstring().
     */
    private const VALID_PNG_BASE64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function makeUser(string $level, string $namaLengkap): User
    {
        return User::create([
            'username'     => strtolower(str_replace(' ', '', $namaLengkap)),
            'password'     => bcrypt('simkk-2026'),
            'nama_lengkap' => $namaLengkap,
            'level'        => $level,
            'shift'        => 'Treatment A',
        ]);
    }

    public function test_terapis_can_only_attach_photos_to_assigned_pasien(): void
    {
        Storage::fake('public');

        $terapisA = Terapis::create(['nama' => 'Sinta Lestari', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        $terapisB = Terapis::create(['nama' => 'Dewi Anggraini', 'spesialisasi' => 'Brightening', 'status' => 'Tersedia']);

        $userA = $this->makeUser('Terapis', 'Sinta Lestari');

        $pasienAssignedToB = Pasien::create([
            'nama_pasien'         => 'Pasien B',
            'usia'                => 28,
            'alamat'              => 'Jl. B',
            'nomor_telp'          => '0812',
            'rekam_medis_id'      => 'RM-B-001',
            'assigned_terapis_id' => $terapisB->id,
        ]);

        $this->actingAs($userA)
            ->postJson("/api/patients/{$pasienAssignedToB->id}/photos", [
                'label'    => 'Before',
                'filename' => 'before.png',
                'content'  => self::VALID_PNG_BASE64,
            ])
            ->assertStatus(403)
            ->assertJson(['message' => 'Pasien ini bukan pasien yang Anda tangani.']);
    }

    public function test_terapis_can_attach_photo_to_own_pasien(): void
    {
        Storage::fake('public');

        $terapisA = Terapis::create(['nama' => 'Sinta Lestari', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        $userA = $this->makeUser('Terapis', 'Sinta Lestari');

        $ownPasien = Pasien::create([
            'nama_pasien'         => 'Pasien A',
            'usia'                => 30,
            'alamat'              => 'Jl. A',
            'nomor_telp'          => '0813',
            'rekam_medis_id'      => 'RM-A-001',
            'assigned_terapis_id' => $terapisA->id,
        ]);

        $this->actingAs($userA)
            ->postJson("/api/patients/{$ownPasien->id}/photos", [
                'label'    => 'Before',
                'filename' => 'before.png',
                'content'  => self::VALID_PNG_BASE64,
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'label', 'date', 'objectRef']);
    }

    public function test_manajer_can_attach_photo_to_any_pasien(): void
    {
        Storage::fake('public');

        $terapisB = Terapis::create(['nama' => 'Dewi Anggraini', 'spesialisasi' => 'Brightening', 'status' => 'Tersedia']);
        $manajer  = $this->makeUser('Manajer', 'Pak Budi');

        $pasienAssignedToB = Pasien::create([
            'nama_pasien'         => 'Pasien B',
            'usia'                => 28,
            'alamat'              => 'Jl. B',
            'nomor_telp'          => '0812',
            'rekam_medis_id'      => 'RM-B-001',
            'assigned_terapis_id' => $terapisB->id,
        ]);

        $this->actingAs($manajer)
            ->postJson("/api/patients/{$pasienAssignedToB->id}/photos", [
                'label'    => 'After',
                'filename' => 'after.png',
                'content'  => self::VALID_PNG_BASE64,
            ])
            ->assertCreated()
            ->assertJson(['label' => 'After']);
    }
}
