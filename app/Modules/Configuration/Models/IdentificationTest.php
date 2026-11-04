<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\IdentificationTestKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Prueba del catálogo de identificación (Gram, catalasa, TSI, sinergia BLEE…). Los
 * resultados posibles y la incubación los carga el laboratorio. Sin incubación, la prueba
 * se lee en el momento.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property IdentificationTestKind $kind
 * @property list<array{code: string, label: string}> $result_options
 * @property int|null $incubation_hours
 * @property bool $is_reportable
 * @property bool $is_active
 * @property bool $is_reference_template
 * @property int $lock_version
 */
class IdentificationTest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => IdentificationTestKind::class,
            'result_options' => 'array',
            'incubation_hours' => 'integer',
            'is_reportable' => 'boolean',
            'is_active' => 'boolean',
            'is_reference_template' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function resultLabel(string $code): ?string
    {
        foreach ($this->result_options as $option) {
            if ($option['code'] === $code) {
                return $option['label'];
            }
        }

        return null;
    }

    public function needsIncubation(): bool
    {
        return $this->incubation_hours !== null && $this->incubation_hours > 0;
    }
}
