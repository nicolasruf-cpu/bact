<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\IsolateSignificanceChanged;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambia la significancia de un aislamiento con motivo (revisión de marchas A4). La decide
 * el bacteriólogo, muchas veces después de identificarlo. Mientras el cultivo no se cerró:
 * - a no significativo: deja de trabajarse (se descarta el antibiograma sin leer);
 * - a probable contaminante: se identifica igual, sin antibiograma; si ya estaba identificado,
 *   queda resuelto;
 * - a significativo: vuelve a identificación, o a preparar el antibiograma si ya tenía organismo.
 */
class ChangeIsolateSignificance
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly CultureClosure $closure,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, IsolateSignificance $significance, string $reason): Isolate
    {
        if (! $user->can(Permission::CulturesReadPositive->value) && ! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para cambiar la significancia.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['significance_reason' => 'Indicá el motivo del cambio.']);
        }

        return DB::transaction(function () use ($user, $isolate, $significance, $reason) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with('culture')->findOrFail($isolate->id);
            if ($isolate->culture->status->isClosed()) {
                throw ValidationException::withMessages(['significance' => 'El cultivo ya está cerrado.']);
            }
            if ($isolate->significance === $significance) {
                throw ValidationException::withMessages(['significance' => 'El aislamiento ya tiene esa significancia.']);
            }

            $from = $isolate->significance;
            $test = SusceptibilityTest::currentFor($isolate->id);
            $reopen = null;

            if ($significance !== IsolateSignificance::Significant && $test !== null && $test->status === SusceptibilityTestStatus::SetUp) {
                $test->update([
                    'status' => SusceptibilityTestStatus::Cancelled,
                    'cancelled_by' => $user->id,
                    'cancelled_at' => Date::now(),
                    'cancellation_reason' => $significance->label().': '.$reason,
                    'lock_version' => $test->lock_version + 1,
                ]);
            }

            $status = match (true) {
                $significance === IsolateSignificance::NotSignificant => IsolateStatus::NotSignificant,
                $significance === IsolateSignificance::ProbableContaminant && $isolate->organism_id !== null => IsolateStatus::Resolved,
                $significance === IsolateSignificance::ProbableContaminant => IsolateStatus::InIdentification,
                $isolate->status === IsolateStatus::NotSignificant || $isolate->organism_id === null => IsolateStatus::InIdentification,
                $test !== null && $test->status === SusceptibilityTestStatus::Completed => IsolateStatus::Resolved,
                default => IsolateStatus::Identified,
            };
            if ($status === IsolateStatus::InIdentification && $isolate->status !== IsolateStatus::InIdentification) {
                $reopen = 'identify';
            } elseif ($status === IsolateStatus::Identified && $isolate->status !== IsolateStatus::Identified) {
                $reopen = 'ast_setup';
            }

            $isolate->update([
                'significance' => $significance,
                'is_significant' => $significance->isSignificant(),
                'significance_reason' => trim($reason),
                'status' => $status,
                'no_ast_reason' => $status === IsolateStatus::Resolved && $test === null ? $significance->label().': '.trim($reason) : $isolate->no_ast_reason,
                'lock_version' => $isolate->lock_version + 1,
            ]);

            $this->auditor->record(IdentificationAuditAction::SignificanceChanged, $isolate,
                before: ['significance' => $from->value],
                after: ['significance' => $significance->value, 'status' => $status->value],
                reason: $reason,
            );

            event(new IsolateSignificanceChanged($isolate, $reopen, $user->id));

            if ($status->isResolved()) {
                $this->closure->closeIfResolved(Culture::query()->lockForUpdate()->findOrFail($isolate->culture_id));
            }

            return $isolate;
        });
    }
}
