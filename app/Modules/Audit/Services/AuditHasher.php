<?php

namespace App\Modules\Audit\Services;

/**
 * Hash encadenado: sha256(hash anterior | valores del evento tal como se guardan).
 * Se calcula sobre la fila exacta que se inserta (strings), así la verificación
 * puede recalcularlo leyendo la base sin depender de casts ni de formatos.
 */
class AuditHasher
{
    /**
     * Columnas que entran en el hash, en este orden.
     */
    public const FIELDS = [
        'occurred_at', 'action', 'user_id', 'site_id', 'subject_type', 'subject_id',
        'reason', 'before', 'after', 'context', 'ip_address', 'request_id',
    ];

    /**
     * @param  array<string, mixed>  $row
     */
    public function hash(?string $previousHash, array $row): string
    {
        $values = array_map(
            fn (string $field) => isset($row[$field]) ? (string) $row[$field] : null,
            self::FIELDS,
        );

        return hash('sha256', ($previousHash ?? '').'|'.json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * JSON canónico (claves ordenadas) para before/after/context.
     *
     * @param  array<array-key, mixed>|null  $data
     */
    public function encode(?array $data): ?string
    {
        if ($data === null || $data === []) {
            return null;
        }

        return json_encode($this->sortKeys($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sortKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($value) => is_array($value) ? $this->sortKeys($value) : $value, $data);
    }
}
