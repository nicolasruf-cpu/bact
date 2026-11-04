<?php

namespace App\Modules\Configuration\Data;

use Illuminate\Http\Request;

/**
 * Confirmación de quien registra una acción antes de cumplirse el tiempo del protocolo
 * (D-0020): "registrar igual", con un comentario opcional que explica por qué.
 */
final readonly class EarlyConfirmation
{
    public function __construct(
        public bool $confirmed = false,
        public ?string $reason = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'early_confirmed' => ['nullable', 'boolean'],
            'early_reason' => ['nullable', 'string', 'max:500'],
        ], [], ['early_reason' => 'motivo']);

        return new self(
            confirmed: (bool) ($validated['early_confirmed'] ?? false),
            reason: filled($validated['early_reason'] ?? null) ? $validated['early_reason'] : null,
        );
    }
}
