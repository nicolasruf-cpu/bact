<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Configuration\Models\ProtocolVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de forma de una versión de protocolo. No son reglas clínicas: verifican que el
 * contenido cargado por el laboratorio esté completo y sea coherente para que el
 * sistema lo pueda ejecutar (MICROBIOLOGY_RULES §2). Los valores los decide el laboratorio.
 */
class ProtocolVersionRules
{
    private const CODE = 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * Valida y normaliza el contenido de un borrador.
     *
     * @param  array<string, mixed>  $data
     * @return array{version: array<string, mixed>, media: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function normalize(array $data, ProcessingKind $kind): array
    {
        $atmospheres = array_map(fn (Atmosphere $a) => $a->value, Atmosphere::cases());

        $validated = Validator::make($data, [
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:5000'],
            'reading_schedule' => ['present', 'array', 'max:10'],
            'reading_schedule.*.min_hours' => ['required', 'integer', 'min:0', 'max:2000'],
            'reading_schedule.*.due_hours' => ['required', 'integer', 'min:0', 'max:2000'],
            'reincubation_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'max_reincubations' => ['required', 'integer', 'min:0', 'max:10'],
            'allow_bulk_negative_confirmation' => ['required', 'boolean'],
            'reading_options' => ['present', 'array', 'max:30'],
            'reading_options.*.code' => ['required', 'string', 'max:20', self::CODE, 'distinct'],
            'reading_options.*.label' => ['required', 'string', 'max:255'],
            'reading_options.*.outcome' => ['required', Rule::enum(ReadingOutcome::class)],
            'count_categories' => ['present', 'array', 'max:20'],
            'count_categories.*.code' => ['required', 'string', 'max:20', self::CODE, 'distinct'],
            'count_categories.*.label' => ['required', 'string', 'max:255'],
            'ast_read_min_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'ast_read_due_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'media' => ['present', 'array', 'max:20'],
            'media.*.medium_id' => ['required', 'integer', 'distinct', Rule::exists('media', 'id')->where('is_active', true)],
            'media.*.inoculum' => ['required', 'string', 'max:50'],
            'media.*.incubation_temperature' => ['required', 'string', 'max:30'],
            'media.*.atmosphere' => ['required', Rule::in($atmospheres)],
            'media.*.is_enrichment' => ['required', 'boolean'],
            'media.*.enrichment_subculture_min_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'media.*.enrichment_subculture_due_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'media.*.enrichment_subculture_media' => ['nullable', 'array', 'max:10'],
            'media.*.enrichment_subculture_media.*.medium_code' => ['required', 'string', Rule::exists('media', 'code')->where('is_active', true)],
            'media.*.enrichment_subculture_media.*.incubation_temperature' => ['required', 'string', 'max:30'],
            'media.*.enrichment_subculture_media.*.atmosphere' => ['required', Rule::in($atmospheres)],
            'direct_exam_settings' => ['nullable', 'array'],
            'direct_exam_settings.label' => ['required_with:direct_exam_settings', 'string', 'max:100'],
            'direct_exam_settings.urgent' => ['boolean'],
            'direct_exam_settings.critical_if_organisms' => ['boolean'],
            'direct_exam_settings.fields' => ['required_with:direct_exam_settings', 'array', 'min:1', 'max:20'],
            'direct_exam_settings.fields.*.code' => ['required', 'string', 'max:20', self::CODE, 'distinct'],
            'direct_exam_settings.fields.*.label' => ['required', 'string', 'max:100'],
            'direct_exam_settings.fields.*.multiple' => ['boolean'],
            'direct_exam_settings.fields.*.options' => ['required', 'array', 'min:1', 'max:30'],
            'direct_exam_settings.fields.*.options.*.code' => ['required', 'string', 'max:20', self::CODE],
            'direct_exam_settings.fields.*.options.*.label' => ['required', 'string', 'max:255'],
            'direct_exam_settings.fields.*.options.*.organisms' => ['boolean'],
            'blood_culture_settings' => [$kind === ProcessingKind::BloodBottle ? 'required' : 'nullable', 'array'],
            'blood_culture_settings.incubation_days_automated' => ['required_with:blood_culture_settings', 'integer', 'min:1', 'max:60'],
            'blood_culture_settings.incubation_days_manual' => ['required_with:blood_culture_settings', 'integer', 'min:1', 'max:60'],
            'blood_culture_settings.blind_subculture_interval_hours' => ['required_with:blood_culture_settings', 'integer', 'min:1', 'max:500'],
            'blood_culture_settings.blind_subculture_medium_code' => ['required_with:blood_culture_settings', 'string', Rule::exists('media', 'code')],
            'blood_culture_settings.instruments' => ['sometimes', 'array', 'max:10'],
            'blood_culture_settings.instruments.*' => ['required', 'string', 'max:50', 'distinct'],
            'blood_culture_settings.bottle_types' => ['required_with:blood_culture_settings', 'array', 'min:1', 'max:10'],
            'blood_culture_settings.bottle_types.*.code' => ['required', 'string', 'max:20', self::CODE, 'distinct'],
            'blood_culture_settings.bottle_types.*.label' => ['required', 'string', 'max:100'],
            'blood_culture_settings.gram_options' => ['required_with:blood_culture_settings', 'array', 'min:1', 'max:30'],
            'blood_culture_settings.gram_options.*.code' => ['required', 'string', 'max:20', self::CODE, 'distinct'],
            'blood_culture_settings.gram_options.*.label' => ['required', 'string', 'max:255'],
            'blood_culture_settings.negative_result_label' => ['required_with:blood_culture_settings', 'string', 'max:255'],
            'blood_culture_settings.communicate_on_positive_gram' => ['boolean'],
        ], [], $this->attributes())->validate();

        $errors = [];

        $schedule = array_map(fn (array $s) => ['min_hours' => (int) $s['min_hours'], 'due_hours' => (int) $s['due_hours']], array_values($validated['reading_schedule']));
        foreach ($schedule as $i => $step) {
            if ($step['due_hours'] < $step['min_hours']) {
                $errors["reading_schedule.{$i}.due_hours"] = 'El vencimiento no puede ser anterior al inicio.';
            } elseif ($i > 0 && $step['min_hours'] <= $schedule[$i - 1]['min_hours']) {
                $errors["reading_schedule.{$i}.min_hours"] = 'Cada lectura tiene que empezar después de la anterior.';
            }
        }

        $astMin = $validated['ast_read_min_hours'] ?? null;
        $astDue = $validated['ast_read_due_hours'] ?? null;
        if (($astMin === null) !== ($astDue === null)) {
            $errors['ast_read_due_hours'] = 'Completá las dos horas de lectura del antibiograma, o ninguna.';
        } elseif ($astMin !== null && $astDue < $astMin) {
            $errors['ast_read_due_hours'] = 'El vencimiento no puede ser anterior al inicio.';
        }

        $media = [];
        foreach (array_values($validated['media']) as $i => $medium) {
            $isEnrichment = (bool) $medium['is_enrichment'];
            $row = [
                'medium_id' => (int) $medium['medium_id'],
                'position' => $i + 1,
                'inoculum' => trim((string) $medium['inoculum']),
                'incubation_temperature' => trim((string) $medium['incubation_temperature']),
                'atmosphere' => (string) $medium['atmosphere'],
                'is_enrichment' => $isEnrichment,
                'enrichment_subculture_min_hours' => null,
                'enrichment_subculture_due_hours' => null,
                'enrichment_subculture_media' => null,
            ];

            if ($isEnrichment) {
                $min = $medium['enrichment_subculture_min_hours'] ?? null;
                $due = $medium['enrichment_subculture_due_hours'] ?? null;
                $targets = array_values($medium['enrichment_subculture_media'] ?? []);
                if ($min === null || $due === null || $due < $min) {
                    $errors["media.{$i}.enrichment_subculture_due_hours"] = 'Indicá desde y hasta qué hora se repica el caldo.';
                }
                if ($targets === []) {
                    $errors["media.{$i}.enrichment_subculture_media"] = 'Indicá a qué medios se repica el caldo.';
                }
                $row['enrichment_subculture_min_hours'] = $min === null ? null : (int) $min;
                $row['enrichment_subculture_due_hours'] = $due === null ? null : (int) $due;
                $row['enrichment_subculture_media'] = array_map(fn (array $t) => [
                    'medium_code' => (string) $t['medium_code'],
                    'incubation_temperature' => trim((string) $t['incubation_temperature']),
                    'atmosphere' => (string) $t['atmosphere'],
                ], $targets);
            }

            $media[] = $row;
        }

        $directExam = $validated['direct_exam_settings'] ?? null;
        if ($directExam !== null) {
            foreach (array_values($directExam['fields']) as $i => $field) {
                $codes = array_column($field['options'], 'code');
                if (count($codes) !== count(array_unique($codes))) {
                    $errors["direct_exam_settings.fields.{$i}.options"] = 'Hay opciones repetidas en '.$field['label'].'.';
                }
            }
            $directExam = [
                'label' => trim((string) $directExam['label']),
                'urgent' => (bool) ($directExam['urgent'] ?? false),
                'critical_if_organisms' => (bool) ($directExam['critical_if_organisms'] ?? false),
                'fields' => array_map(fn (array $field) => [
                    'code' => (string) $field['code'],
                    'label' => trim((string) $field['label']),
                    'multiple' => (bool) ($field['multiple'] ?? false),
                    'options' => array_map(fn (array $o) => [
                        'code' => (string) $o['code'],
                        'label' => trim((string) $o['label']),
                        'organisms' => (bool) ($o['organisms'] ?? false),
                    ], array_values($field['options'])),
                ], array_values($directExam['fields'])),
            ];
        }

        $blood = $validated['blood_culture_settings'] ?? null;
        if ($kind === ProcessingKind::Plate) {
            $blood = null;
        } elseif ($blood !== null) {
            $blood = [
                'incubation_days_automated' => (int) $blood['incubation_days_automated'],
                'incubation_days_manual' => (int) $blood['incubation_days_manual'],
                'blind_subculture_interval_hours' => (int) $blood['blind_subculture_interval_hours'],
                'blind_subculture_medium_code' => (string) $blood['blind_subculture_medium_code'],
                'instruments' => array_values(array_map('strval', $blood['instruments'] ?? [])),
                'bottle_types' => array_map(fn (array $t) => ['code' => (string) $t['code'], 'label' => trim((string) $t['label'])], array_values($blood['bottle_types'])),
                'gram_options' => array_map(fn (array $t) => ['code' => (string) $t['code'], 'label' => trim((string) $t['label'])], array_values($blood['gram_options'])),
                'negative_result_label' => trim((string) $blood['negative_result_label']),
                'communicate_on_positive_gram' => (bool) ($blood['communicate_on_positive_gram'] ?? false),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $first = $schedule[0] ?? ['min_hours' => 0, 'due_hours' => 0];

        return [
            'version' => [
                'notes' => $validated['notes'] ?? null,
                'source' => $validated['source'] ?? null,
                'reading_schedule' => $schedule,
                'first_read_min_hours' => $first['min_hours'],
                'first_read_due_hours' => $first['due_hours'],
                'reincubation_hours' => $validated['reincubation_hours'] ?? null,
                'max_reincubations' => (int) $validated['max_reincubations'],
                'allow_bulk_negative_confirmation' => (bool) $validated['allow_bulk_negative_confirmation'],
                'reading_options' => array_map(fn (array $o) => [
                    'code' => (string) $o['code'],
                    'label' => trim((string) $o['label']),
                    'outcome' => (string) $o['outcome'],
                ], array_values($validated['reading_options'])),
                'count_categories' => array_map(fn (array $c) => [
                    'code' => (string) $c['code'],
                    'label' => trim((string) $c['label']),
                ], array_values($validated['count_categories'])),
                'ast_read_min_hours' => $astMin === null ? null : (int) $astMin,
                'ast_read_due_hours' => $astDue === null ? null : (int) $astDue,
                'direct_exam_settings' => $directExam,
                'blood_culture_settings' => $blood,
            ],
            'media' => $media,
        ];
    }

    /**
     * Lo que falta para poder aprobar una versión: sin esto el sistema no puede ejecutar
     * el protocolo. Devuelve una lista de motivos (vacía = completa).
     *
     * @return list<string>
     */
    public function missingForApproval(ProtocolVersion $version): array
    {
        return array_column($this->missingItems($version), 'message');
    }

    /**
     * Lo mismo que missingForApproval, con la sección del editor a la que corresponde
     * cada faltante (media, schedule, results, blood, ast).
     *
     * @return list<array{section: string, message: string}>
     */
    public function missingItems(ProtocolVersion $version): array
    {
        $missing = [];
        $outcomes = array_column($version->reading_options ?? [], 'outcome');
        $plates = $version->media->where('is_enrichment', false);

        if ($plates->isEmpty()) {
            $missing[] = ['section' => 'media', 'message' => 'Falta al menos un medio que se lea (no un caldo).'];
        }
        if ($version->reading_schedule === null || $version->reading_schedule === []) {
            $missing[] = ['section' => 'schedule', 'message' => 'Falta el cronograma de lecturas.'];
        }
        if (! in_array(ReadingOutcome::Growth->value, $outcomes, true)) {
            $missing[] = ['section' => 'results', 'message' => 'Falta una opción de lectura con resultado "desarrollo".'];
        }
        if (! in_array(ReadingOutcome::NoGrowth->value, $outcomes, true)) {
            $missing[] = ['section' => 'results', 'message' => 'Falta una opción de lectura con resultado "sin desarrollo".'];
        }
        if (in_array(ReadingOutcome::Reincubate->value, $outcomes, true) && ($version->reincubation_hours === null || $version->max_reincubations < 1)) {
            $missing[] = ['section' => 'results', 'message' => 'Hay una opción "reincubar" pero faltan las horas o la cantidad de reincubaciones.'];
        }
        if ($version->isBloodCulture() && $version->blood_culture_settings === null) {
            $missing[] = ['section' => 'blood', 'message' => 'Faltan los parámetros del frasco de hemocultivo.'];
        }
        if ($version->ast_read_min_hours === null) {
            $missing[] = ['section' => 'ast', 'message' => 'Faltan las horas de lectura del antibiograma.'];
        }

        return $missing;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'reading_schedule.*.min_hours' => 'desde (h)',
            'reading_schedule.*.due_hours' => 'hasta (h)',
            'reading_options.*.code' => 'código de la opción',
            'reading_options.*.label' => 'texto de la opción',
            'reading_options.*.outcome' => 'resultado de la opción',
            'count_categories.*.code' => 'código del recuento',
            'count_categories.*.label' => 'texto del recuento',
            'media.*.medium_id' => 'medio',
            'media.*.inoculum' => 'inóculo',
            'media.*.incubation_temperature' => 'temperatura',
            'media.*.atmosphere' => 'atmósfera',
            'direct_exam_settings.label' => 'nombre del examen directo',
            'direct_exam_settings.fields' => 'campos del examen directo',
            'direct_exam_settings.fields.*.code' => 'código del campo',
            'direct_exam_settings.fields.*.label' => 'nombre del campo',
            'direct_exam_settings.fields.*.options' => 'opciones del campo',
            'blood_culture_settings' => 'parámetros del frasco',
        ];
    }
}
