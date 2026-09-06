<?php

namespace App\Listeners;

use App\Events\WhatsAppCommunicationEvent;
use App\Services\GallaboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected GallaboxService $gallaboxService;

    /**
     * Create the event listener.
     */
    public function __construct(GallaboxService $gallaboxService)
    {
        $this->gallaboxService = $gallaboxService;
    }

    /**
     * Handle the event.
     */
    public function handle(WhatsAppCommunicationEvent $event): void
    {
        try {
            // Send WhatsApp message via Gallabox
            $this->gallaboxService->sendTemplateMessage(
                phoneNumber: $event->phoneNumber,
                eventType: $event->eventType,
                variables: $event->variables
            );
        } catch (\Exception $e) {
            // Log error but don't throw - WhatsApp failure shouldn't break the main flow
            Log::error('WhatsApp notification listener failed', [
                'event_type' => $event->eventType,
                'phone' => $event->phoneNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(WhatsAppCommunicationEvent $event, \Throwable $exception): void
    {
        Log::error('WhatsApp notification job failed', [
            'event_type' => $event->eventType,
            'phone' => $event->phoneNumber,
            'error' => $exception->getMessage()
        ]);
    }
}
