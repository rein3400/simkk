<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function reminder(Request $request, TelegramService $tg): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'        => 'required|integer|exists:pasien,id',
            'telegram_chat_id' => 'required_without:phone|string',
            'when'             => 'required|string|max:100',
        ]);

        $pasien = Pasien::findOrFail($validated['pasien_id']);

        $chatId = $validated['telegram_chat_id']
            ?? $pasien->telegram_chat_id;

        if (!$chatId) {
            return response()->json([
                'message' => 'Pasien belum memiliki telegram_chat_id. Minta pasien memulai bot Telegram klinik terlebih dahulu.',
            ], 422);
        }

        $sent = $tg->sendTreatmentReminder($chatId, $pasien->nama_pasien, $validated['when']);

        return response()->json([
            'sent'    => $sent,
            'to'      => $chatId,
            'patient' => $pasien->nama_pasien,
        ]);
    }

    public function aftercare(Request $request, TelegramService $tg): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'        => 'required|integer|exists:pasien,id',
            'telegram_chat_id' => 'required_without:phone|string',
            'treatment'        => 'required|string|max:100',
        ]);

        $pasien = Pasien::findOrFail($validated['pasien_id']);

        $chatId = $validated['telegram_chat_id']
            ?? $pasien->telegram_chat_id;

        if (!$chatId) {
            return response()->json([
                'message' => 'Pasien belum memiliki telegram_chat_id. Minta pasien memulai bot Telegram klinik terlebih dahulu.',
            ], 422);
        }

        $sent = $tg->sendAftercareTips($chatId, $pasien->nama_pasien, $validated['treatment']);

        return response()->json([
            'sent'      => $sent,
            'to'        => $chatId,
            'patient'   => $pasien->nama_pasien,
            'treatment' => $validated['treatment'],
        ]);
    }

    /**
     * Telegram webhook handler.
     *
     * Routes (public, no auth — Telegram servers call this):
     *   GET  /api/telegram/webhook  -> returns 200 OK (Telegram requires)
     *   POST /api/telegram/webhook  -> handles Update from Telegram
     *
     * Supported commands from patient:
     *   /start                 -> welcome message
     *   /start LINK <RM-id>    -> link this chat to patient record (sets telegram_chat_id)
     *   /unlink                -> remove telegram_chat_id from patient
     *   /help                  -> show available commands
     *   /ping                  -> liveness check
     *   contact (shared)       -> if phone matches a pasien, link automatically
     */
    public function webhook(Request $request, TelegramService $tg): JsonResponse
    {
        // Telegram webhook setup verification: respond 200 OK to GET
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true, 'webhook' => 'sim-kk-telegram']);
        }

        // Security: verify X-Telegram-Bot-Api-Secret-Token header.
        // Telegram sends this header on every POST if a secret_token was set via setWebhook.
        // We use hash_equals for timing-safe comparison.
        $secret = config('sim-kk.telegram.webhook_secret');
        if (empty($secret)) {
            // Operator must configure TELEGRAM_WEBHOOK_SECRET before the webhook is exposed.
            // 503 (not 401) signals "service not ready" rather than "auth failed".
            return response()->json([
                'ok'    => false,
                'error' => 'telegram webhook secret not configured',
            ], 503);
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (!is_string($provided) || !hash_equals($secret, $provided)) {
            Log::warning('Telegram webhook rejected: invalid or missing secret token', [
                'ip'         => $request->ip(),
                'has_header' => $provided !== null,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'invalid token',
            ], 401);
        }

        $update = $request->all();

        Log::info('Telegram webhook received', ['update_id' => $update['update_id'] ?? null]);

        $message = $update['message'] ?? null;
        if (!$message) {
            return response()->json(['ok' => true, 'handled' => false]);
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        $contact = $message['contact'] ?? null;

        if ($contact && !empty($contact['phone_number'])) {
            $this->linkByPhone($contact['phone_number'], $chatId);
            $tg->sendText($chatId, "✅ Kontak Anda telah terhubung. Notifikasi klinik akan dikirim ke chat ini.");
            return response()->json(['ok' => true, 'handled' => 'contact']);
        }

        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6));
            $payloadLower = strtolower($payload);
            if ($payload === '' || str_starts_with($payloadLower, 'link ')) {
                if ($payload === '') {
                    $tg->sendText($chatId, $this->welcomeMessage());
                    return response()->json(['ok' => true, 'handled' => 'start']);
                }
                // /start LINK <rm-id>
                $rmId = trim(substr($payload, 5));
                $pasien = Pasien::where('rekam_medis_id', $rmId)->first();
                if ($pasien) {
                    $pasien->telegram_chat_id = $chatId;
                    $pasien->save();
                    $tg->sendText($chatId, "✅ Halo *{$pasien->nama_pasien}*! Akun Telegram Anda terhubung ke rekam medis. Anda akan menerima reminder jadwal & aftercare treatment di sini.");
                } else {
                    $tg->sendText($chatId, "❌ Rekam medis *{$rmId}* tidak ditemukan. Hubungi resepsionis klinik untuk verifikasi data.");
                }
                return response()->json(['ok' => true, 'handled' => 'start-link']);
            }
            // Unrecognized payload
            $tg->sendText($chatId, $this->welcomeMessage());
            return response()->json(['ok' => true, 'handled' => 'start']);
        }

        if (str_starts_with($text, '/unlink')) {
            Pasien::where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);
            $tg->sendText($chatId, "🔌 Akun Telegram Anda telah diputuskan dari rekam medis. Anda tidak akan menerima notifikasi lagi.");
            return response()->json(['ok' => true, 'handled' => 'unlink']);
        }

        if (str_starts_with($text, '/help')) {
            $tg->sendText($chatId, $this->helpMessage());
            return response()->json(['ok' => true, 'handled' => 'help']);
        }

        if (str_starts_with($text, '/ping')) {
            $tg->sendText($chatId, "pong ✓");
            return response()->json(['ok' => true, 'handled' => 'ping']);
        }

        // Unrecognized — nudge to /help
        $tg->sendText($chatId, "Perintah tidak dikenali. Ketik /help untuk bantuan.");
        return response()->json(['ok' => true, 'handled' => 'unknown']);
    }

    private function linkByPhone(string $phone, string $chatId): ?Pasien
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // Normalize Indonesian phone numbers to 62xxx format (no leading 0).
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        // Build multiple normalized forms for exact matching to handle
        // different storage conventions (62xxx, 0xxx, +62xxx).
        $forms = array_unique(array_filter([
            $digits,                              // 62xxx
            '0' . substr($digits, 2),             // 0xxx (if starts with 62)
            '+' . $digits,                         // +62xxx
            substr($digits, 2),                    // local number without country code
        ]));

        // P1 #4: Exact normalized match instead of LIKE '%9digits'.
        // Avoids mis-matching when 2 patients share the same 9-digit suffix.
        $pasien = Pasien::whereIn('nomor_telp', $forms)->first();
        if ($pasien) {
            $pasien->telegram_chat_id = $chatId;
            $pasien->save();
            return $pasien;
        }
        return null;
    }

    private function welcomeMessage(): string
    {
        return "Selamat datang di *Bot SIM-KK* 🌿\n\n"
             . "Untuk menghubungkan akun Telegram ini ke rekam medis Anda, kirim:\n"
             . "`/start LINK <No. Rekam Medis>`\n\n"
             . "Atau bagikan kontak Anda lewat tombol share di bawah.\n\n"
             . "Ketik /help untuk perintah lain.";
    }

    private function helpMessage(): string
    {
        return "*Perintah tersedia:*\n\n"
             . "• `/start LINK <RM-id>` — hubungkan chat ke rekam medis\n"
             . "• `/unlink` — putuskan hubungan\n"
             . "• `/help` — tampilkan bantuan ini\n"
             . "• `/ping` — cek koneksi bot\n\n"
             . "Setelah terhubung, Anda akan menerima:\n"
             . "📅 Reminder jadwal treatment\n"
             . "🌿 Tips aftercare setelah treatment";
    }
}
