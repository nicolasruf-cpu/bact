<?php

namespace App\Modules\Configuration\Data;

/**
 * Parámetros del frasco de hemocultivo, tomados de la versión de protocolo congelada.
 * Son configuración del laboratorio (plantilla de referencia en docs/templates/HEMOCULTIVO.md).
 */
final readonly class BloodCultureSettings
{
    /**
     * @param  list<string>  $instruments
     * @param  list<array{code: string, label: string}>  $bottleTypes
     * @param  list<array{code: string, label: string}>  $gramOptions
     */
    public function __construct(
        public int $incubationDaysAutomated,
        public int $incubationDaysManual,
        public int $blindSubcultureIntervalHours,
        public string $blindSubcultureMediumCode,
        public array $instruments,
        public array $bottleTypes,
        public array $gramOptions,
        public string $negativeResultLabel,
        public bool $communicateOnPositiveGram,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            incubationDaysAutomated: (int) $data['incubation_days_automated'],
            incubationDaysManual: (int) $data['incubation_days_manual'],
            blindSubcultureIntervalHours: (int) $data['blind_subculture_interval_hours'],
            blindSubcultureMediumCode: (string) $data['blind_subculture_medium_code'],
            instruments: array_values($data['instruments'] ?? []),
            bottleTypes: array_values($data['bottle_types'] ?? []),
            gramOptions: array_values($data['gram_options'] ?? []),
            negativeResultLabel: (string) $data['negative_result_label'],
            communicateOnPositiveGram: (bool) ($data['communicate_on_positive_gram'] ?? false),
        );
    }

    public function incubationDays(bool $automated): int
    {
        return $automated ? $this->incubationDaysAutomated : $this->incubationDaysManual;
    }

    public function bottleTypeLabel(string $code): string
    {
        return collect($this->bottleTypes)->firstWhere('code', $code)['label'] ?? $code;
    }

    public function gramLabel(string $code): string
    {
        return collect($this->gramOptions)->firstWhere('code', $code)['label'] ?? $code;
    }

    /**
     * Texto del resultado negativo con los días de incubación del frasco.
     */
    public function negativeResult(int $days): string
    {
        return str_replace('{days}', (string) $days, $this->negativeResultLabel);
    }
}
