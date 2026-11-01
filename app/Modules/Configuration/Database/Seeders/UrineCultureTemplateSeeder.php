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
 * Plantilla de referencia de urocultivo (docs/templates/UROCULTIVO.md, D-0008).
 * Por defecto se carga como BORRADOR: no se usa con pacientes hasta que la apruebe
 * el Director técnico responsable. Solo el DemoSeeder la carga vigente.
 * Es idempotente: no duplica ni pisa versiones existentes.
 */
class UrineCultureTemplateSeeder extends Seeder
{
    public const SOURCE = 'PLANTILLA DE REFERENCIA — requiere validación del laboratorio. '
        .'Basada en: Clinical Microbiology Procedures Handbook (ASM), "Urine Cultures"; '
        .'IDSA/ASM Guide to Utilization of the Microbiology Laboratory (2024). Ver docs/templates/UROCULTIVO.md.';

    public function run(ProtocolVersionStatus $status = ProtocolVersionStatus::Draft): void
    {
        $study = Study::firstOrCreate(['code' => 'URO'], ['name' => 'Urocultivo']);

        $bloodAgar = Medium::firstOrCreate(['code' => 'AS'], ['name' => 'Agar sangre']);
        $macConkey = Medium::firstOrCreate(['code' => 'MAC'], ['name' => 'Agar MacConkey']);

        $types = [
            ['code' => 'URI-CM', 'name' => 'Orina — chorro medio', 'inoculum' => 'Asa calibrada 1 µL'],
            ['code' => 'URI-SV', 'name' => 'Orina — sonda vesical', 'inoculum' => 'Asa calibrada 10 µL'],
        ];

        foreach ($types as $type) {
            $sampleType = SampleType::firstOrCreate(['code' => $type['code']], ['name' => $type['name']]);

            $protocol = Protocol::firstOrCreate(
                ['study_id' => $study->id, 'sample_type_id' => $sampleType->id],
                ['name' => "Urocultivo — {$sampleType->name}"],
            );

            if ($protocol->versions()->exists()) {
                continue;
            }

            /** @var ProtocolVersion $version */
            $version = $protocol->versions()->create([
                'version' => 1,
                'status' => $status,
                'is_reference_template' => true,
                'source' => self::SOURCE,
                'first_read_min_hours' => 18,
                'first_read_due_hours' => 24,
                'reincubation_hours' => 24,
                'max_reincubations' => 1,
                'allow_bulk_negative_confirmation' => false,
                'activated_at' => $status === ProtocolVersionStatus::Active ? Date::now() : null,
            ]);

            foreach ([$bloodAgar, $macConkey] as $position => $medium) {
                $version->media()->create([
                    'medium_id' => $medium->id,
                    'position' => $position + 1,
                    'inoculum' => $type['inoculum'],
                    'incubation_temperature' => '35 ± 2 °C',
                    'atmosphere' => Atmosphere::Aerobic,
                ]);
            }
        }
    }
}
