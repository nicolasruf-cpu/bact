<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BloodCultures\Actions\LoadBottle;
use App\Modules\BloodCultures\Actions\RecordBottlePositive;
use App\Modules\BloodCultures\Actions\RecordGramStain;
use App\Modules\BloodCultures\Actions\SubcultureBottle;
use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\CriticalResults\Actions\RecordCriticalCommunication;
use App\Modules\CriticalResults\Data\CommunicationData;
use App\Modules\CriticalResults\Enums\CommunicationChannel;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordDirectExam;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Actions\SubcultureEnrichmentBroth;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Data\PatientData;
use App\Modules\Patients\Enums\Sex;
use App\Modules\Reporting\Actions\IssuePreliminaryReport;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Samples\Actions\ReceiveOrderSamples;
use App\Modules\Samples\Data\IncomingSampleData;
use App\Modules\Samples\Data\ReceptionData;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * SOLO DEMO: escenarios armados a partir de casos clínicos publicados (docs/demo/CASOS_PUBLICADOS.md).
 *
 * - Los pacientes son FICTICIOS: solo se toman la edad, el sexo y el cuadro de la publicación.
 * - Cada caso recorre el circuito real (acciones del sistema) con un reloj simulado,
 *   hasta la etapa indicada; algunos quedan a mitad de camino para que haya trabajo en las mesadas.
 * - No se inventan resultados: lo que la publicación no informa (p. ej., valores de antibiograma)
 *   queda pendiente y se anota en la ficha del caso.
 * - Es idempotente: un caso ya cargado (por su código de barras) no se repite.
 */
class PublishedCaseSeeder extends Seeder
{
    private Site $site;

    private User $biochemist;

    private User $assistant;

    private CarbonImmutable $start;

    /** @var list<string> */
    private array $failures = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los casos de demostración no se cargan en producción.');
        }

        $this->site = Site::query()->where('code', 'CEN')->firstOrFail();
        $this->biochemist = User::query()->where('email', 'bioquimica@bactruf.test')->firstOrFail();
        $this->assistant = User::query()->where('email', 'auxiliar@bactruf.test')->firstOrFail();
        $now = CarbonImmutable::now();

        $cases = [
            'PUB-01' => [72, fn () => $this->esblCystitis()],
            'PUB-02' => [60, fn () => $this->mssaBacteremia()],
            'PUB-03' => [70, fn () => $this->kpcBacteremia()],
            'PUB-04' => [30, fn () => $this->meningococcalMeningitis()],
            'PUB-05' => [50, fn () => $this->pneumococcalEmpyema()],
            'PUB-06' => [26, fn () => $this->septicArthritis()],
            'PUB-07' => [30, fn () => $this->balPneumonia()],
            'PUB-08' => [48, fn () => $this->salmonellaGastroenteritis()],
            'PUB-09' => [28, fn () => $this->campylobacterEnteritis()],
            'PUB-10' => [30, fn () => $this->gonococcalUrethritis()],
            'PUB-11' => [80, fn () => $this->streptococcalPharyngitis()],
            'PUB-12' => [2, fn () => $this->pendingReception()],
        ];

        foreach ($cases as $barcode => [$hoursAgo, $script]) {
            if (Sample::query()->where('barcode', $barcode)->exists()) {
                continue;
            }

            $this->start = $now->subHours($hoursAgo);
            try {
                $script();
                $this->command?->info("Caso {$barcode} cargado.");
            } catch (Throwable $e) {
                $this->failures[] = "{$barcode}: {$e->getMessage()}";
                $this->command?->error("Caso {$barcode}: {$e->getMessage()}");
            } finally {
                Date::setTestNow();
                Auth::forgetUser();
            }
        }

        Artisan::call('activities:release');

        if ($this->failures !== []) {
            throw new RuntimeException("Casos con errores:\n".implode("\n", $this->failures));
        }
    }

    // ---------------------------------------------------------------------------------
    // Casos (fuentes en docs/demo/CASOS_PUBLICADOS.md)
    // ---------------------------------------------------------------------------------

    /**
     * PUB-01 — Cistitis por E. coli productora de BLEE en una mujer joven.
     * DeBusscher J, et al. Emerg Infect Dis. 2009;15(11):1862-4.
     * Llega hasta el antibiograma armado: la publicación informa CIM solo para algunos
     * antibióticos, así que la lectura queda pendiente.
     */
    private function esblCystitis(): void
    {
        $study = $this->receive('PUB-01', 'URO', 'URI-CM', Sex::Female, 24, Priority::Routine,
            'Cistitis no complicada, piuria > 100 leucocitos/campo. Caso: DeBusscher et al., Emerg Infect Dis 2009.');
        $culture = $this->inoculate($study);

        $this->at(20);
        $this->read($culture, 'POS', [['Bacilo gram negativo, lactosa positivo', 'GE5', 'MAC', true]],
            'La publicación no informa el recuento: se carga ≥ 10⁵ UFC/mL como dato sintético.');

        $this->at(26);
        $isolate = $this->isolate($culture, 1);
        $this->identify($isolate, 'ECOL', IdentificationMethod::Biochemical, 'Identificación según la publicación (BLEE tipo CTX-M-15).', true);
        $this->setUpAst($isolate, AstMethod::Mic);
    }

    /**
     * PUB-02 — Bacteriemia por S. aureus meticilino sensible con foco en un orzuelo.
     * Staphylococcus aureus hordeolum as a cause of bacteremia. J Fam Pract. 1989;29(4).
     * Frasco positivo, Gram, crítico comunicado, preliminar, subcultivo e identificación.
     */
    private function mssaBacteremia(): void
    {
        $study = $this->receive('PUB-02', 'HEMO', 'SAN-PER', Sex::Female, 55, Priority::Urgent,
            'Fiebre y orzuelo. Caso: J Fam Pract 1989;29(4).');

        $bottle = $this->loadBottle($study);
        $this->at(14.5);
        $this->as($this->assistant);
        app(RecordBottlePositive::class)->handle($this->assistant, $bottle, Date::now()->subMinutes(20));

        $this->at(15);
        $this->as($this->biochemist);
        app(RecordGramStain::class)->handle($this->biochemist, $bottle->fresh() ?? $bottle, 'CGP-RAC');
        $this->communicate($study, 'Dra. Médica Ficticia', 'Médica de guardia');
        $this->preliminary($study);

        $this->at(15.5);
        $culture = app(SubcultureBottle::class)->handle($this->biochemist, $bottle->fresh() ?? $bottle);

        $this->at(34);
        $this->read($culture, 'POS', [['Colonias blancas cremosas, beta hemolíticas', null, null, true]]);
        $this->at(36);
        $isolate = $this->isolate($culture, 1);
        $this->identify($isolate, 'SAUR', IdentificationMethod::Biochemical, 'Catalasa +, coagulasa +. β-lactamasa + (publicación).', true);
        $this->setUpAst($isolate, AstMethod::Disk);
    }

    /**
     * PUB-03 — Bacteriemia por K. pneumoniae productora de KPC (paciente 3).
     * Goldfarb D, et al. J Clin Microbiol. 2009;47(6):1920-2.
     */
    private function kpcBacteremia(): void
    {
        $study = $this->receive('PUB-03', 'HEMO', 'SAN-PER', Sex::Female, 67, Priority::Urgent,
            'Hemocultivos repetidos positivos. Caso: Goldfarb et al., J Clin Microbiol 2009 (paciente 3).');

        $bottle = $this->loadBottle($study);
        $this->at(11);
        $this->as($this->assistant);
        app(RecordBottlePositive::class)->handle($this->assistant, $bottle, Date::now()->subMinutes(30));

        $this->at(11.5);
        $this->as($this->biochemist);
        app(RecordGramStain::class)->handle($this->biochemist, $bottle->fresh() ?? $bottle, 'BGN');
        $this->communicate($study, 'Dr. Médico Ficticio', 'Infectología');

        $this->at(12);
        $culture = app(SubcultureBottle::class)->handle($this->biochemist, $bottle->fresh() ?? $bottle);
        $this->at(31);
        $this->read($culture, 'POS', [['Colonias mucosas, lactosa positivas', null, null, true]]);
        $this->at(33);
        $isolate = $this->isolate($culture, 1);
        $this->identify($isolate, 'KPNE', IdentificationMethod::Vitek, 'Identificación según la publicación. Carbapenemasa KPC (PCR blaKPC en la publicación).', true);
        $this->setUpAst($isolate, AstMethod::Mic);
    }

    /**
     * PUB-04 — Meningitis meningocócica en un niño de 3 años.
     * CNS Case Studies — Part I, caso 1 (WebPath, Universidad de Tel Aviv).
     * Directo crítico comunicado, preliminar, desarrollo a las 24 h; siguen las lecturas.
     */
    private function meningococcalMeningitis(): void
    {
        $study = $this->receive('PUB-04', 'LCR', 'LCR', Sex::Male, 3, Priority::Stat,
            'LCR turbio, 5000 leucocitos/µL (99 % neutrófilos). Caso: WebPath CNS, caso 1.');

        $this->at(0.3);
        $this->direct($study, ['LEU' => ['ABU'], 'MO' => ['DGN']], 'Diplococos gram negativos intracelulares.');
        $this->communicate($study, 'Dr. Pediatra Ficticio', 'Guardia de pediatría');

        $this->at(0.5);
        $culture = $this->inoculate($study);
        $this->preliminary($study);

        $this->at(20);
        $this->read($culture, 'POS', [['Colonias grises, no hemolíticas, en agar chocolate', null, 'CHOC', true]]);
        $this->at(24);
        $this->identify($this->isolate($culture, 1), 'NMEN', IdentificationMethod::Biochemical,
            'Oxidasa +, diplococo gram negativo (publicación).', false,
            'No hay panel de antibiograma configurado para Neisseria (Q-C3).');
    }

    /**
     * PUB-05 — Empiema por S. pneumoniae serotipo 23F en un adulto.
     * Yovi I, et al. Acta Med Indones. 2025;57(4):503.
     */
    private function pneumococcalEmpyema(): void
    {
        $study = $this->receive('PUB-05', 'LIQ', 'LPL', Sex::Male, 61, Priority::Urgent,
            'Empiema en paciente con quimioterapia. Caso: Yovi et al., Acta Med Indones 2025.');

        $this->at(0.5);
        $this->direct($study, ['LEU' => ['ABU'], 'MO' => ['CGP-PAR']], null);
        $culture = $this->inoculate($study);
        $this->preliminary($study);

        $this->at(20);
        $this->read($culture, 'POS', [['Colonias alfa hemolíticas, umbilicadas', null, 'AS', true]]);
        $this->at(24);
        $this->identify($this->isolate($culture, 1), 'SPNE', IdentificationMethod::Biochemical,
            'Serotipo 23F informado en la publicación (no se registra en el sistema).', false,
            'No hay panel de antibiograma configurado para Streptococcus (Q-C3).');
        // Quedan las lecturas de 48 y 72 h programadas.
    }

    /**
     * PUB-06 — Artritis séptica de rodilla por S. aureus con Gram negativo.
     * Carta: falsos negativos del líquido sinovial. Reumatol Clin. 2016.
     * Queda en la mesada de identificación.
     */
    private function septicArthritis(): void
    {
        $study = $this->receive('PUB-06', 'LIQ', 'LAR', Sex::Male, 49, Priority::Urgent,
            'Monoartritis de rodilla. Caso: Reumatol Clin 2016 (carta, falsos negativos del líquido sinovial).');

        $this->at(0.5);
        $this->direct($study, ['LEU' => ['ESC'], 'MO' => ['NO']], 'Gram sin microorganismos (falso negativo según la publicación).');
        $culture = $this->inoculate($study);

        $this->at(22);
        $this->read($culture, 'POS', [['Colonias doradas, beta hemolíticas', null, 'AS', true]]);
    }

    /**
     * PUB-07 — Neumonía en UTI: BAL cuantitativo con S. aureus 10⁵ y P. aeruginosa 10² UFC/mL.
     * Kasprzycka K, et al. J Educ Health Sport. 2023;45(1):76-85.
     */
    private function balPneumonia(): void
    {
        $study = $this->receive('PUB-07', 'RESP', 'BAL', Sex::Unknown, 87, Priority::Urgent,
            'Sospecha de neumonía en UTI, miastenia gravis. Caso: Kasprzycka et al., JEHS 2023.');

        $this->at(1);
        $this->direct($study, ['EPI' => ['LT10'], 'LEU' => ['GT25'], 'MO' => ['CGP-RAC', 'BGN']], null);
        $culture = $this->inoculate($study);

        $this->at(20);
        $this->read($culture, 'POS', [
            ['Colonias doradas, beta hemolíticas', 'GE4', 'AS', true],
            ['Colonias planas, olor frutal, pigmento verde', 'LT3', 'MAC', false],
        ], 'S. aureus ≥ 10⁴ UFC/mL; P. aeruginosa 10² UFC/mL (no significativo según la publicación).');

        $this->at(24);
        $this->identify($this->isolate($culture, 1), 'SAUR', IdentificationMethod::Biochemical, 'Catalasa +, coagulasa +. SAMS (publicación).', true);
        $this->setUpAst($this->isolate($culture, 1), AstMethod::Disk);
    }

    /**
     * PUB-08 — Gastroenteritis por Salmonella Enteritidis con shock hipovolémico.
     * J Clin Diagn Res. 2012 (caso clínico).
     * Directo, repique del caldo selenito, desarrollo en XLD e identificación.
     */
    private function salmonellaGastroenteritis(): void
    {
        $study = $this->receive('PUB-08', 'COPRO', 'MF', Sex::Female, 60, Priority::Urgent,
            'Gastroenteritis aguda con shock hipovolémico (UTI). Caso: J Clin Diagn Res 2012.');

        $culture = $this->inoculate($study);

        // Placas primarias a las 18,5 h; después se repica el caldo (sus placas se leen en la lectura siguiente).
        $this->at(18.5);
        $this->read($culture, 'POS', [['Colonias lactosa negativas con centro negro (SH₂ +)', null, 'XLD', true]]);
        $this->at(19);
        $broth = $culture->plates()->where('is_enrichment', true)->firstOrFail();
        app(SubcultureEnrichmentBroth::class)->handle($this->biochemist, $broth);
        $this->at(26);
        $isolate = $this->isolate($culture, 1);
        $this->identify($isolate, 'SENT', IdentificationMethod::Biochemical, 'Serotipo Enteritidis según la publicación (no se registra el serotipo).', true);
        $this->setUpAst($isolate, AstMethod::Disk);
    }

    /**
     * PUB-09 — Enteritis por Campylobacter jejuni en una niña de 4 años.
     * Albahra S, McElvania TeKippe E. Lablogatory, 12/01/2016.
     */
    private function campylobacterEnteritis(): void
    {
        $study = $this->receive('PUB-09', 'COPRO', 'MF', Sex::Female, 4, Priority::Routine,
            'Diarrea con sangre después de un asado. Caso: Lablogatory 2016.');

        $this->at(0.5);
        $this->direct($study, ['LEU' => ['MOD']], 'Leucocitos fecales positivos (publicación).');
        $culture = $this->inoculate($study);

        $this->at(18.5);
        $this->read($culture, 'POS', [['Colonias grises, no hemolíticas, en medio para Campylobacter (42 °C)', null, 'CAMPY', true]]);
        $this->at(19);
        $broth = $culture->plates()->where('is_enrichment', true)->firstOrFail();
        app(SubcultureEnrichmentBroth::class)->handle($this->biochemist, $broth);
        $this->at(26);
        $this->identify($this->isolate($culture, 1), 'CJEJ', IdentificationMethod::Other,
            'MALDI-TOF (publicación). Bacilos gram negativos curvos.', false,
            'La publicación no informa antibiograma; no hay panel para Campylobacter (Q-C3).');
    }

    /**
     * PUB-10 — Uretritis gonocócica en un hombre de 29 años.
     * Petrovska Basovska B, et al. Acta Morphologica. 2021.
     */
    private function gonococcalUrethritis(): void
    {
        $study = $this->receive('PUB-10', 'GEN', 'URE', Sex::Male, 29, Priority::Routine,
            'Disuria y secreción purulenta. Caso: Petrovska Basovska et al., Acta Morphologica 2021.');

        $culture = $this->inoculate($study);
        // El directo queda pendiente: la publicación no informa el Gram.

        $this->at(22);
        $this->read($culture, 'POS', [['Colonias gris blanquecinas en Thayer-Martin', null, 'TM', true]]);
        $this->at(26);
        $this->identify($this->isolate($culture, 1), 'NGON', IdentificationMethod::Other,
            'Oxidasa +, catalasa +; confirmado por PCR (publicación). CIM: sensible a ceftriaxona y ciprofloxacina (publicación).', false,
            'No hay panel de antibiograma configurado para Neisseria (Q-C3).');
    }

    /**
     * PUB-11 — Faringitis por S. pyogenes en una lactante de 2 meses.
     * Sharif MR, et al. Jundishapur J Microbiol. 2016;9(5):e32630.
     * Circuito completo hasta el informe validado.
     */
    private function streptococcalPharyngitis(): void
    {
        $study = $this->receive('PUB-11', 'FAU', 'HFA', Sex::Female, 0, Priority::Routine,
            'Fiebre, exudado faríngeo, adenopatía submaxilar. Caso: Sharif et al., Jundishapur J Microbiol 2016.');

        $culture = $this->inoculate($study);

        $this->at(22);
        $this->read($culture, 'POS', [['Colonias pequeñas con beta hemólisis completa', null, 'AS', true]]);
        $this->at(26);
        $this->identify($this->isolate($culture, 1), 'SPYO', IdentificationMethod::Biochemical,
            'Catalasa −, oxidasa −, PYR + (publicación).', false,
            'La publicación no informa antibiograma.');

        $this->at(44);
        $this->read($culture, 'NEG', []);

        $this->at(46);
        $this->validate($study);
    }

    /**
     * PUB-12 — Recibida y sin sembrar: el escenario anterior a la mesada de siembra.
     * Mismo cuadro que PUB-01 (otra paciente ficticia).
     */
    private function pendingReception(): void
    {
        $this->receive('PUB-12', 'URO', 'URI-CM', Sex::Female, 31, Priority::Routine,
            'Síntomas urinarios. Escenario basado en DeBusscher et al., Emerg Infect Dis 2009.');
    }

    // ---------------------------------------------------------------------------------
    // Pasos
    // ---------------------------------------------------------------------------------

    private function at(float $hours): void
    {
        Date::setTestNow($this->start->addMinutes((int) round($hours * 60)));
        Artisan::call('activities:release');
    }

    private function as(User $user): void
    {
        Auth::setUser($user);
        setPermissionsTeamId($this->site->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function receive(string $barcode, string $study, string $sampleType, Sex $sex, int $age, Priority $priority, string $notes): SampleStudy
    {
        $this->at(0);
        $this->as($this->assistant);

        $number = (int) substr($barcode, 4);
        app(ReceiveOrderSamples::class)->handle($this->assistant, $this->site, new ReceptionData(
            idempotencyKey: (string) Str::uuid(),
            orderNumber: null,
            patient: new PatientData(
                documentNumber: sprintf('99%06d', $number),
                lastName: 'Caso publicado '.substr($barcode, 4),
                firstName: 'Paciente ficticio',
                birthDate: CarbonImmutable::now()->subYears($age)->subMonths($age === 0 ? 2 : 0)->startOfDay(),
                sex: $sex,
            ),
            requestingService: 'Demo — casos publicados',
            requestingPhysician: null,
            priority: $priority,
            samples: [new IncomingSampleData(
                barcode: $barcode,
                sampleTypeId: (int) SampleType::query()->where('code', $sampleType)->value('id'),
                studyIds: [(int) Study::query()->where('code', $study)->value('id')],
                collectedAt: CarbonImmutable::now()->subMinutes(30),
                notes: 'Escenario basado en un caso publicado; paciente ficticio. '.$notes,
            )],
        ));

        $this->as($this->biochemist);

        return SampleStudy::query()->whereHas('sample', fn ($q) => $q->where('barcode', $barcode))->firstOrFail();
    }

    private function inoculate(SampleStudy $study): Culture
    {
        return app(InoculateSampleStudy::class)->handle($this->biochemist, $study->fresh() ?? $study);
    }

    private function loadBottle(SampleStudy $study): BloodCultureBottle
    {
        $this->as($this->assistant);
        $bottle = app(LoadBottle::class)->handle($this->assistant, $study, BottleMethod::Automated, 'AER', 'BacT/ALERT', 'A-'.$study->id);
        $this->as($this->biochemist);

        return $bottle;
    }

    /**
     * @param  array<string, list<string>>  $results
     */
    private function direct(SampleStudy $study, array $results, ?string $description): void
    {
        app(RecordDirectExam::class)->handle($this->biochemist, $study->fresh() ?? $study, $results, $description);
    }

    private function communicate(SampleStudy $study, string $recipient, string $role): void
    {
        $alert = CriticalAlert::query()->where('sample_study_id', $study->id)
            ->where('status', '!=', CriticalAlertStatus::Communicated->value)->latest('id')->firstOrFail();

        app(RecordCriticalCommunication::class)->handle($this->biochemist, $alert, new CommunicationData(
            idempotencyKey: (string) Str::uuid(),
            recipientName: $recipient,
            recipientRole: $role,
            channel: CommunicationChannel::Phone,
            successful: true,
            readBackConfirmed: true,
            notes: 'Comunicación de demostración (datos ficticios).',
        ));
    }

    private function preliminary(SampleStudy $study): void
    {
        app(IssuePreliminaryReport::class)->handle($this->biochemist, $study->fresh() ?? $study);
    }

    /**
     * @param  list<array{0: string, 1: string|null, 2: string|null, 3: bool}>  $isolates  morfotipo, recuento, medio, significativo
     */
    private function read(Culture $culture, string $option, array $isolates, ?string $notes = null): void
    {
        $culture = $culture->fresh() ?? $culture;
        $data = array_map(fn (array $i) => new IsolateData(
            morphotype: $i[0],
            countCategory: $i[1],
            plateId: $i[2] === null ? null : $this->plate($culture, $i[2])->id,
            isSignificant: $i[3],
        ), $isolates);

        app(RecordReading::class)->handle($this->biochemist, $culture, $option, $notes, $data);
    }

    private function plate(Culture $culture, string $mediumCode): Plate
    {
        $mediumId = Medium::query()->where('code', $mediumCode)->value('id');

        return $culture->plates()->where('medium_id', $mediumId)->orderByDesc('id')->firstOrFail();
    }

    private function isolate(Culture $culture, int $number): Isolate
    {
        return Isolate::query()->where('culture_id', $culture->id)->where('number', $number)->firstOrFail();
    }

    private function identify(Isolate $isolate, string $organism, IdentificationMethod $method, string $tests, bool $ast, ?string $noAstReason = null): void
    {
        app(IdentifyIsolate::class)->handle(
            $this->biochemist,
            $isolate->fresh() ?? $isolate,
            (int) Organism::query()->where('code', $organism)->value('id'),
            $method,
            IdentificationLevel::Definitive,
            $tests,
            null,
            $ast,
            $ast ? null : $noAstReason,
        );
    }

    private function setUpAst(Isolate $isolate, AstMethod $method): void
    {
        app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate->fresh() ?? $isolate, $method);
    }

    private function validate(SampleStudy $study): void
    {
        app(ValidateReport::class)->handle($this->biochemist, $study->fresh() ?? $study);
    }
}
