<?php

namespace Tests\Feature;

use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_webhook_returns_ok(): void
    {
        $res = $this->getJson('/api/telegram/webhook');
        $res->assertOk()->assertJson(['ok' => true, 'webhook' => 'sim-kk-telegram']);
    }

    public function test_start_command_sends_welcome(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => [
                'chat' => ['id' => 111111111],
                'text' => '/start',
            ],
        ]);

        $res->assertOk()->assertJson(['handled' => 'start']);
        Http::assertSent(fn ($r) => str_contains($r->data()['text'], 'Selamat datang'));
    }

    public function test_start_link_with_valid_rm_id_links_patient(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $pasien = Pasien::create([
            'nama_pasien' => 'Rina Marlina',
            'usia' => 24,
            'alamat' => 'Jl. Sudirman',
            'nomor_telp' => '081234567890',
            'rekam_medis_id' => 'RM-0001',
        ]);

        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 2,
            'message' => [
                'chat' => ['id' => 222222222],
                'text' => '/start LINK RM-0001',
            ],
        ]);

        $res->assertOk()->assertJson(['handled' => 'start-link']);
        $this->assertSame('222222222', $pasien->fresh()->telegram_chat_id);
    }

    public function test_start_link_with_invalid_rm_id_reports_error(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 3,
            'message' => [
                'chat' => ['id' => 333333333],
                'text' => '/start LINK RM-9999',
            ],
        ]);

        $res->assertOk()->assertJson(['handled' => 'start-link']);
        Http::assertSent(fn ($r) => str_contains($r->data()['text'], 'tidak ditemukan'));
    }

    public function test_unlink_command_clears_chat_id(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $pasien = Pasien::create([
            'nama_pasien' => 'Rina',
            'usia' => 24,
            'alamat' => 'x',
            'nomor_telp' => '081234567890',
            'rekam_medis_id' => 'RM-0001',
            'telegram_chat_id' => '444444444',
        ]);

        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 4,
            'message' => ['chat' => ['id' => 444444444], 'text' => '/unlink'],
        ]);

        $res->assertOk()->assertJson(['handled' => 'unlink']);
        $this->assertNull($pasien->fresh()->telegram_chat_id);
    }

    public function test_help_command_returns_help(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 5,
            'message' => ['chat' => ['id' => 555555555], 'text' => '/help'],
        ]);
        $res->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->data()['text'], 'Perintah tersedia'));
    }

    public function test_ping_command(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 6,
            'message' => ['chat' => ['id' => 666666666], 'text' => '/ping'],
        ]);
        $res->assertOk()->assertJson(['handled' => 'ping']);
    }

    public function test_shared_contact_links_patient_by_phone(): void
    {
        config(['sim-kk.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $pasien = Pasien::create([
            'nama_pasien' => 'Rina',
            'usia' => 24,
            'alamat' => 'x',
            'nomor_telp' => '081234567890',
            'rekam_medis_id' => 'RM-0001',
        ]);

        $res = $this->postJson('/api/telegram/webhook', [
            'update_id' => 7,
            'message' => [
                'chat' => ['id' => 777777777],
                'contact' => [
                    'phone_number' => '+6281234567890',
                    'first_name' => 'Rina',
                ],
            ],
        ]);

        $res->assertOk()->assertJson(['handled' => 'contact']);
        $this->assertSame('777777777', $pasien->fresh()->telegram_chat_id);
    }
}
