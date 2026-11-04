<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Cultures\Events\CultureCompleted;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Cultures\Events\DirectExamRecorded;
use App\Modules\Cultures\Events\EnrichmentSubcultured;
use App\Modules\Cultures\Events\SampleStudyRejected;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identification\Events\InstrumentLoaded;
use App\Modules\Identification\Events\InstrumentRunResulted;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Identification\Events\IsolateReferred;
use App\Modules\Identification\Events\IsolateSignificanceChanged;
use App\Modules\Identification\Events\IsolateSubcultured;
use App\Modules\Identification\Events\IsolateSubcultureRead;
use App\Modules\Identification\Events\IsolateTestsRead;
use App\Modules\Identification\Events\IsolateTestsSetUp;
use App\Modules\Identification\Events\ReferralResultRecorded;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Reporting\Events\ReportValidated;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityAntibioticsAdded;
use App\Modules\Susceptibility\Events\SusceptibilityTestCancelled;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Susceptibility\Services\AstSchedule;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use App\Modules\Workflow\Services\ActivityLifecycle;
use App\Modules\Workflow\Services\ActivityScheduler;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Políticas de flujo del cultivo en adelante (WORKFLOW_ENGINE.md §3). Cada evento clínico
 * completa su actividad y crea la siguiente, en la misma transacción. Los tiempos salen
 * de la versión de protocolo congelada; acá no hay criterios clínicos.
 */
class ClinicalFlowPolicy
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly ActivityScheduler $scheduler,
        private readonly AstSchedule $astSchedule,
    ) {}

    /**
     * Lectura → completa la lectura; reincubación → nueva lectura; aislamientos → identificación.
     */
    public function onCultureRead(CultureRead $event): void
    {
        $culture = $event->culture;
        $study = $culture->sampleStudy;

        $reading = $this->lifecycle->completeFor($culture, ActivityType::ReadCulture, $event->userId);

        if ($event->nextReadAt !== null) {
            $this->scheduler->schedule(
                type: ActivityType::ReadCulture,
                subject: $culture,
                sampleId: $study->sample_id,
                siteId: $study->processing_site_id,
                priority: $this->priority($study),
                availableAt: $event->nextReadAt,
                dueAt: $event->nextReadDueAt,
                attempt: $event->reading->attempt + 1,
                causedByActivityId: $reading?->id,
            );
        }

        foreach ($event->significantIsolates as $isolate) {
            $this->scheduleFor($isolate, ActivityType::Identify, $reading?->id);
        }
    }

    public function onDirectExamRecorded(DirectExamRecorded $event): void
    {
        $this->lifecycle->completeFor($event->sampleStudy, ActivityType::DirectExam, $event->userId);
    }

    public function onEnrichmentSubcultured(EnrichmentSubcultured $event): void
    {
        $this->lifecycle->completeFor($event->broth, ActivityType::SubcultureEnrichment, $event->userId);
    }

    /**
     * Identificación definitiva → completa la identificación; si corresponde, preparar el ATB.
     */
    public function onIsolateIdentified(IsolateIdentified $event): void
    {
        $identify = $this->lifecycle->completeFor($event->isolate, ActivityType::Identify, $event->userId);

        if ($event->requiresSusceptibility && $event->presumptiveTest === null) {
            $this->scheduleFor($event->isolate, ActivityType::AstSetup, $identify?->id);
        }
        // Antibiograma preparado con la presuntiva que la definitiva no requiere: se descartó.
        if (! $event->requiresSusceptibility && $event->presumptiveTest !== null) {
            $this->lifecycle->cancelOpenFor($event->presumptiveTest, [ActivityType::AstRead], 'La identificación definitiva no requiere antibiograma.');
        }
    }

    /**
     * Significancia cambiada → se cancela lo pendiente del aislamiento y, si vuelve a
     * trabajarse, se crea la identificación o la preparación del antibiograma.
     */
    public function onIsolateSignificanceChanged(IsolateSignificanceChanged $event): void
    {
        $isolate = $event->isolate;
        $reason = 'Significancia: '.$isolate->significance->label();

        if (! $isolate->significance->isSignificant()) {
            $this->lifecycle->cancelOpenFor($isolate, [ActivityType::AstSetup], $reason);
            SusceptibilityTest::query()->where('isolate_id', $isolate->id)
                ->where('status', SusceptibilityTestStatus::Cancelled->value)->get()
                ->each(fn (SusceptibilityTest $t) => $this->lifecycle->cancelOpenFor($t, [ActivityType::AstRead], $reason));
        }
        if ($isolate->status->isResolved()) {
            $this->lifecycle->cancelOpenFor($isolate, [ActivityType::Identify, ActivityType::ReadIdentificationTests], $reason);
        }

        match ($event->reopen) {
            'identify' => $this->scheduleAt($isolate, ActivityType::Identify, Date::now()),
            'ast_setup' => $this->scheduleAt($isolate, ActivityType::AstSetup, Date::now()),
            default => null,
        };
    }

    /**
     * Carga en el equipo → queda pendiente el resultado; con resultado completo, se completa.
     */
    public function onInstrumentLoaded(InstrumentLoaded $event): void
    {
        $this->scheduleSubject($event->run, $event->run->isolate, ActivityType::InstrumentResult, $event->run->loaded_at);
    }

    public function onInstrumentRunResulted(InstrumentRunResulted $event): void
    {
        if (! $event->run->isPending()) {
            $this->lifecycle->completeFor($event->run, ActivityType::InstrumentResult, $event->userId);
        }
    }

    public function onIsolateSubcultured(IsolateSubcultured $event): void
    {
        $this->scheduleSubject($event->subculture, $event->subculture->isolate, ActivityType::ReadIsolateSubculture, $event->readableAt);
    }

    public function onIsolateSubcultureRead(IsolateSubcultureRead $event): void
    {
        $this->lifecycle->completeFor($event->subculture, ActivityType::ReadIsolateSubculture, $event->userId);
    }

    public function onIsolateReferred(IsolateReferred $event): void
    {
        $this->scheduleSubject($event->referral, $event->referral->isolate, ActivityType::ReferralResult, $event->referral->sent_at);
    }

    public function onReferralResultRecorded(ReferralResultRecorded $event): void
    {
        $this->lifecycle->completeFor($event->referral, ActivityType::ReferralResult, $event->userId);
    }

    /**
     * Pruebas sembradas → su lectura, al cumplirse la incubación de la primera que vence.
     */
    public function onIsolateTestsSetUp(IsolateTestsSetUp $event): void
    {
        if ($this->hasOpen($event->isolate, ActivityType::ReadIdentificationTests)) {
            return;
        }

        $this->scheduleAt($event->isolate, ActivityType::ReadIdentificationTests, $event->readableAt);
    }

    /**
     * Pruebas leídas → completa la lectura; si quedan otras, una nueva lectura para ellas.
     */
    public function onIsolateTestsRead(IsolateTestsRead $event): void
    {
        $this->lifecycle->completeFor($event->isolate, ActivityType::ReadIdentificationTests, $event->userId);

        if ($event->nextReadableAt !== null) {
            $this->scheduleAt($event->isolate, ActivityType::ReadIdentificationTests, $event->nextReadableAt);
        }
    }

    /**
     * ATB preparado → lectura del ATB al cumplirse la incubación del panel o del protocolo (A2).
     */
    public function onSusceptibilitySetUp(SusceptibilityTestSetUp $event): void
    {
        $isolate = $event->test->isolate;
        $setup = $this->lifecycle->completeFor($isolate, ActivityType::AstSetup, $event->userId);

        $study = $isolate->culture->sampleStudy;
        $setUpAt = $event->test->set_up_at;
        [$min, $due] = $this->astSchedule->hours($event->test);

        $this->scheduler->schedule(
            type: ActivityType::AstRead,
            subject: $event->test,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: $setUpAt->copy()->addHours($min),
            dueAt: $due !== null ? $setUpAt->copy()->addHours($due) : null,
            attempt: $this->nextAttempt($event->test, ActivityType::AstRead),
            causedByActivityId: $setup?->id,
        );
    }

    /**
     * ATB descartado → se cancela su lectura; si el aislamiento sigue esperando antibiograma,
     * vuelve la preparación.
     */
    public function onSusceptibilityTestCancelled(SusceptibilityTestCancelled $event): void
    {
        $this->lifecycle->cancelOpenFor($event->test, [ActivityType::AstRead], 'Antibiograma descartado: '.$event->test->cancellation_reason);

        if ($event->needsNewSetUp) {
            $this->scheduleAt($event->test->isolate, ActivityType::AstSetup, Date::now());
        }
    }

    public function onSusceptibilityRead(SusceptibilityTestRead $event): void
    {
        $this->lifecycle->completeFor($event->test, ActivityType::AstRead, $event->userId);
    }

    /**
     * Antibióticos agregados al leer el panel → otra lectura, con la incubación del protocolo (D-0022).
     */
    public function onSusceptibilityAntibioticsAdded(SusceptibilityAntibioticsAdded $event): void
    {
        $study = $event->test->isolate->culture->sampleStudy;
        [$min, $due] = $this->astSchedule->hours($event->test, additional: true);

        $this->scheduler->schedule(
            type: ActivityType::AstRead,
            subject: $event->test,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: $event->setUpAt->copy()->addHours($min),
            dueAt: $due !== null ? $event->setUpAt->copy()->addHours($due) : null,
            attempt: $this->nextAttempt($event->test, ActivityType::AstRead),
        );
    }

    /**
     * Muestra no apta → se cancela todo lo pendiente del estudio (siembra, lecturas,
     * identificaciones, antibiogramas, frasco); la validación la crea el cierre.
     */
    public function onSampleStudyRejected(SampleStudyRejected $event): void
    {
        $study = $event->sampleStudy;
        $culture = $event->culture;
        $isolateIds = $culture?->isolates()->pluck('id')->all() ?? [];

        $subjects = [
            (new SampleStudy)->getMorphClass() => [$study->id],
            (new Culture)->getMorphClass() => $culture === null ? [] : [$culture->id],
            (new Plate)->getMorphClass() => $culture?->plates()->pluck('id')->all() ?? [],
            (new Isolate)->getMorphClass() => $isolateIds,
            (new SusceptibilityTest)->getMorphClass() => SusceptibilityTest::query()->whereIn('isolate_id', $isolateIds)->pluck('id')->all(),
            (new BloodCultureBottle)->getMorphClass() => BloodCultureBottle::query()->where('sample_study_id', $study->id)->pluck('id')->all(),
        ];

        Activity::query()
            ->where('sample_id', $study->sample_id)
            ->whereNotIn('status', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->where(function ($q) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    if ($ids !== []) {
                        $q->orWhere(fn ($s) => $s->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->get()
            ->each(fn (Activity $activity) => $this->lifecycle->cancelOpenFor($activity->subject, [$activity->type], 'Muestra no apta: '.$study->rejection_reason));
    }

    /**
     * Cultivo cerrado → el estudio pasa a la bandeja de validación.
     */
    public function onCultureCompleted(CultureCompleted $event): void
    {
        $study = $event->sampleStudy;

        $this->scheduler->schedule(
            type: ActivityType::ValidateReport,
            subject: $study,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: Date::now(),
        );
    }

    public function onReportValidated(ReportValidated $event): void
    {
        $this->lifecycle->completeFor($event->sampleStudy, ActivityType::ValidateReport, $event->userId);
    }

    private function scheduleFor(Isolate $isolate, ActivityType $type, ?int $causedBy): void
    {
        $study = $isolate->culture->sampleStudy;

        $this->scheduler->schedule(
            type: $type,
            subject: $isolate,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: Date::now(),
            causedByActivityId: $causedBy,
        );
    }

    private function hasOpen(Model $subject, ActivityType $type): bool
    {
        return Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('type', $type->value)
            ->whereNotIn('status', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->exists();
    }

    private function nextAttempt(Model $subject, ActivityType $type): int
    {
        return Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('type', $type->value)
            ->count() + 1;
    }

    /**
     * Actividad sobre un aislamiento desde una hora dada, con el número de intento siguiente.
     */
    private function scheduleAt(Isolate $isolate, ActivityType $type, CarbonInterface $availableAt, ?CarbonInterface $dueAt = null): void
    {
        $study = $isolate->culture->sampleStudy;

        $this->scheduler->schedule(
            type: $type,
            subject: $isolate,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: $availableAt,
            dueAt: $dueAt,
            attempt: $this->nextAttempt($isolate, $type),
        );
    }

    /**
     * Actividad sobre un sujeto que cuelga de un aislamiento (carga del equipo, repique, derivación).
     */
    private function scheduleSubject(Model $subject, Isolate $isolate, ActivityType $type, CarbonInterface $availableAt): void
    {
        $study = $isolate->culture->sampleStudy;

        $this->scheduler->schedule(
            type: $type,
            subject: $subject,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: $availableAt,
        );
    }

    private function priority(SampleStudy $study): Priority
    {
        return Sample::query()->with('labOrder')->findOrFail($study->sample_id)->labOrder->priority;
    }
}
