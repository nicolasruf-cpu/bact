<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\IdentificationTest;
use Illuminate\Database\Seeder;

/**
 * Catálogo de referencia de pruebas de identificación (revisión de marchas B1, B2, B5):
 * Gram de la colonia, pruebas rápidas, bioquímicas, complementarias y de mecanismo, con sus
 * resultados posibles. Es una PLANTILLA a validar por el laboratorio (D-0008): las horas de
 * incubación son las de uso habitual y el Director técnico las ajusta. No incluye reglas de
 * interpretación. Idempotente.
 */
class IdentificationTestSeeder extends Seeder
{
    private const POS_NEG = [['code' => 'POS', 'label' => 'Positiva'], ['code' => 'NEG', 'label' => 'Negativa']];

    private const SENS = [['code' => 'S', 'label' => 'Sensible'], ['code' => 'R', 'label' => 'Resistente']];

    public function run(): void
    {
        foreach ($this->tests() as [$code, $name, $kind, $options, $hours, $reportable]) {
            IdentificationTest::firstOrCreate(['code' => $code], [
                'name' => $name,
                'kind' => $kind,
                'result_options' => $options,
                'incubation_hours' => $hours,
                'is_reportable' => $reportable,
                'is_reference_template' => true,
            ]);
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: IdentificationTestKind, 3: list<array{code: string, label: string}>, 4: int|null, 5: bool}>
     */
    private function tests(): array
    {
        $gram = IdentificationTestKind::Gram;
        $rapid = IdentificationTestKind::Rapid;
        $bio = IdentificationTestKind::Biochemical;
        $comp = IdentificationTestKind::Complementary;
        $mech = IdentificationTestKind::Mechanism;

        return [
            ['GRAM', 'Gram de la colonia', $gram, [
                ['code' => 'CGP-RAC', 'label' => 'Cocos gram positivos en racimos'],
                ['code' => 'CGP-CAD', 'label' => 'Cocos gram positivos en cadenas'],
                ['code' => 'CGP-PAR', 'label' => 'Cocos gram positivos en pares'],
                ['code' => 'DGN', 'label' => 'Diplococos gram negativos'],
                ['code' => 'CBGN', 'label' => 'Cocobacilos gram negativos'],
                ['code' => 'BGN', 'label' => 'Bacilos gram negativos'],
                ['code' => 'BGP', 'label' => 'Bacilos gram positivos'],
                ['code' => 'LEV', 'label' => 'Levaduras'],
            ], null, false],
            ['CAT', 'Catalasa', $rapid, self::POS_NEG, null, false],
            ['OXI', 'Oxidasa', $rapid, self::POS_NEG, null, false],
            ['COAG-L', 'Coagulasa (látex o portaobjetos)', $rapid, self::POS_NEG, null, false],
            ['COAG-T', 'Coagulasa en tubo', $rapid, self::POS_NEG, 4, false],
            ['PYR', 'PYR', $rapid, self::POS_NEG, null, false],
            ['IND-G', 'Indol en gota', $rapid, self::POS_NEG, null, false],
            ['OPT', 'Optoquina', $rapid, self::SENS, 18, false],
            ['BAC', 'Bacitracina', $rapid, self::SENS, 18, false],
            ['LAT-STR', 'Látex de grupo estreptocócico', $rapid, [
                ['code' => 'A', 'label' => 'Grupo A'],
                ['code' => 'B', 'label' => 'Grupo B'],
                ['code' => 'C', 'label' => 'Grupo C'],
                ['code' => 'F', 'label' => 'Grupo F'],
                ['code' => 'G', 'label' => 'Grupo G'],
                ['code' => 'NEG', 'label' => 'No aglutina'],
            ], null, false],
            ['TG', 'Tubo germinativo', $rapid, self::POS_NEG, 3, false],
            ['TSI', 'TSI', $bio, [
                ['code' => 'KA', 'label' => 'K/A'],
                ['code' => 'AA', 'label' => 'A/A'],
                ['code' => 'KK', 'label' => 'K/K'],
                ['code' => 'KAH', 'label' => 'K/A, SH₂ +'],
                ['code' => 'AAH', 'label' => 'A/A, SH₂ +'],
                ['code' => 'KAG', 'label' => 'K/A, gas +'],
                ['code' => 'AAG', 'label' => 'A/A, gas +'],
            ], 18, false],
            ['LIA', 'LIA', $bio, [
                ['code' => 'KK', 'label' => 'K/K (lisina decarboxilasa +)'],
                ['code' => 'KA', 'label' => 'K/A (lisina decarboxilasa −)'],
                ['code' => 'RA', 'label' => 'R/A (desaminasa +)'],
                ['code' => 'KKH', 'label' => 'K/K, SH₂ +'],
            ], 18, false],
            ['IND', 'Indol (SIM)', $bio, self::POS_NEG, 18, false],
            ['MOT', 'Movilidad (SIM)', $bio, self::POS_NEG, 18, false],
            ['CIT', 'Citrato de Simmons', $bio, self::POS_NEG, 18, false],
            ['URE', 'Urea', $bio, self::POS_NEG, 18, false],
            ['BE', 'Bilis esculina', $bio, self::POS_NEG, 18, false],
            ['NACL', 'Desarrollo en NaCl 6,5 %', $bio, self::POS_NEG, 18, false],
            ['CAMP', 'Prueba de CAMP', $bio, self::POS_NEG, 18, false],
            ['SAL-O', 'Serología de Salmonella (antígeno O)', $comp, [
                ['code' => 'O4', 'label' => 'O:4 (grupo B)'],
                ['code' => 'O7', 'label' => 'O:6,7 (grupo C1)'],
                ['code' => 'O9', 'label' => 'O:9 (grupo D)'],
                ['code' => 'O3', 'label' => 'O:3,10 (grupo E)'],
                ['code' => 'NEG', 'label' => 'No aglutina con los antisueros disponibles'],
            ], null, true],
            ['BLEE', 'Sinergia de doble disco (BLEE)', $mech, [
                ['code' => 'POS', 'label' => 'Positiva: productor de BLEE'],
                ['code' => 'NEG', 'label' => 'Negativa'],
            ], 18, true],
            ['CARBA-IC', 'Carbapenemasa (inmunocromatografía)', $mech, [
                ['code' => 'NEG', 'label' => 'No se detecta'],
                ['code' => 'KPC', 'label' => 'KPC'],
                ['code' => 'NDM', 'label' => 'NDM'],
                ['code' => 'OXA48', 'label' => 'OXA-48'],
                ['code' => 'VIM', 'label' => 'VIM'],
                ['code' => 'IMP', 'label' => 'IMP'],
            ], null, true],
            ['MCIM', 'mCIM (carbapenemasa)', $mech, [
                ['code' => 'POS', 'label' => 'Positivo: productor de carbapenemasa'],
                ['code' => 'NEG', 'label' => 'Negativo'],
            ], 18, true],
            ['DTEST', 'Test D (resistencia inducible a clindamicina)', $mech, self::POS_NEG, 18, true],
            ['NITRO', 'Nitrocefina (β-lactamasa)', $mech, self::POS_NEG, null, true],
        ];
    }
}
