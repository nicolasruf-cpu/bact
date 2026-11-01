<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Data\AuditChainReport;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula la cadena completa y detecta eventos alterados, insertados o faltantes.
 */
class AuditChainVerifier
{
    public function __construct(private readonly AuditHasher $hasher) {}

    public function verify(): AuditChainReport
    {
        $previousHash = null;
        $checked = 0;

        foreach (DB::table('audit_events')->orderBy('id')->lazyById(500) as $event) {
            $row = (array) $event;
            $checked++;

            if ($row['previous_hash'] !== $previousHash) {
                return AuditChainReport::broken($checked, (int) $row['id'], 'El evento no apunta al hash del evento anterior (falta o se insertó un evento).');
            }

            if (! hash_equals($this->hasher->hash($previousHash, $row), $row['hash'])) {
                return AuditChainReport::broken($checked, (int) $row['id'], 'El contenido del evento no coincide con su hash (fue modificado).');
            }

            $previousHash = $row['hash'];
        }

        $head = DB::table('audit_chain_head')->where('id', 1)->value('last_hash');
        if ($head !== $previousHash) {
            return AuditChainReport::broken($checked, null, 'La cabeza de la cadena no coincide con el último evento (se eliminaron eventos al final).');
        }

        return AuditChainReport::ok($checked);
    }
}
