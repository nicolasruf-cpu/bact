<?php

namespace App\Modules\Patients\Actions;

use App\Modules\Audit\Services\Auditor;
use App\Modules\Patients\Data\PatientData;
use App\Modules\Patients\Enums\PatientAuditAction;
use App\Modules\Patients\Models\Patient;

/**
 * Busca al paciente por documento; si no existe, lo registra.
 * Un paciente existente no se modifica desde la recepción.
 * Se llama dentro de la transacción de la recepción.
 */
class FindOrRegisterPatient
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handle(PatientData $data): Patient
    {
        $existing = Patient::query()->where('document_number', $data->documentNumber)->first();

        if ($existing !== null) {
            return $existing;
        }

        $patient = Patient::create([
            'document_number' => $data->documentNumber,
            'last_name' => $data->lastName,
            'first_name' => $data->firstName,
            'birth_date' => $data->birthDate,
            'sex' => $data->sex,
        ]);

        $this->auditor->record(PatientAuditAction::Registered, $patient, after: [
            'document_number' => $patient->document_number,
        ]);

        return $patient;
    }
}
