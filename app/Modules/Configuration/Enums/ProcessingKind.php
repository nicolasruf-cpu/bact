<?php

namespace App\Modules\Configuration\Enums;

/**
 * Cómo se procesa un estudio: cultivo en placas (urocultivo) o frasco de hemocultivo.
 */
enum ProcessingKind: string
{
    case Plate = 'plate';
    case BloodBottle = 'blood_bottle';
}
