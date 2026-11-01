<?php

namespace App\Modules\BloodCultures\Queries;

use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Support\Facades\Date;

/**
 * Datos de la pantalla del frasco: carga, repiques, positividad, Gram y el cultivo del positivo.
 */
class BottlePageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(SampleStudy $study): array
    {
        $study->loadMissing('protocolVersion');
        $version = $study->protocolVersion;
        $settings = $version->bloodCultureSettings();

        /** @var BloodCultureBottle|null $bottle */
        $bottle = BloodCultureBottle::query()
            ->where('sample_study_id', $study->id)
            ->with(['blindSubcultures.medium', 'gramStain', 'sampleStudy.protocolVersion'])
            ->first();

        /** @var Culture|null $culture */
        $culture = Culture::query()->where('sample_study_id', $study->id)->first();

        return [
            'settings' => $settings === null ? null : [
                'incubation_days_automated' => $settings->incubationDaysAutomated,
                'incubation_days_manual' => $settings->incubationDaysManual,
                'instruments' => $settings->instruments,
                'bottle_types' => $settings->bottleTypes,
                'gram_options' => $settings->gramOptions,
            ],
            'methods' => collect(BottleMethod::cases())->map(fn (BottleMethod $m) => ['value' => $m->value, 'label' => $m->label()])->all(),
            'bottle' => $bottle === null ? null : $this->bottle($bottle, $version->first_read_min_hours),
            'culture' => $culture === null ? null : [
                'ulid' => $culture->ulid,
                'status' => $culture->status->value,
                'status_label' => $culture->status->label(),
                'inoculated_at' => $culture->inoculated_at->toIso8601String(),
                'reading_url' => route('readings.create', $culture),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bottle(BloodCultureBottle $bottle, int $readMinHours): array
    {
        $settings = $bottle->settings();
        $days = $bottle->incubationDays();
        $next = $bottle->blindSubcultures->count() + 1;
        $nextAt = $bottle->loaded_at->copy()->addHours($next * $settings->blindSubcultureIntervalHours);
        $incubating = $bottle->status === BottleStatus::Incubating;

        return [
            'ulid' => $bottle->ulid,
            'method' => $bottle->method->value,
            'method_label' => $bottle->method->label(),
            'bottle_type' => $settings->bottleTypeLabel($bottle->bottle_type),
            'instrument' => $bottle->instrument,
            'instrument_position' => $bottle->instrument_position,
            'loaded_at' => $bottle->loaded_at->toIso8601String(),
            'incubation_ends_at' => $bottle->incubation_ends_at->toIso8601String(),
            'incubation_days' => $days,
            'status' => $bottle->status->value,
            'status_label' => $bottle->status->label(),
            'positive_at' => $bottle->positive_at?->toIso8601String(),
            'positivity_source' => $bottle->positivity_source?->label(),
            'minutes_to_positivity' => $bottle->minutesToPositivity(),
            'notes' => $bottle->notes,
            'can_confirm_negative_now' => $incubating && $bottle->isAutomated() && ! $bottle->incubation_ends_at->isFuture(),
            'next_subculture' => $incubating && ! $bottle->isAutomated() && $next <= $days ? [
                'number' => $next,
                'available_at' => $nextAt->toIso8601String(),
                'available' => ! $nextAt->isFuture(),
            ] : null,
            'blind_subcultures' => $bottle->blindSubcultures->map(fn (BlindSubculture $s) => [
                'ulid' => $s->ulid,
                'number' => $s->number,
                'medium' => $s->medium->name,
                'subcultured_at' => $s->subcultured_at->toIso8601String(),
                'readable_at' => $s->subcultured_at->copy()->addHours($readMinHours)->toIso8601String(),
                'readable' => $s->subcultured_at->copy()->addHours($readMinHours)->lessThanOrEqualTo(Date::now()),
                'result' => $s->result?->value,
                'result_label' => $s->result?->label(),
                'read_at' => $s->read_at?->toIso8601String(),
            ])->values()->all(),
            'gram' => $bottle->gramStain === null ? null : [
                'option_code' => $bottle->gramStain->option_code,
                'label' => $settings->gramLabel($bottle->gramStain->option_code),
                'description' => $bottle->gramStain->description,
                'performed_at' => $bottle->gramStain->performed_at->toIso8601String(),
            ],
        ];
    }
}
