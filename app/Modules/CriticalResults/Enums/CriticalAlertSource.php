<?php

namespace App\Modules\CriticalResults\Enums;

enum CriticalAlertSource: string
{
    case PositiveGram = 'positive_gram';
    case DirectExam = 'direct_exam';

    public function label(): string
    {
        return match ($this) {
            self::PositiveGram => 'Gram de frasco positivo',
            self::DirectExam => 'Examen directo',
        };
    }
}
