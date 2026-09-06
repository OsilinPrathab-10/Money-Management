<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppCommunicationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventType;
    public string $phoneNumber;
    public array $variables;

    /**
     * Create a new event instance.
     *
     * @param string $eventType Event type matching whatsapp_templates.event_type
     * @param string $phoneNumber Recipient phone number
     * @param array $variables Template variables ['1' => 'value1', '2' => 'value2']
     */
    public function __construct(string $eventType, string $phoneNumber, array $variables = [])
    {
        $this->eventType = $eventType;
        $this->phoneNumber = $phoneNumber;
        $this->variables = $variables;
    }
}
