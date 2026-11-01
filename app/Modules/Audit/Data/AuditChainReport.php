<?php

namespace App\Modules\Audit\Data;

final readonly class AuditChainReport
{
    private function __construct(
        public bool $valid,
        public int $checked,
        public ?int $brokenAtEventId,
        public ?string $problem,
    ) {}

    public static function ok(int $checked): self
    {
        return new self(true, $checked, null, null);
    }

    public static function broken(int $checked, ?int $eventId, string $problem): self
    {
        return new self(false, $checked, $eventId, $problem);
    }
}
