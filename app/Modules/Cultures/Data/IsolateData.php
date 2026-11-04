<?php

namespace App\Modules\Cultures\Data;

use App\Modules\Cultures\Enums\IsolateSignificance;

final readonly class IsolateData
{
    public function __construct(
        public string $morphotype,
        public ?string $countCategory,
        public ?int $plateId,
        public bool $isSignificant,
        public ?IsolateSignificance $significance = null,
    ) {}

    public function significance(): IsolateSignificance
    {
        return $this->significance ?? ($this->isSignificant ? IsolateSignificance::Significant : IsolateSignificance::NotSignificant);
    }
}
