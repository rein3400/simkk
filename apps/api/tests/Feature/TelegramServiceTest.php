<?php

namespace Tests\Feature;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_send_text_calls_telegram_api_with_correct_payload(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        config(['sim-kk.telegram.bot_token' => 'test-bot-token-123']);

        $svc = new TelegramService();
        $result = $svc->sendText('123456789', 'Halo test');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $url = $request->url();
            $body = $request->data();
            return str_contains($url, '/bottest-bot-token-123/sendMessage')
                && $body['chat_id'] === '123456789'
                && $body['text'] === 'Halo test'
                && $body['parse_mode'] === 'Markdown';
        });
    }

    public function test_send_text_returns_false_when_token_missing(): void
    {
        config(['sim-kk.telegram.bot_token' => null]);

        $svc = new TelegramService();
        $result = $svc->sendText('123', 'test');

        $this->assertFalse($result);
    }

    public function test_send_text_returns_false_on_http_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request'], 400),
        ]);

        config(['sim-kk.telegram.bot_token' => 'test-token']);

        $svc = new TelegramService();
        $result = $svc->sendText('123', 'test');

        $this->assertFalse($result);
    }

    public function test_treatment_reminder_includes_patient_name_and_when(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        config(['sim-kk.telegram.bot_token' => 'test-token']);

        $svc = new TelegramService();
        $result = $svc->sendTreatmentReminder('123', 'Rina Marlina', '15 Juni 2026 jam 14:00');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($body['text'], 'Rina Marlina')
                && str_contains($body['text'], '15 Juni 2026 jam 14:00');
        });
    }

    public function test_aftercare_tips_includes_treatment_name(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        config(['sim-kk.telegram.bot_token' => 'test-token']);

        $svc = new TelegramService();
        $result = $svc->sendAftercareTips('123', 'Rina', 'Chemical Peeling');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($body['text'], 'Rina')
                && str_contains($body['text'], 'Chemical Peeling')
                && str_contains($body['text'], 'sunscreen');
        });
    }
}
