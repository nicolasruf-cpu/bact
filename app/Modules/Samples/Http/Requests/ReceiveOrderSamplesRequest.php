<?php

namespace App\Modules\Samples\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Patients\Data\PatientData;
use App\Modules\Patients\Enums\Sex;
use App\Modules\Samples\Data\IncomingSampleData;
use App\Modules\Samples\Data\ReceptionData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveOrderSamplesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SamplesReceive->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $samples = array_map(fn ($sample) => is_array($sample) ? [
            ...$sample,
            'barcode' => isset($sample['barcode']) ? strtoupper(trim((string) $sample['barcode'])) : null,
        ] : $sample, (array) $this->input('samples', []));

        $this->merge([
            'order_number' => $this->filled('order_number') ? strtoupper(trim($this->string('order_number')->toString())) : null,
            // "30.111.222", "30 111 222" y "30-111-222" son el mismo documento.
            'document_number' => $this->filled('document_number')
                ? strtoupper((string) preg_replace('/[\s.\-]/', '', $this->string('document_number')->toString()))
                : null,
            'samples' => $samples,
        ]);
    }

    /**
     * Códigos de barras: cualquier carácter imprimible salvo espacios (Code 128 / Code 39
     * pueden traer . / - _ + y otros signos).
     */
    private const CODE_FORMAT = 'regex:/^[\x21-\x7E]+$/';

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_number.regex' => 'El número de orden no puede tener espacios ni letras acentuadas.',
            'document_number.regex' => 'El documento solo puede tener letras y números.',
            'samples.*.barcode.regex' => 'El código de muestra no puede tener espacios ni letras acentuadas.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'order_number' => ['nullable', 'string', 'max:50', self::CODE_FORMAT],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'requesting_service' => ['nullable', 'string', 'max:100'],
            'requesting_physician' => ['nullable', 'string', 'max:150'],
            'samples' => ['required', 'array', 'min:1', 'max:20'],
            'samples.*.barcode' => ['required', 'string', 'max:50', self::CODE_FORMAT, 'distinct'],
            'samples.*.sample_type_id' => ['required', 'integer', Rule::exists('sample_types', 'id')->where('is_active', true)],
            'samples.*.study_ids' => ['required', 'array', 'min:1'],
            'samples.*.study_ids.*' => ['integer', Rule::exists('studies', 'id')->where('is_active', true)],
            'samples.*.collected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'samples.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_number' => 'número de orden',
            'document_number' => 'documento',
            'last_name' => 'apellido',
            'first_name' => 'nombre',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'priority' => 'prioridad',
            'requesting_service' => 'servicio',
            'requesting_physician' => 'médico solicitante',
            'samples' => 'muestras',
            'samples.*.barcode' => 'código de muestra',
            'samples.*.sample_type_id' => 'tipo de muestra',
            'samples.*.study_ids' => 'estudios',
            'samples.*.collected_at' => 'fecha y hora de toma',
            'samples.*.notes' => 'observaciones',
        ];
    }

    public function toData(): ReceptionData
    {
        $birthDate = $this->date('birth_date');

        /** @var list<array<string, mixed>> $samples */
        $samples = array_values((array) $this->validated('samples'));

        return new ReceptionData(
            idempotencyKey: $this->string('idempotency_key')->toString(),
            orderNumber: $this->input('order_number'),
            patient: new PatientData(
                documentNumber: $this->string('document_number')->toString(),
                lastName: trim($this->string('last_name')->toString()),
                firstName: trim($this->string('first_name')->toString()),
                birthDate: $birthDate ? CarbonImmutable::instance($birthDate) : null,
                sex: $this->enum('sex', Sex::class) ?? Sex::Unknown,
            ),
            requestingService: $this->filled('requesting_service') ? $this->string('requesting_service')->toString() : null,
            requestingPhysician: $this->filled('requesting_physician') ? $this->string('requesting_physician')->toString() : null,
            priority: $this->enum('priority', Priority::class) ?? Priority::Routine,
            samples: array_map(fn (array $sample) => new IncomingSampleData(
                barcode: (string) $sample['barcode'],
                sampleTypeId: (int) $sample['sample_type_id'],
                studyIds: array_values(array_map('intval', (array) $sample['study_ids'])),
                collectedAt: ! empty($sample['collected_at']) ? CarbonImmutable::parse((string) $sample['collected_at']) : null,
                notes: ! empty($sample['notes']) ? (string) $sample['notes'] : null,
            ), $samples),
        );
    }
}
