<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
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
    ];

    /** @var list<array{0: string, 1: string, 2: OrganismGroup, 3: list<string>}> */
    private const PANELS = [
        ['URO-ENT', 'Enterobacterales — orina', OrganismGroup::Enterobacterales,
            ['AMP', 'AMC', 'CEF', 'CXM', 'CRO', 'CAZ', 'FEP', 'IPM', 'GEN', 'AMK', 'CIP', 'SXT', 'NIT', 'FOS']],
        ['URO-PSA', 'Pseudomonas aeruginosa', OrganismGroup::Pseudomonas,
            ['CAZ', 'FEP', 'TZP', 'IPM', 'MEM', 'GEN', 'AMK', 'CIP']],
        ['URO-ENC', 'Enterococcus — orina', OrganismGroup::Enterococcus,
            ['AMP', 'VAN', 'NIT', 'CIP', 'FOS']],
        ['URO-STA', 'Staphylococcus — orina', OrganismGroup::Staphylococcus,
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

        foreach (self::PANELS as [$code, $name, $group, $codes]) {
            $panel = AstPanel::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'organism_group' => $group, 'is_reference_template' => true],
            );

            if ($panel->antibiotics()->exists()) {
                continue;
            }

            foreach ($codes as $position => $antibioticCode) {
                $panel->antibiotics()->attach($antibiotics[$antibioticCode]->id, ['position' => $position + 1]);
            }
        }
    }
}
