<?php

namespace App\Modules\Samples\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Patients\Data\PatientData;
use App\Modules\Patients\Enums\Sex;
use App\Modules\Samples\Data\ReceiveSampleData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SamplesReceive->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'sample_type_id' => ['required', 'integer', Rule::exists('sample_types', 'id')->where('is_active', true)],
            'study_ids' => ['required', 'array', 'min:1'],
            'study_ids.*' => ['integer', 'distinct', Rule::exists('studies', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'collected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'requesting_service' => ['nullable', 'string', 'max:100'],
            'requesting_physician' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'barcode' => 'código de muestra',
            'document_number' => 'documento',
            'last_name' => 'apellido',
            'first_name' => 'nombre',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'sample_type_id' => 'tipo de muestra',
            'study_ids' => 'estudios',
            'priority' => 'prioridad',
            'collected_at' => 'fecha y hora de toma',
            'requesting_service' => 'servicio',
            'requesting_physician' => 'médico solicitante',
            'notes' => 'observaciones',
        ];
    }

    public function toData(): ReceiveSampleData
    {
        $birthDate = $this->date('birth_date');
        $collectedAt = $this->date('collected_at');

        return new ReceiveSampleData(
            idempotencyKey: $this->string('idempotency_key')->toString(),
            barcode: strtoupper(trim($this->string('barcode')->toString())),
            patient: new PatientData(
                documentNumber: strtoupper(trim($this->string('document_number')->toString())),
                lastName: trim($this->string('last_name')->toString()),
                firstName: trim($this->string('first_name')->toString()),
                birthDate: $birthDate ? CarbonImmutable::instance($birthDate) : null,
                sex: $this->enum('sex', Sex::class) ?? Sex::Unknown,
            ),
            sampleTypeId: $this->integer('sample_type_id'),
            studyIds: array_values(array_map('intval', (array) $this->input('study_ids'))),
            priority: $this->enum('priority', Priority::class) ?? Priority::Routine,
            collectedAt: $collectedAt ? CarbonImmutable::instance($collectedAt) : null,
            requestingService: $this->filled('requesting_service') ? $this->string('requesting_service')->toString() : null,
            requestingPhysician: $this->filled('requesting_physician') ? $this->string('requesting_physician')->toString() : null,
            notes: $this->filled('notes') ? $this->string('notes')->toString() : null,
        );
    }
}
