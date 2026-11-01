<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BloodCultureBottle;

/**
 * Subcultivo del frasco positivo hecho (el cultivo además emite CultureInoculated).
 */
final readonly class BottleSubcultured
{
    public function __construct(
        public BloodCultureBottle $bottle,
        public int $userId,
    ) {}
}
