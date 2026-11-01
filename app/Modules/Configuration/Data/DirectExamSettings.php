<?php

namespace App\Modules\Configuration\Data;

/**
 * Examen directo de la muestra, tomado de la versión de protocolo congelada.
 * Cada campo tiene opciones; las que indican microorganismos llevan `organisms: true`.
 * Si `critical_if_organisms` está activo, un directo con microorganismos abre un
 * resultado crítico (LCR, decisión del laboratorio 2026-11-01). Son configuración del
 * laboratorio: el sistema no interpreta el directo.
 */
final readonly class DirectExamSettings
{
    /**
     * @param  list<array{code: string, label: string, multiple?: bool, options: list<array{code: string, label: string, organisms?: bool}>}>  $fields
     */
    public function __construct(
        public string $label,
        public array $fields,
        public bool $criticalIfOrganisms,
        public bool $urgent,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: (string) ($data['label'] ?? 'Examen directo'),
            fields: array_values($data['fields'] ?? []),
            criticalIfOrganisms: (bool) ($data['critical_if_organisms'] ?? false),
            urgent: (bool) ($data['urgent'] ?? false),
        );
    }

    /**
     * @return array{code: string, label: string, multiple?: bool, options: list<array{code: string, label: string, organisms?: bool}>}|null
     */
    public function field(string $code): ?array
    {
        return collect($this->fields)->firstWhere('code', $code);
    }

    /**
     * @return array{code: string, label: string, organisms?: bool}|null
     */
    public function option(string $fieldCode, string $optionCode): ?array
    {
        return collect($this->field($fieldCode)['options'] ?? [])->firstWhere('code', $optionCode);
    }

    /**
     * Resultado legible: "Campo: opción, opción" por cada campo con algo elegido.
     *
     * @param  array<string, list<string>>  $results
     * @return list<array{field: string, values: list<string>}>
     */
    public function describe(array $results): array
    {
        $lines = [];
        foreach ($this->fields as $field) {
            $codes = $results[$field['code']] ?? [];
            if ($codes === []) {
                continue;
            }
            $lines[] = [
                'field' => $field['label'],
                'values' => array_map(fn (string $code) => $this->option($field['code'], $code)['label'] ?? $code, $codes),
            ];
        }

        return $lines;
    }
}
