<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Events\DirectExamRecorded;
use App\Modules\Cultures\Models\DirectExam;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra el examen directo de un estudio con las opciones del protocolo congelado.
 * Si alguna opción elegida indica microorganismos y el protocolo lo marca como crítico
 * (LCR, decisión del laboratorio), se abre un resultado crítico. Uno por estudio:
 * repetir el mismo resultado devuelve el registrado; corregirlo es otra etapa (4e).
 */
class RecordDirectExam
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  array<string, list<string>>  $results  código de campo → códigos de opción
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, SampleStudy $study, array $results, ?string $description = null): DirectExam
    {
        if (! $user->can(Permission::DirectExamsPerform->value)) {
            throw new AuthorizationException('No tenés permiso para registrar exámenes directos.');
        }

        return DB::transaction(function () use ($user, $study, $results, $description) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->with(['protocolVersion', 'study', 'sample'])->findOrFail($study->id);
            $settings = $study->protocolVersion->directExamSettings();

            if ($settings === null) {
                throw ValidationException::withMessages(['results' => 'El protocolo de este estudio no incluye examen directo.']);
            }

            $results = $this->normalize($settings->fields, $results);

            $existing = DirectExam::query()->where('sample_study_id', $study->id)->first();
            if ($existing !== null) {
                if ($existing->results == $results && $existing->description === $description) {
                    return $existing;
                }
                throw ValidationException::withMessages(['results' => 'El examen directo ya está registrado.']);
            }

            if (! in_array($study->status, [SampleStudyStatus::Pending, SampleStudyStatus::InProcess, SampleStudyStatus::PreliminaryReported], true)) {
                throw ValidationException::withMessages(['results' => 'El estudio ya no admite el examen directo.']);
            }

            $organismsSeen = false;
            foreach ($results as $fieldCode => $codes) {
                foreach ($codes as $code) {
                    $organismsSeen = $organismsSeen || (bool) ($settings->option($fieldCode, $code)['organisms'] ?? false);
                }
            }

            $exam = DirectExam::create([
                'sample_study_id' => $study->id,
                'results' => $results,
                'description' => $description,
                'organisms_seen' => $organismsSeen,
                'performed_by' => $user->id,
                'performed_at' => Date::now(),
            ]);

            $this->auditor->record(CultureAuditAction::DirectExamRecorded, $exam, after: [
                'sample_study_id' => $study->id,
                'results' => $results,
                'organisms_seen' => $organismsSeen,
            ]);

            $text = collect($settings->describe($results))->map(fn (array $line) => implode(', ', $line['values']))->implode(' · ');
            event(new DirectExamRecorded(
                exam: $exam,
                sampleStudy: $study,
                summary: "{$study->study->name} · {$study->sample->barcode} · Directo: {$text}",
                critical: $settings->criticalIfOrganisms && $organismsSeen,
                userId: $user->id,
            ));

            return $exam;
        });
    }

    /**
     * Solo campos y opciones del protocolo; un campo simple admite una opción.
     *
     * @param  list<array{code: string, label: string, multiple?: bool, options: list<array{code: string, label: string, organisms?: bool}>}>  $fields
     * @param  array<string, list<string>>  $results
     * @return array<string, list<string>>
     *
     * @throws ValidationException
     */
    private function normalize(array $fields, array $results): array
    {
        $unknown = array_diff(array_keys($results), array_column($fields, 'code'));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['results' => 'Hay campos que no son del protocolo.']);
        }

        $normalized = [];
        foreach ($fields as $field) {
            $codes = array_values(array_unique(array_map('strval', $results[$field['code']] ?? [])));
            $valid = array_column($field['options'], 'code');
            foreach ($codes as $code) {
                if (! in_array($code, $valid, true)) {
                    throw ValidationException::withMessages(["results.{$field['code']}" => "Opción no válida en {$field['label']}."]);
                }
            }
            if (count($codes) > 1 && ! ($field['multiple'] ?? false)) {
                throw ValidationException::withMessages(["results.{$field['code']}" => "En {$field['label']} se elige una sola opción."]);
            }
            if ($codes !== []) {
                $normalized[$field['code']] = $codes;
            }
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['results' => 'Elegí al menos una opción.']);
        }

        return $normalized;
    }
}
