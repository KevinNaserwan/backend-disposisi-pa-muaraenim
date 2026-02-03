<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    /**
     * Send a WhatsApp notification.
     *
     * @param string $phoneNumber
     * @param string $message
     * @return void
     */
    public function sendNotification(string $phoneNumber, string $message)
    {
        // Validate phone number
        if (empty($phoneNumber)) {
            Log::warning("Skipping WA notification: Phone number is empty.");
            return;
        }

        // Log the notification
        Log::info("WA Notification to {$phoneNumber}: {$message}");

        try {
            $token = env('FONNTE_TOKEN');
            if ($token) {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::withHeaders([
                    'Authorization' => $token,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $phoneNumber,
                    'message' => $message,
                ]);

                Log::info("Fonnte Response: " . $response->body());
            } else {
                Log::warning("FONNTE_TOKEN not set in .env");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send WA via Fonnte: " . $e->getMessage());
        }
    }
}
