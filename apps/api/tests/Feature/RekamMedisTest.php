<?php

namespace Tests\Feature;

use App\Models\Pasien;
use App\Models\FotoKlinis;
use App\Models\Terapis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        $response = $this->actingAs($userA)
            ->postJson("/api/patients/{$ownPasien->id}/photos", [
                'label'    => 'Before',
                'filename' => 'before.png',
                'content'  => self::VALID_PNG_BASE64,
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'label', 'date', 'objectRef', 'url']);

        $this->assertStringContainsString('expires=', $response->json('url'));
        $this->assertStringContainsString('signature=', $response->json('url'));
    }

    public function test_signed_photo_url_streams_without_bearer_header(): void
    {
        Storage::fake('public');

        $pasien = Pasien::create([
            'nama_pasien'    => 'Pasien Foto',
            'usia'           => 30,
            'alamat'         => 'Jl. Foto',
            'nomor_telp'     => '0814',
            'rekam_medis_id' => 'RM-FOTO-001',
        ]);

        $objectRef = 'clinical/RM-FOTO-001/browser-safe.png';
        Storage::disk('public')->put($objectRef, base64_decode(substr(self::VALID_PNG_BASE64, strpos(self::VALID_PNG_BASE64, ',') + 1)));

        $photo = FotoKlinis::create([
            'pasien_id'  => $pasien->id,
            'label'      => 'Before',
            'tanggal'    => '08 Jun',
            'object_ref' => $objectRef,
        ]);

        $url = URL::temporarySignedRoute('photos.raw', now()->addMinutes(5), ['photo' => $photo->id]);
        $parts = parse_url($url);
        $uri = ($parts['path'] ?? '') . '?' . ($parts['query'] ?? '');

        $this->get($uri)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_missing_photo_object_streams_visible_placeholder(): void
    {
        Storage::fake('public');

        $pasien = Pasien::create([
            'nama_pasien'    => 'Pasien Missing Foto',
            'usia'           => 31,
            'alamat'         => 'Jl. Missing',
            'nomor_telp'     => '0815',
            'rekam_medis_id' => 'RM-FOTO-404',
        ]);

        $photo = FotoKlinis::create([
            'pasien_id'  => $pasien->id,
            'label'      => 'After',
            'tanggal'    => '08 Jun',
            'object_ref' => 'local://clinical/RM-FOTO-404/missing.png',
        ]);

        $url = URL::temporarySignedRoute('photos.raw', now()->addMinutes(5), ['photo' => $photo->id]);
        $parts = parse_url($url);
        $uri = ($parts['path'] ?? '') . '?' . ($parts['query'] ?? '');

        $this->get($uri)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertSee('Foto tidak tersedia', false);
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
