<?php

namespace App\Modules\CriticalResults\Enums;

enum CommunicationChannel: string
{
    case Phone = 'phone';
    case InPerson = 'in_person';
    case Message = 'message';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Teléfono',
            self::InPerson => 'En persona',
            self::Message => 'Mensaje',
            self::Other => 'Otro',
        };
    }
}
