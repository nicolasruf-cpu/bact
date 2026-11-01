<?php

namespace App\Modules\CriticalResults\Queries;

use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\CriticalResults\Models\CriticalCommunication;

/**
 * Resultados críticos de un estudio, con el historial de intentos de comunicación.
 */
class StudyCriticalAlertsQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forStudy(int $sampleStudyId): array
    {
        return CriticalAlert::query()
            ->where('sample_study_id', $sampleStudyId)
            ->with('communications.communicator')
            ->orderBy('opened_at')
            ->get()
            ->map(fn (CriticalAlert $alert) => [
                'ulid' => $alert->ulid,
                'summary' => $alert->summary,
                'status' => $alert->status->value,
                'status_label' => $alert->status->label(),
                'opened_at' => $alert->opened_at->toIso8601String(),
                'closed_at' => $alert->closed_at?->toIso8601String(),
                'communications' => $alert->communications->map(fn (CriticalCommunication $c) => [
                    'recipient_name' => $c->recipient_name,
                    'recipient_role' => $c->recipient_role,
                    'channel' => $c->channel->label(),
                    'successful' => $c->successful,
                    'read_back_confirmed' => $c->read_back_confirmed,
                    'notes' => $c->notes,
                    'communicated_by' => $c->communicator->name,
                    'communicated_at' => $c->communicated_at->toIso8601String(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
