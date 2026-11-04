<?php

namespace App\Modules\Configuration\Enums;

use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Organization\Models\Site;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogos generales que edita el Administrador o el Director técnico
 * (config.catalogs.edit). El código no cambia después del alta: lo usan las
 * plantillas, las importaciones y los registros históricos. Nada se borra: se desactiva.
 */
enum CatalogType: string
{
    case SampleTypes = 'sample-types';
    case Studies = 'studies';
    case Media = 'media';
    case Organisms = 'organisms';
    case Antibiotics = 'antibiotics';
    case Sites = 'sites';

    public function label(): string
    {
        return match ($this) {
            self::SampleTypes => 'Tipos de muestra',
            self::Studies => 'Estudios',
            self::Media => 'Medios de cultivo',
            self::Organisms => 'Microorganismos',
            self::Antibiotics => 'Antibióticos',
            self::Sites => 'Sedes',
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::SampleTypes => 'tipo de muestra',
            self::Studies => 'estudio',
            self::Media => 'medio',
            self::Organisms => 'microorganismo',
            self::Antibiotics => 'antibiótico',
            self::Sites => 'sede',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::SampleTypes => SampleType::class,
            self::Studies => Study::class,
            self::Media => Medium::class,
            self::Organisms => Organism::class,
            self::Antibiotics => Antibiotic::class,
            self::Sites => Site::class,
        };
    }

    public function table(): string
    {
        return (new ($this->modelClass()))->getTable();
    }

    public function codeMaxLength(): int
    {
        return 20;
    }

    /**
     * El grupo define el panel y los puntos de corte: se fija en el alta (Q-A10).
     */
    public function hasOrganismGroup(): bool
    {
        return $this === self::Organisms;
    }

    /**
     * Carga del disco y antibiótico que se informa (prueba subrogante, A7). Se pueden cambiar.
     */
    public function hasAntibioticFields(): bool
    {
        return $this === self::Antibiotics;
    }
}
