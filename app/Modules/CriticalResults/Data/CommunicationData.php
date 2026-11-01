<?php

namespace App\Modules\CriticalResults\Data;

use App\Modules\CriticalResults\Enums\CommunicationChannel;

final readonly class CommunicationData
{
    public function __construct(
        public string $idempotencyKey,
        public string $recipientName,
        public ?string $recipientRole,
        public CommunicationChannel $channel,
        public bool $successful,
        public bool $readBackConfirmed,
        public ?string $notes,
    ) {}
}
