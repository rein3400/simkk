<?php

namespace Tests\Feature;

use App\Models\DailyCashFloat;
use App\Models\DailyClosing;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use App\Services\DailyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedUsers(): array
    {
        return [
            'manajer' => User::create([
                'username'     => 'mgr',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Hendra Wijaya',
                'level'        => 'Manajer',
                'shift'        => 'Pagi',
            ]),
            'kasir' => User::create([
                'username'     => 'ksr',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Diani Arthantri',
                'level'        => 'Kasir',
                'shift'        => 'Pagi',
            ]),
            'terapis' => User::create([
                'username'     => 'trp',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Sinta',
                'level'        => 'Terapis',
                'shift'        => 'Pagi',
            ]),
        ];
    }

    private function seedTransaction(string $tanggal, int $total, string $metode = 'Tunai'): void
    {
        $pasien  = Pasien::create(['nama_pasien' => 'P', 'usia' => 25, 'alamat' => 'Jl', 'nomor_telp' => '081', 'rekam_medis_id' => 'RM-' . uniqid('', true)]);
        $terapis = Terapis::create(['nama' => 'S', 'spesialisasi' => 'A', 'status' => 'Tersedia']);
        $row = new \App\Models\Transaksi();
        $row->timestamps = false;
        $row->forceFill([
            'id_transaksi' => 'TRX-' . substr(md5($tanggal . $total . $metode . uniqid('', true)), 0, 8),
            'pasien_id'    => $pasien->id,
            'terapis_id'   => $terapis->id,
            'status'       => 'Lunas',
            'subtotal'     => $total,
            'diskon'       => 0,
            'metode_bayar' => $metode,
            'total'        => $total,
            'komisi_total' => 0,
            'waktu'        => '10:00',
            'created_at'   => Carbon::parse($tanggal . ' 10:00:00'),
            'updated_at'   => Carbon::parse($tanggal . ' 10:00:00'),
        ])->save();
    }

    public function test_export_returns_pdf_with_role_manajer(): void
    {
        $u = $this->seedUsers();
        $this->seedTransaction('2026-05-26', 250000, 'Tunai');

        $res = $this->actingAs($u['manajer'])
            ->get('/api/daily-reports/2026-05-26/export');

        $res->assertOk();
        $this->assertEquals('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_export_rejects_kasir_role(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['kasir'])
            ->get('/api/daily-reports/2026-05-26/export')
            ->assertStatus(403);
    }

    public function test_export_validates_date_format(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['manajer'])
            ->get('/api/daily-reports/not-a-date/export')
            ->assertStatus(422);
    }

    public function test_submit_creates_daily_closing_in_submitted_state(): void
    {
        $u = $this->seedUsers();
        $this->seedTransaction('2026-05-26', 100000, 'Tunai');
        DailyCashFloat::create([
            'user_id'    => $u['kasir']->id,
            'tanggal'    => '2026-05-26',
            'modal_awal' => 2000000,
        ]);

        $res = $this->actingAs($u['kasir'])
            ->postJson('/api/daily-reports/2026-05-26/submit');

        $res->assertOk()->assertJsonStructure([
            'message',
            'closing' => ['id', 'tanggal', 'status', 'user_kasir_id', 'total_penjualan', 'total_tunai', 'pnl'],
        ]);
        $this->assertEquals('submitted', $res->json('closing.status'));
        $this->assertEquals(100000, $res->json('closing.total_penjualan'));
        $this->assertEquals(100000, $res->json('closing.total_tunai'));
    }

    public function test_submit_allowed_for_manajer_as_well(): void
    {
        $u = $this->seedUsers();
        $this->seedTransaction('2026-05-26', 50000, 'Tunai');

        $this->actingAs($u['manajer'])
            ->postJson('/api/daily-reports/2026-05-26/submit')
            ->assertOk()
            ->assertJsonPath('closing.status', 'submitted');
    }

    public function test_submit_rejects_terapis_role(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['terapis'])
            ->postJson('/api/daily-reports/2026-05-26/submit')
            ->assertStatus(403);
    }

    public function test_approve_transitions_to_approved_state(): void
    {
        $u = $this->seedUsers();
        $this->seedTransaction('2026-05-26', 75000, 'Tunai');

        $this->actingAs($u['kasir'])
            ->postJson('/api/daily-reports/2026-05-26/submit')
            ->assertOk();

        $closing = DailyClosing::whereDate('tanggal', '2026-05-26')->firstOrFail();

        $res = $this->actingAs($u['manajer'])
            ->postJson("/api/daily-reports/closings/{$closing->id}/approve");

        $res->assertOk()
            ->assertJsonPath('closing.status', 'approved')
            ->assertJsonPath('closing.user_manajer_id', $u['manajer']->id);
    }

    public function test_approve_rejects_kasir_role(): void
    {
        $u = $this->seedUsers();
        $this->seedTransaction('2026-05-26', 75000, 'Tunai');

        $this->actingAs($u['kasir'])
            ->postJson('/api/daily-reports/2026-05-26/submit')
            ->assertOk();

        $closing = DailyClosing::whereDate('tanggal', '2026-05-26')->firstOrFail();

        $this->actingAs($u['kasir'])
            ->postJson("/api/daily-reports/closings/{$closing->id}/approve")
            ->assertStatus(403);
    }

    public function test_approve_rejects_draft_state(): void
    {
        $u = $this->seedUsers();
        $closing = DailyClosing::create([
            'tanggal'        => '2026-05-26',
            'user_kasir_id'  => $u['kasir']->id,
            'status'         => DailyClosing::STATUS_DRAFT,
        ]);

        $this->actingAs($u['manajer'])
            ->postJson("/api/daily-reports/closings/{$closing->id}/approve")
            ->assertStatus(422);
    }

    public function test_service_build_payload_aggregates_sales_by_category(): void
    {
        $this->seedTransaction('2026-05-26', 500000, 'Tunai');
        $this->seedTransaction('2026-05-26', 200000, 'EDC BCA');
        $svc = DailyReportService::fromConfig();
        $payload = $svc->buildPayload('2026-05-26');

        $this->assertEquals(700000, $payload['totalSales']);
        $this->assertEquals(200000, $payload['totalCard']);
        $this->assertEquals(500000, $payload['totalSales'] - $payload['totalCard']);
        $this->assertEquals(200000, $payload['setoranBank']);
        $this->assertNotEmpty($payload['signatureManajerUrl']);
        $this->assertNotEmpty($payload['signatureKasirUrl']);
    }
}
