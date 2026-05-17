<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted before failing.
     */
    public int $tries = 3;

    /**
     * Backoff (seconds) between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $phoneNumber,
        public string $message,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $ok = $whatsapp->send($this->phoneNumber, $this->message);

        // Throw so the queue retries (status:false, network error, etc.).
        if (! $ok) {
            throw new \RuntimeException("WA send failed for {$this->phoneNumber}");
        }
    }
}
