<?php

namespace App\Modules\Identification\Events;

use App\Modules\Identification\Models\IsolateSubculture;

final readonly class IsolateSubcultureRead
{
    public function __construct(
        public IsolateSubculture $subculture,
        public int $userId,
    ) {}
}
