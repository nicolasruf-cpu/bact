<?php

namespace App\Modules\WorkBatches\Enums;

use App\Modules\Workflow\Enums\ActivityType;

enum WorkBatchType: string
{
    case Inoculation = 'inoculation';
    case Reading = 'reading';

    public function label(): string
    {
        return match ($this) {
            self::Inoculation => 'Siembra',
            self::Reading => 'Lectura',
        };
    }

    public function codePrefix(): string
    {
        return match ($this) {
            self::Inoculation => 'SIE',
            self::Reading => 'LEC',
        };
    }

    /**
     * @return list<ActivityType>
     */
    public function activityTypes(): array
    {
        return match ($this) {
            self::Inoculation => [ActivityType::Inoculate],
            self::Reading => [ActivityType::ReadCulture],
        };
    }
}
