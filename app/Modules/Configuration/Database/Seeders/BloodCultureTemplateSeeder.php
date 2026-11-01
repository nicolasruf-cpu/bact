<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Plantilla de referencia de hemocultivo y retrocultivo (docs/templates/HEMOCULTIVO.md, D-0008).
 * Por defecto se carga como BORRADOR: no se usa con pacientes hasta que la apruebe
 * el Director técnico responsable. Solo el DemoSeeder la carga vigente.
 * Es idempotente: no duplica ni pisa versiones existentes.
 */
class BloodCultureTemplateSeeder extends Seeder
{
    public const SOURCE = 'PLANTILLA DE REFERENCIA — requiere validación del laboratorio. '
        .'Basada en: CLSI M47 (Principles and Procedures for Blood Cultures); '
        .'Clinical Microbiology Procedures Handbook (ASM), "Blood Cultures". Ver docs/templates/HEMOCULTIVO.md.';

    /** Lectura del subcultivo del frasco positivo (HEMOCULTIVO.md §5). */
    public const READING_OPTIONS = [
        ['code' => 'POS', 'label' => 'Desarrollo', 'outcome' => 'growth'],
        ['code' => 'NEG', 'label' => 'Sin desarrollo en el subcultivo', 'outcome' => 'no_growth'],
        ['code' => 'REI', 'label' => 'Reincubar', 'outcome' => 'reincubate'],
    ];

    /** Parámetros del frasco (HEMOCULTIVO.md §3–4). */
    public const SETTINGS = [
        'incubation_days_automated' => 5,
        'incubation_days_manual' => 7,
        'blind_subculture_interval_hours' => 24,
        'blind_subculture_medium_code' => 'CHOC',
        'instruments' => ['BacT/ALERT'],
        'bottle_types' => [
            ['code' => 'AER', 'label' => 'Aerobio'],
            ['code' => 'ANA', 'label' => 'Anaerobio'],
            ['code' => 'PED', 'label' => 'Pediátrico'],
        ],
        'gram_options' => [
            ['code' => 'CGP-RAC', 'label' => 'Cocos gram positivos en racimos'],
            ['code' => 'CGP-CAD', 'label' => 'Cocos gram positivos en cadenas'],
            ['code' => 'CGP-PAR', 'label' => 'Cocos gram positivos en pares'],
            ['code' => 'BGN', 'label' => 'Bacilos gram negativos'],
            ['code' => 'BGP', 'label' => 'Bacilos gram positivos'],
            ['code' => 'CGN', 'label' => 'Cocos gram negativos'],
            ['code' => 'LEV', 'label' => 'Levaduras'],
            ['code' => 'MIX', 'label' => 'Más de un morfotipo'],
            ['code' => 'NO', 'label' => 'No se observan microorganismos'],
        ],
        'negative_result_label' => 'Sin desarrollo a los {days} días de incubación',
        // Decisión del laboratorio (2026-11-01): el Gram de un frasco positivo se comunica.
        'communicate_on_positive_gram' => true,
    ];

    public function run(ProtocolVersionStatus $status = ProtocolVersionStatus::Draft): void
    {
        $media = [
            Medium::firstOrCreate(['code' => 'AS'], ['name' => 'Agar sangre']),
            Medium::firstOrCreate(['code' => 'CHOC'], ['name' => 'Agar chocolate']),
            Medium::firstOrCreate(['code' => 'MAC'], ['name' => 'Agar MacConkey']),
        ];
        $atmospheres = [Atmosphere::Co2, Atmosphere::Co2, Atmosphere::Aerobic];

        $protocols = [
            ['study' => ['HEMO', 'Hemocultivo'], 'type' => ['SAN-PER', 'Sangre periférica']],
            ['study' => ['RETRO', 'Retrocultivo'], 'type' => ['SAN-CAT', 'Sangre por catéter']],
        ];

        foreach ($protocols as $definition) {
            $study = Study::firstOrCreate(['code' => $definition['study'][0]], ['name' => $definition['study'][1]]);
            $sampleType = SampleType::firstOrCreate(['code' => $definition['type'][0]], ['name' => $definition['type'][1]]);

            $protocol = Protocol::firstOrCreate(
                ['study_id' => $study->id, 'sample_type_id' => $sampleType->id],
                ['name' => "{$study->name} — {$sampleType->name}"],
            );

            if ($protocol->versions()->exists()) {
                continue;
            }

            /** @var ProtocolVersion $version */
            $version = $protocol->versions()->create([
                'version' => 1,
                'status' => $status,
                'is_reference_template' => true,
                'processing_kind' => ProcessingKind::BloodBottle,
                'source' => self::SOURCE,
                // Lectura de las placas (repiques y subcultivo del positivo).
                'first_read_min_hours' => 18,
                'first_read_due_hours' => 24,
                'reincubation_hours' => 24,
                'max_reincubations' => 1,
                'allow_bulk_negative_confirmation' => false,
                'reading_options' => self::READING_OPTIONS,
                'count_categories' => null,
                'blood_culture_settings' => self::SETTINGS,
                'ast_read_min_hours' => 16,
                'ast_read_due_hours' => 24,
                'activated_at' => $status === ProtocolVersionStatus::Active ? Date::now() : null,
            ]);

            // Medios del subcultivo del frasco positivo.
            foreach ($media as $position => $medium) {
                $version->media()->create([
                    'medium_id' => $medium->id,
                    'position' => $position + 1,
                    'inoculum' => 'Gota del frasco',
                    'incubation_temperature' => '35 ± 2 °C',
                    'atmosphere' => $atmospheres[$position],
                ]);
            }
        }
    }
}
