<?php

namespace App\Modules\Audit\Queries;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditActionRegistry;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogQuery
{
    public function __construct(private readonly AuditActionRegistry $registry) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(?int $siteId, ?string $action, int $perPage = 50): LengthAwarePaginator
    {
        return AuditEvent::query()
            ->with(['user:id,name', 'site:id,name'])
            // La sede activa ve sus eventos y los que no tienen sede (p. ej. ingresos fallidos).
            ->when($siteId, fn ($query) => $query->where(fn ($q) => $q->where('site_id', $siteId)->orWhereNull('site_id')))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => $this->toRow($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'action' => $event->action,
            'action_label' => $this->registry->label($event->action),
            'user' => $event->user?->name,
            'site' => $event->site?->name,
            'subject' => $event->subject_type ? "{$event->subject_type} #{$event->subject_id}" : null,
            'reason' => $event->reason,
            'before' => $event->before,
            'after' => $event->after,
            'context' => $event->context,
            'ip_address' => $event->ip_address,
        ];
    }
}
