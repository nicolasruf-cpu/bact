<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Enums\SubcultureResult;
use App\Modules\Identification\Events\IsolateSubcultureRead;
use App\Modules\Identification\Models\IsolateSubculture;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lee el repique de un aislamiento: puro, o no (se vuelve a repicar). Las pruebas siguientes
 * se hacen desde esa placa.
 */
class ReadIsolateSubculture
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, IsolateSubculture $subculture, SubcultureResult $result, ?string $notes): IsolateSubculture
    {
        if (! $user->can(Permission::IdentificationPerform->value) && ! $user->can(Permission::IdentificationBiochemRead->value)) {
            throw new AuthorizationException('No tenés permiso para leer repiques.');
        }

        return DB::transaction(function () use ($user, $subculture, $result, $notes) {
            /** @var IsolateSubculture $subculture */
            $subculture = IsolateSubculture::query()->lockForUpdate()->with(['plate', 'isolate'])->findOrFail($subculture->id);
            if ($subculture->read_at !== null) {
                throw ValidationException::withMessages(['result' => 'El repique ya se leyó.']);
            }

            $subculture->update([
                'result' => $result,
                'notes' => filled($notes) ? $notes : null,
                'read_by' => $user->id,
                'read_at' => Date::now(),
            ]);
            $subculture->plate->update(['status' => PlateStatus::Finished]);

            $this->auditor->record(IdentificationAuditAction::Subcultured, $subculture->isolate, after: [
                'read' => true,
                'result' => $result->value,
            ], reason: $notes);

            event(new IsolateSubcultureRead($subculture, $user->id));

            return $subculture;
        });
    }
}
