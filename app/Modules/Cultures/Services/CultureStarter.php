<?php

namespace App\Modules\Cultures\Services;

use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Events\CultureInoculated;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Samples\Models\SampleStudy;
use Carbon\CarbonInterface;

/**
 * Crea el cultivo de un estudio con sus placas y avisa que se sembró (Workflow programa
 * la lectura). Lo usan la siembra de placas y el subcultivo del frasco de hemocultivo.
 * Se llama dentro de la transacción de la operación que lo provoca; la auditoría es de quien llama.
 */
class CultureStarter
{
    /**
     * @param  iterable<array{medium_id: int, inoculum: string, incubation_temperature: string, atmosphere: mixed, is_enrichment?: bool, read_from_hours?: int|null, read_until_hours?: int|null}>  $plates
     */
    public function start(SampleStudy $study, iterable $plates, CarbonInterface $inoculatedAt, int $userId, ?string $notes = null): Culture
    {
        $culture = Culture::create([
            'sample_study_id' => $study->id,
            'attempt' => 1,
            'status' => CultureStatus::Incubating,
            'inoculated_at' => $inoculatedAt,
            'inoculated_by' => $userId,
            'notes' => $notes,
        ]);

        foreach ($plates as $plate) {
            $culture->plates()->create([...$plate, 'status' => PlateStatus::Incubating]);
        }

        event(new CultureInoculated($culture, $study, $userId));

        return $culture;
    }
}
