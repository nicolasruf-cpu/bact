<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Plantillas de referencia del resto de los cultivos en placa (docs/templates/CULTIVOS.md, D-0008):
 * secreciones y heridas, líquidos de punción, LCR, respiratorias, coprocultivo, genitales y fauces.
 * Por defecto se cargan como BORRADOR: no se usan con pacientes hasta que las apruebe el
 * Director técnico responsable. Solo el DemoSeeder las carga vigentes.
 * Es idempotente: no duplica ni pisa versiones existentes.
 */
class PlateCultureTemplateSeeder extends Seeder
{
    public const SOURCE = 'PLANTILLA DE REFERENCIA — requiere validación del laboratorio. '
        .'Basada en: Clinical Microbiology Procedures Handbook (ASM); '
        .'IDSA/ASM Guide to Utilization of the Microbiology Laboratory (2024). Ver docs/templates/CULTIVOS.md.';

    public const MEDIA = [
        'AS' => 'Agar sangre',
        'CHOC' => 'Agar chocolate',
        'MAC' => 'Agar MacConkey',
        'SS' => 'Agar Salmonella-Shigella',
        'XLD' => 'Agar XLD',
        'CAMPY' => 'Agar selectivo para Campylobacter',
        'SEL' => 'Caldo selenito',
        'TM' => 'Agar Thayer-Martin modificado',
        'SAB' => 'Agar Sabouraud',
    ];

    /** Morfologías del Gram; "no se observan" no cuenta como microorganismos. */
    private const GRAM_ORGANISMS = [
        ['code' => 'CGP-RAC', 'label' => 'Cocos gram positivos en racimos', 'organisms' => true],
        ['code' => 'CGP-CAD', 'label' => 'Cocos gram positivos en cadenas', 'organisms' => true],
        ['code' => 'CGP-PAR', 'label' => 'Cocos gram positivos en pares', 'organisms' => true],
        ['code' => 'DGN', 'label' => 'Diplococos gram negativos', 'organisms' => true],
        ['code' => 'BGN', 'label' => 'Bacilos gram negativos', 'organisms' => true],
        ['code' => 'BGP', 'label' => 'Bacilos gram positivos', 'organisms' => true],
        ['code' => 'LEV', 'label' => 'Levaduras', 'organisms' => true],
        ['code' => 'NO', 'label' => 'No se observan microorganismos'],
    ];

    private const AMOUNT = [
        ['code' => 'NO', 'label' => 'No se observan'],
        ['code' => 'ESC', 'label' => 'Escasos'],
        ['code' => 'MOD', 'label' => 'Moderados'],
        ['code' => 'ABU', 'label' => 'Abundantes'],
    ];

    private const PRESENCE = [
        ['code' => 'NO', 'label' => 'No se observan'],
        ['code' => 'SI', 'label' => 'Se observan'],
    ];

    private const SEMIQUANTITATIVE = [
        ['code' => 'ESC', 'label' => 'Escaso desarrollo'],
        ['code' => 'MOD', 'label' => 'Moderado desarrollo'],
        ['code' => 'ABU', 'label' => 'Abundante desarrollo'],
    ];

    public function run(ProtocolVersionStatus $status = ProtocolVersionStatus::Draft): void
    {
        foreach (self::MEDIA as $code => $name) {
            Medium::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ($this->definitions() as $definition) {
            $study = Study::firstOrCreate(['code' => $definition['study'][0]], ['name' => $definition['study'][1]]);

            foreach ($definition['types'] as $type) {
                $sampleType = SampleType::firstOrCreate(['code' => $type['code']], ['name' => $type['name']]);
                $protocol = Protocol::firstOrCreate(
                    ['study_id' => $study->id, 'sample_type_id' => $sampleType->id],
                    ['name' => "{$study->name} — {$sampleType->name}"],
                );

                if ($protocol->versions()->exists()) {
                    continue;
                }

                $schedule = $type['schedule'] ?? $definition['schedule'];

                /** @var ProtocolVersion $version */
                $version = $protocol->versions()->create([
                    'version' => 1,
                    'status' => $status,
                    'is_reference_template' => true,
                    'source' => self::SOURCE,
                    'first_read_min_hours' => $schedule[0]['min_hours'],
                    'first_read_due_hours' => $schedule[0]['due_hours'],
                    'reading_schedule' => $schedule,
                    'reincubation_hours' => 24,
                    'max_reincubations' => 1,
                    'allow_bulk_negative_confirmation' => false,
                    'reading_options' => $definition['reading_options'],
                    'count_categories' => $type['count_categories'] ?? $definition['count_categories'] ?? null,
                    'direct_exam_settings' => array_key_exists('direct_exam', $type) ? $type['direct_exam'] : ($definition['direct_exam'] ?? null),
                    'ast_read_min_hours' => 16,
                    'ast_read_due_hours' => 24,
                    'allow_presumptive_ast' => true,
                    'activated_at' => $status === ProtocolVersionStatus::Active ? Date::now() : null,
                ]);

                foreach ($type['media'] ?? $definition['media'] as $position => $medium) {
                    $version->media()->create([
                        'medium_id' => Medium::query()->where('code', $medium['code'])->firstOrFail()->id,
                        'position' => $position + 1,
                        'inoculum' => $type['inoculum'],
                        'incubation_temperature' => $medium['temperature'] ?? '35 ± 2 °C',
                        'atmosphere' => $medium['atmosphere'],
                        'is_enrichment' => isset($medium['enrichment']),
                        'enrichment_subculture_min_hours' => $medium['enrichment']['min_hours'] ?? null,
                        'enrichment_subculture_due_hours' => $medium['enrichment']['due_hours'] ?? null,
                        'enrichment_subculture_media' => $medium['enrichment']['media'] ?? null,
                        'read_from_hours' => $medium['from'] ?? null,
                        'read_until_hours' => $medium['until'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        $readings = fn (array $hours): array => array_map(fn (int $h) => ['min_hours' => $h - 6, 'due_hours' => $h], $hours);
        $gram = fn (bool $critical = false, bool $urgent = false, string $label = 'Examen directo (Gram)'): array => [
            'label' => $label,
            'fields' => [
                ['code' => 'LEU', 'label' => 'Leucocitos', 'options' => self::AMOUNT],
                ['code' => 'MO', 'label' => 'Microorganismos', 'multiple' => true, 'options' => self::GRAM_ORGANISMS],
            ],
            'critical_if_organisms' => $critical,
            'urgent' => $urgent,
        ];
        $aerobic = [
            ['code' => 'AS', 'atmosphere' => Atmosphere::Co2],
            ['code' => 'CHOC', 'atmosphere' => Atmosphere::Co2],
            ['code' => 'MAC', 'atmosphere' => Atmosphere::Aerobic],
        ];
        $options = fn (string $negative, ?string $notSignificant = null): array => array_values(array_filter([
            ['code' => 'NEG', 'label' => $negative, 'outcome' => 'no_growth'],
            ['code' => 'POS', 'label' => 'Desarrollo', 'outcome' => 'growth'],
            $notSignificant === null ? null : ['code' => 'NS', 'label' => $notSignificant, 'outcome' => 'not_significant'],
            ['code' => 'REI', 'label' => 'Reincubar', 'outcome' => 'reincubate'],
        ]));

        return [
            [
                'study' => ['SEC', 'Cultivo de secreción y herida'],
                'types' => [
                    ['code' => 'HER', 'name' => 'Herida / úlcera (hisopado)', 'inoculum' => 'Hisopo, siembra por agotamiento'],
                    ['code' => 'ABS', 'name' => 'Absceso (punción o aspirado)', 'inoculum' => 'Gota del material, siembra por agotamiento'],
                ],
                'media' => $aerobic,
                'schedule' => $readings([24, 48]),
                'reading_options' => $options('Sin desarrollo', 'Desarrollo de flora habitual de piel'),
                'count_categories' => self::SEMIQUANTITATIVE,
                'direct_exam' => $gram(),
            ],
            [
                'study' => ['LIQ', 'Cultivo de líquido de punción'],
                'types' => [
                    ['code' => 'LPL', 'name' => 'Líquido pleural', 'inoculum' => 'Gota del sedimento'],
                    ['code' => 'LAS', 'name' => 'Líquido ascítico', 'inoculum' => 'Gota del sedimento'],
                    ['code' => 'LAR', 'name' => 'Líquido articular', 'inoculum' => 'Gota del sedimento'],
                ],
                'media' => $aerobic,
                'schedule' => $readings([24, 48, 72]),
                'reading_options' => $options('Sin desarrollo'),
                'direct_exam' => $gram(),
            ],
            [
                'study' => ['LCR', 'Cultivo de LCR'],
                'types' => [
                    ['code' => 'LCR', 'name' => 'Líquido cefalorraquídeo', 'inoculum' => 'Gota del sedimento'],
                ],
                'media' => [
                    ['code' => 'AS', 'atmosphere' => Atmosphere::Co2],
                    ['code' => 'CHOC', 'atmosphere' => Atmosphere::Co2],
                ],
                'schedule' => $readings([24, 48, 72]),
                'reading_options' => $options('Sin desarrollo'),
                // Decisión del laboratorio (2026-11-01): el Gram de LCR con microorganismos es crítico.
                'direct_exam' => $gram(critical: true, urgent: true),
            ],
            [
                'study' => ['RESP', 'Cultivo de secreción respiratoria'],
                'types' => [
                    [
                        'code' => 'ESP', 'name' => 'Esputo', 'inoculum' => 'Porción purulenta, siembra por agotamiento',
                        'count_categories' => self::SEMIQUANTITATIVE,
                    ],
                    [
                        'code' => 'ATQ', 'name' => 'Aspirado traqueal', 'inoculum' => 'Asa calibrada 10 µL (muestra homogeneizada)',
                        'count_categories' => [
                            ['code' => 'LT5', 'label' => '< 10⁵ UFC/mL'],
                            ['code' => '5-6', 'label' => '10⁵–10⁶ UFC/mL'],
                            ['code' => 'GE6', 'label' => '≥ 10⁶ UFC/mL'],
                        ],
                    ],
                    [
                        'code' => 'BAL', 'name' => 'Lavado broncoalveolar', 'inoculum' => 'Asa calibrada 1 µL',
                        'count_categories' => [
                            ['code' => 'LT3', 'label' => '< 10³ UFC/mL'],
                            ['code' => '3-4', 'label' => '10³–10⁴ UFC/mL'],
                            ['code' => 'GE4', 'label' => '≥ 10⁴ UFC/mL'],
                        ],
                    ],
                ],
                'media' => $aerobic,
                'schedule' => $readings([24, 48]),
                'reading_options' => $options('Sin desarrollo', 'Desarrollo de flora habitual de vías respiratorias superiores'),
                'direct_exam' => [
                    'label' => 'Examen directo (Gram)',
                    'fields' => [
                        ['code' => 'EPI', 'label' => 'Células epiteliales por campo (100×)', 'options' => [
                            ['code' => 'LT10', 'label' => 'Menos de 10'],
                            ['code' => '10-25', 'label' => 'Entre 10 y 25'],
                            ['code' => 'GT25', 'label' => 'Más de 25'],
                        ]],
                        ['code' => 'LEU', 'label' => 'Leucocitos por campo (100×)', 'options' => [
                            ['code' => 'LT10', 'label' => 'Menos de 10'],
                            ['code' => '10-25', 'label' => 'Entre 10 y 25'],
                            ['code' => 'GT25', 'label' => 'Más de 25'],
                        ]],
                        ['code' => 'MO', 'label' => 'Microorganismos', 'multiple' => true, 'options' => self::GRAM_ORGANISMS],
                    ],
                    'critical_if_organisms' => false,
                    'urgent' => false,
                ],
            ],
            [
                'study' => ['COPRO', 'Coprocultivo'],
                'types' => [
                    ['code' => 'MF', 'name' => 'Materia fecal', 'inoculum' => 'Hisopo cargado, siembra por agotamiento'],
                ],
                'media' => [
                    ['code' => 'MAC', 'atmosphere' => Atmosphere::Aerobic],
                    ['code' => 'SS', 'atmosphere' => Atmosphere::Aerobic],
                    ['code' => 'XLD', 'atmosphere' => Atmosphere::Aerobic],
                    // Se lee a partir de la segunda lectura (revisión de marchas A3; a validar).
                    ['code' => 'CAMPY', 'atmosphere' => Atmosphere::Microaerophilic, 'temperature' => '42 ± 1 °C', 'from' => 42],
                    ['code' => 'SEL', 'atmosphere' => Atmosphere::Aerobic, 'enrichment' => [
                        'min_hours' => 18,
                        'due_hours' => 24,
                        'media' => [
                            ['medium_code' => 'SS', 'incubation_temperature' => '35 ± 2 °C', 'atmosphere' => Atmosphere::Aerobic->value],
                            ['medium_code' => 'XLD', 'incubation_temperature' => '35 ± 2 °C', 'atmosphere' => Atmosphere::Aerobic->value],
                        ],
                    ]],
                ],
                'schedule' => $readings([24, 48]),
                'reading_options' => [
                    ['code' => 'NEG', 'label' => 'No se aislaron Salmonella, Shigella ni Campylobacter', 'outcome' => 'no_growth'],
                    ['code' => 'POS', 'label' => 'Desarrollo de enteropatógeno', 'outcome' => 'growth'],
                    ['code' => 'REI', 'label' => 'Reincubar', 'outcome' => 'reincubate'],
                ],
                'direct_exam' => [
                    'label' => 'Examen directo (leucocitos fecales)',
                    'fields' => [
                        ['code' => 'LEU', 'label' => 'Leucocitos', 'options' => self::AMOUNT],
                        ['code' => 'HEM', 'label' => 'Hematíes', 'options' => self::AMOUNT],
                    ],
                    'critical_if_organisms' => false,
                    'urgent' => false,
                ],
            ],
            [
                'study' => ['GEN', 'Cultivo de secreción genital'],
                'types' => [
                    [
                        'code' => 'FV', 'name' => 'Flujo vaginal', 'inoculum' => 'Hisopo, siembra por agotamiento',
                        'media' => [
                            ['code' => 'AS', 'atmosphere' => Atmosphere::Co2],
                            ['code' => 'SAB', 'atmosphere' => Atmosphere::Aerobic],
                        ],
                        'schedule' => $readings([24, 48]),
                        'direct_exam' => [
                            'label' => 'Examen directo (fresco y Gram)',
                            'fields' => [
                                ['code' => 'PH', 'label' => 'pH', 'type' => 'number', 'unit' => null, 'options' => []],
                                ['code' => 'AMI', 'label' => 'Test de aminas (KOH)', 'options' => [
                                    ['code' => 'NEG', 'label' => 'Negativo'],
                                    ['code' => 'POS', 'label' => 'Positivo'],
                                ]],
                                ['code' => 'NUG', 'label' => 'Score de Nugent', 'type' => 'number', 'unit' => null, 'options' => []],
                                ['code' => 'LEU', 'label' => 'Leucocitos', 'options' => self::AMOUNT],
                                ['code' => 'CG', 'label' => 'Células guía (clue cells)', 'options' => self::PRESENCE],
                                ['code' => 'LEV', 'label' => 'Levaduras / pseudohifas', 'options' => [
                                    ['code' => 'NO', 'label' => 'No se observan'],
                                    ['code' => 'SI', 'label' => 'Se observan', 'organisms' => true],
                                ]],
                                ['code' => 'TRI', 'label' => 'Trichomonas', 'options' => [
                                    ['code' => 'NO', 'label' => 'No se observan'],
                                    ['code' => 'SI', 'label' => 'Se observan', 'organisms' => true],
                                ]],
                                ['code' => 'FLO', 'label' => 'Microbiota', 'options' => [
                                    ['code' => 'LAC', 'label' => 'Predominio de lactobacilos'],
                                    ['code' => 'DIS', 'label' => 'Lactobacilos disminuidos'],
                                    ['code' => 'AUS', 'label' => 'Lactobacilos ausentes, flora mixta'],
                                ]],
                            ],
                            'critical_if_organisms' => false,
                            'urgent' => false,
                        ],
                    ],
                    ['code' => 'URE', 'name' => 'Secreción uretral', 'inoculum' => 'Hisopo, siembra por agotamiento'],
                    ['code' => 'ENC', 'name' => 'Secreción endocervical', 'inoculum' => 'Hisopo, siembra por agotamiento'],
                ],
                'media' => [
                    ['code' => 'AS', 'atmosphere' => Atmosphere::Co2],
                    ['code' => 'CHOC', 'atmosphere' => Atmosphere::Co2],
                    ['code' => 'TM', 'atmosphere' => Atmosphere::Co2],
                ],
                'schedule' => $readings([24, 48, 72]),
                'reading_options' => $options('Sin desarrollo', 'Desarrollo de flora habitual'),
                'direct_exam' => $gram(),
            ],
            [
                'study' => ['FAU', 'Exudado de fauces'],
                'types' => [
                    ['code' => 'HFA', 'name' => 'Hisopado de fauces', 'inoculum' => 'Hisopo, siembra por agotamiento'],
                ],
                'media' => [
                    ['code' => 'AS', 'atmosphere' => Atmosphere::Co2],
                ],
                'schedule' => $readings([24, 48]),
                'reading_options' => [
                    ['code' => 'NEG', 'label' => 'No se aisló Streptococcus beta hemolítico del grupo A', 'outcome' => 'no_growth'],
                    ['code' => 'POS', 'label' => 'Desarrollo de colonias beta hemolíticas', 'outcome' => 'growth'],
                    ['code' => 'REI', 'label' => 'Reincubar', 'outcome' => 'reincubate'],
                ],
                'direct_exam' => null,
            ],
        ];
    }
}
