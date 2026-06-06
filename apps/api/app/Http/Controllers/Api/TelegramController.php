<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function reminder(Request $request, TelegramService $tg): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'      => 'required|integer|exists:pasien,id',
            'telegram_chat_id' => 'required_without:phone|string',
            'when'           => 'required|string|max:100',
        ]);

        $pasien = Pasien::findOrFail($validated['pasien_id']);

        $chatId = $validated['telegram_chat_id']
            ?? $this->resolveChatIdFromPhone($pasien->nomor_telp);

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
            ?? $this->resolveChatIdFromPhone($pasien->nomor_telp);

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
     * NOTE: Telegram uses chat_id (numeric) not phone number.
     * To bridge phone → chat_id, the patient must start the bot first
     * and we store their chat_id in `pasien.telegram_chat_id`.
     * For now this is a placeholder; in production a webhook handler
     * (TelegramController@webhook) will store the mapping.
     */
    private function resolveChatIdFromPhone(string $phone): ?string
    {
        return null;
    }
}
