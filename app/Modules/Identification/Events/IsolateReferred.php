<?php

namespace App\Modules\Identification\Events;

use App\Modules\Identification\Models\Referral;

final readonly class IsolateReferred
{
    public function __construct(
        public Referral $referral,
        public int $userId,
    ) {}
}
