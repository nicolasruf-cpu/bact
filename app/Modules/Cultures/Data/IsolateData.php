<?php

namespace App\Modules\Cultures\Data;

final readonly class IsolateData
{
    public function __construct(
        public string $morphotype,
        public ?string $countCategory,
        public ?int $plateId,
        public bool $isSignificant,
    ) {}
}
