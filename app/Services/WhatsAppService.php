<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Queue a WhatsApp notification.
     *
     * The actual HTTP call to Fonnte is pushed onto the queue so it never
     * blocks the API response (important when a disposition is sent to many
     * recipients at once).
     */
    public function sendNotification(string $phoneNumber, string $message): void
    {
        $phoneNumber = $this->normalize($phoneNumber);

        if ($phoneNumber === '') {
            Log::warning('Skipping WA notification: Phone number is empty.');

            return;
        }

        SendWhatsAppNotification::dispatch($phoneNumber, $message);
    }

    /**
     * Perform the actual send. Called from the queued job.
     */
    public function send(string $phoneNumber, string $message): bool
    {
        $token = config('services.fonnte.token');

        if (! $token) {
            Log::warning('FONNTE_TOKEN not set; WA notification not sent.');

            return false;
        }

        Log::info("WA Notification to {$phoneNumber}: {$message}");

        $response = Http::withHeaders(['Authorization' => $token])
            ->post('https://api.fonnte.com/send', [
                'target' => $phoneNumber,
                'message' => $message,
            ]);

        $body = $response->json();

        // Fonnte returns HTTP 200 even on failure, with "status": false.
        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            Log::error('Fonnte send failed: '.$response->body());

            return false;
        }

        Log::info('Fonnte send ok: '.$response->body());

        return true;
    }

    /**
     * Normalise Indonesian numbers: strip non-digits and turn a leading
     * "0" into the "62" country code.
     */
    private function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
