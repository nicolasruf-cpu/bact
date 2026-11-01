<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confirmación múltiple de siembra: todos con el protocolo estándar, sin modificaciones
 * (ACTIVITY_CATALOG: INOCULATE la admite solo en ese caso). Todo o nada.
 */
class InoculateWithStandardProtocol
{
    public function __construct(private readonly InoculateSampleStudy $inoculate) {}

    /**
     * @param  Collection<int, SampleStudy>  $studies
     * @param  array<string, mixed>  $context
     */
    public function handle(User $user, Collection $studies, array $context = []): int
    {
        return DB::transaction(function () use ($user, $studies, $context) {
            foreach ($studies as $study) {
                $this->inoculate->handle($user, $study, null, null, $context + ['bulk' => true]);
            }

            return $studies->count();
        });
    }
}
