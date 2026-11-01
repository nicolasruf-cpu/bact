<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Contracts\AuditAction;
use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Único punto de escritura de la auditoría (ADR-0006).
 * Debe llamarse dentro de la transacción de la operación auditada: si la operación
 * se revierte, el evento también.
 */
class Auditor
{
    public function __construct(
        private readonly AuditHasher $hasher,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $before  valores anteriores (solo lo que cambió)
     * @param  array<string, mixed>  $after  valores nuevos
     * @param  array<string, mixed>  $context  p. ej. ['batch_id' => 12, 'activity_id' => 99]
     */
    public function record(
        AuditAction $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        array $context = [],
        ?int $userId = null,
    ): AuditEvent {
        // Se usa el administrador de transacciones (y no transactionLevel) porque en las
        // pruebas descuenta la transacción que envuelve cada test.
        if (app('db.transactions')->callbackApplicableTransactions()->isEmpty()) {
            throw new LogicException('La auditoría se registra dentro de la transacción de la operación.');
        }

        $head = DB::table('audit_chain_head')->where('id', 1)->lockForUpdate()->first();
        $previousHash = $head?->last_hash;

        $siteId = getPermissionsTeamId();

        $row = [
            'occurred_at' => Date::now()->utc()->format('Y-m-d H:i:s.u'),
            'action' => $action->auditName(),
            'user_id' => $userId ?? Auth::id(),
            'site_id' => is_numeric($siteId) ? (int) $siteId : null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'before' => $this->hasher->encode($before),
            'after' => $this->hasher->encode($after),
            'context' => $this->hasher->encode($context),
            'ip_address' => $this->request->ip(),
            'request_id' => $this->request->attributes->get('request_id'),
        ];

        $row['previous_hash'] = $previousHash;
        $row['hash'] = $this->hasher->hash($previousHash, $row);

        $id = DB::table('audit_events')->insertGetId($row);

        DB::table('audit_chain_head')->where('id', 1)->update([
            'last_event_id' => $id,
            'last_hash' => $row['hash'],
        ]);

        return AuditEvent::findOrFail($id);
    }
}
