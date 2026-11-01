<?php

namespace App\Modules\Patients\Models;

use App\Modules\Patients\Database\Factories\PatientFactory;
use App\Modules\Patients\Enums\Sex;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Paciente: solo los datos mínimos necesarios (SECURITY.md).
 *
 * @property int $id
 * @property string $ulid
 * @property string $document_number
 * @property string $last_name
 * @property string $first_name
 * @property Carbon|null $birth_date
 * @property Sex $sex
 * @property string|null $external_id
 */
#[UseFactory(PatientFactory::class)]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id', 'ulid'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
        ];
    }

    public function fullName(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }
}
