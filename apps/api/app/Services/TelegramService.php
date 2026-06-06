<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $baseUrl = 'https://api.telegram.org/bot';

    public function sendText(string $chatId, string $message): bool
    {
        $token = config('sim-kk.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram not configured — skipping send.');
            return false;
        }

        $url = $this->baseUrl . $token . '/sendMessage';

        $response = Http::post($url, [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'Markdown',
        ]);

        if (!$response->successful()) {
            Log::error('Telegram send failed', [
                'chat_id' => $chatId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    public function sendTreatmentReminder(string $chatId, string $patientName, string $when): bool
    {
        $msg = "Halo *{$patientName}* 👋\n\n"
             . "Ini pengingat jadwal treatment Anda:\n"
             . "📅 *{$when}*\n\n"
             . "Sampai jumpa di klinik. Jika perlu ubah jadwal, balas pesan ini.\n\n"
             . "— SIM-KK";

        return $this->sendText($chatId, $msg);
    }

    public function sendAftercareTips(string $chatId, string $patientName, string $treatment): bool
    {
        $msg = "Halo *{$patientName}* 🌿\n\n"
             . "Setelah *{$treatment}*, berikut tips aftercare:\n"
             . "• Hindari paparan matahari langsung 24 jam pertama\n"
             . "• Gunakan sunscreen SPF 50+ setiap keluar rumah\n"
             . "• Hindari produk exfoliating 3 hari ke depan\n"
             . "• Minum air putih 8 gelas/hari\n\n"
             . "Jika ada keluhan, balas pesan ini atau hubungi klinik.\n\n"
             . "— SIM-KK";

        return $this->sendText($chatId, $msg);
    }
}
