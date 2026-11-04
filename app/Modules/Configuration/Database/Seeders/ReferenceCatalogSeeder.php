<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\SampleType;
use Illuminate\Database\Seeder;

/**
 * Catálogo de referencia: organismos frecuentes en urocultivo, antibióticos y paneles
 * de la plantilla (docs/templates/UROCULTIVO.md §7, D-0008: a validar por el laboratorio).
 * No incluye puntos de corte. Idempotente.
 */
class ReferenceCatalogSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: OrganismGroup}> */
    private const ORGANISMS = [
        ['ECOL', 'Escherichia coli', OrganismGroup::Enterobacterales],
        ['KPNE', 'Klebsiella pneumoniae', OrganismGroup::Enterobacterales],
        ['KOXY', 'Klebsiella oxytoca', OrganismGroup::Enterobacterales],
        ['PMIR', 'Proteus mirabilis', OrganismGroup::Enterobacterales],
        ['ECLO', 'Enterobacter cloacae complex', OrganismGroup::Enterobacterales],
        ['CFRE', 'Citrobacter freundii', OrganismGroup::Enterobacterales],
        ['MMOR', 'Morganella morganii', OrganismGroup::Enterobacterales],
        ['SMAR', 'Serratia marcescens', OrganismGroup::Enterobacterales],
        ['PAER', 'Pseudomonas aeruginosa', OrganismGroup::Pseudomonas],
        ['ABAU', 'Acinetobacter baumannii complex', OrganismGroup::Acinetobacter],
        ['EFAE', 'Enterococcus faecalis', OrganismGroup::Enterococcus],
        ['EFAM', 'Enterococcus faecium', OrganismGroup::Enterococcus],
        ['SSAP', 'Staphylococcus saprophyticus', OrganismGroup::Staphylococcus],
        ['SAUR', 'Staphylococcus aureus', OrganismGroup::Staphylococcus],
        ['SAGA', 'Streptococcus agalactiae', OrganismGroup::Streptococcus],
        ['CALB', 'Candida albicans', OrganismGroup::Yeast],
        // Agregados para los casos publicados de la demo (2026-11-01).
        ['SEPI', 'Staphylococcus epidermidis', OrganismGroup::Staphylococcus],
        ['SPNE', 'Streptococcus pneumoniae', OrganismGroup::Streptococcus],
        ['SPYO', 'Streptococcus pyogenes', OrganismGroup::Streptococcus],
        ['SENT', 'Salmonella enterica', OrganismGroup::Enterobacterales],
        ['NMEN', 'Neisseria meningitidis', OrganismGroup::Other],
        ['NGON', 'Neisseria gonorrhoeae', OrganismGroup::Other],
        ['CJEJ', 'Campylobacter jejuni', OrganismGroup::Other],
    ];

    /** @var array<string, string> */
    private const ANTIBIOTICS = [
        'AMP' => 'Ampicilina',
        'AMC' => 'Amoxicilina-clavulánico',
        'CEF' => 'Cefalotina',
        'CXM' => 'Cefuroxima',
        'CRO' => 'Ceftriaxona',
        'CAZ' => 'Ceftazidima',
        'FEP' => 'Cefepime',
        'TZP' => 'Piperacilina-tazobactam',
        'IPM' => 'Imipenem',
        'MEM' => 'Meropenem',
        'GEN' => 'Gentamicina',
        'AMK' => 'Amikacina',
        'CIP' => 'Ciprofloxacina',
        'SXT' => 'Trimetoprima-sulfametoxazol',
        'NIT' => 'Nitrofurantoína',
        'FOS' => 'Fosfomicina',
        'VAN' => 'Vancomicina',
        'OXA' => 'Oxacilina (cefoxitina)',
        // Agregados en la revisión de marchas (D): con la carga del disco y, si es prueba
        // subrogante, el antibiótico que se informa.
        'FOX' => 'Cefoxitina',
        'PEN' => 'Penicilina',
        'ERI' => 'Eritromicina',
        'CLI' => 'Clindamicina',
        'LZD' => 'Linezolid',
        'GEH' => 'Gentamicina',
    ];

    /** @var array<string, array{0: string|null, 1: string|null}> carga del disco y código del antibiótico que se informa */
    private const DISK = [
        'FOX' => ['30 µg', 'OXA'],
        'PEN' => ['10 U', null],
        'ERI' => ['15 µg', null],
        'CLI' => ['2 µg', null],
        'LZD' => ['30 µg', null],
        'GEH' => ['120 µg', null],
    ];

    /** Muestras no urinarias de las plantillas: los paneles generales valen para ellas. */
    private const NON_URINE = ['SAN-PER', 'SAN-CAT', 'HER', 'ABS', 'LPL', 'LAS', 'LAR', 'LCR', 'ESP', 'ATQ', 'BAL'];

    /**
     * Paneles para muestras no urinarias y VITEK (revisión de marchas D). Plantilla a validar:
     * el laboratorio carga sus paneles reales (D-0021). Se crean solo si existen las muestras.
     *
     * @var list<array{0: string, 1: string, 2: list<OrganismGroup>, 3: list<string>, 4: list<string>|null, 5: bool}>
     */
    private const SAMPLE_PANELS = [
        ['GRAL-ENT', 'Enterobacterales — sangre, heridas y líquidos', [OrganismGroup::Enterobacterales],
            ['AMP', 'AMC', 'CXM', 'CRO', 'CAZ', 'FEP', 'TZP', 'IPM', 'MEM', 'GEN', 'AMK', 'CIP', 'SXT'], self::NON_URINE, false],
        ['COPRO-ENT', 'Enterobacterales — coprocultivo', [OrganismGroup::Enterobacterales],
            ['AMP', 'CIP', 'SXT', 'CRO'], ['MF'], false],
        ['GRAL-STA', 'Staphylococcus — sangre, heridas y líquidos', [OrganismGroup::Staphylococcus],
            ['FOX', 'PEN', 'ERI', 'CLI', 'SXT', 'GEN', 'CIP', 'LZD', 'VAN'], self::NON_URINE, false],
        ['VTK-REF-GN', 'VITEK — gram negativos', [OrganismGroup::Enterobacterales, OrganismGroup::Pseudomonas, OrganismGroup::Acinetobacter],
            ['AMP', 'AMC', 'CXM', 'CRO', 'CAZ', 'FEP', 'TZP', 'IPM', 'MEM', 'GEN', 'AMK', 'CIP', 'SXT', 'NIT'], null, true],
    ];

    /** @var list<array{0: string, 1: string, 2: list<OrganismGroup>, 3: list<string>}> */
    private const PANELS = [
        ['URO-ENT', 'Enterobacterales — orina', [OrganismGroup::Enterobacterales],
            ['AMP', 'AMC', 'CEF', 'CXM', 'CRO', 'CAZ', 'FEP', 'IPM', 'GEN', 'AMK', 'CIP', 'SXT', 'NIT', 'FOS']],
        ['URO-PSA', 'Pseudomonas aeruginosa', [OrganismGroup::Pseudomonas],
            ['CAZ', 'FEP', 'TZP', 'IPM', 'MEM', 'GEN', 'AMK', 'CIP']],
        ['URO-ENC', 'Enterococcus — orina', [OrganismGroup::Enterococcus],
            ['AMP', 'VAN', 'NIT', 'CIP', 'FOS']],
        ['URO-STA', 'Staphylococcus — orina', [OrganismGroup::Staphylococcus],
            ['OXA', 'SXT', 'NIT', 'CIP', 'VAN']],
    ];

    public function run(): void
    {
        foreach (self::ORGANISMS as [$code, $name, $group]) {
            Organism::firstOrCreate(['code' => $code], ['name' => $name, 'organism_group' => $group]);
        }

        /** @var array<string, Antibiotic> $antibiotics */
        $antibiotics = [];
        foreach (self::ANTIBIOTICS as $code => $name) {
            $antibiotics[$code] = Antibiotic::firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach (self::DISK as $code => [$disk, $reportedAs]) {
            $antibiotic = $antibiotics[$code];
            if ($antibiotic->disk_content === null) {
                $antibiotic->update([
                    'disk_content' => $disk,
                    'reported_as_antibiotic_id' => $reportedAs === null ? null : $antibiotics[$reportedAs]->id,
                ]);
            }
        }

        // Sin muestras asociadas: valen para todas, como antes de D-0021.
        foreach (self::PANELS as [$code, $name, $groups, $codes]) {
            $panel = AstPanel::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'organism_groups' => $groups, 'is_reference_template' => true],
            );

            if ($panel->antibiotics()->exists()) {
                continue;
            }

            foreach ($codes as $position => $antibioticCode) {
                $panel->antibiotics()->attach($antibiotics[$antibioticCode]->id, ['position' => $position + 1]);
            }
        }

        foreach (self::SAMPLE_PANELS as [$code, $name, $groups, $codes, $sampleCodes, $vitek]) {
            if (AstPanel::query()->where('code', $code)->exists()) {
                continue;
            }
            $sampleTypeIds = $sampleCodes === null ? [] : SampleType::query()->whereIn('code', $sampleCodes)->pluck('id')->all();
            // Sin las muestras cargadas, un panel general valdría también para orina: se crea después.
            if ($sampleCodes !== null && $sampleTypeIds === []) {
                continue;
            }

            $panel = AstPanel::create([
                'code' => $code,
                'name' => $name,
                'organism_groups' => $groups,
                'is_vitek' => $vitek,
                // El VITEK avisa cuando termina: no se espera una incubación fija.
                'read_min_hours' => $vitek ? 0 : null,
                'is_reference_template' => true,
            ]);
            $panel->sampleTypes()->sync($sampleTypeIds);
            foreach ($codes as $position => $antibioticCode) {
                $panel->antibiotics()->attach($antibiotics[$antibioticCode]->id, ['position' => $position + 1]);
            }
        }

        $this->call(IdentificationTestSeeder::class);
    }
}
