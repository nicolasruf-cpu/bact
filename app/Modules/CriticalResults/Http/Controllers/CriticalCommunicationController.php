<?php

namespace App\Modules\CriticalResults\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CriticalResults\Actions\RecordCriticalCommunication;
use App\Modules\CriticalResults\Data\CommunicationData;
use App\Modules\CriticalResults\Enums\CommunicationChannel;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CriticalCommunicationController extends Controller
{
    public function store(Request $request, CriticalAlert $alert, RecordCriticalCommunication $record): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::CriticalCommunicate->value), 403);
        abort_unless($alert->site_id === getPermissionsTeamId(), 403);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_role' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'successful' => ['required', 'boolean'],
            'read_back_confirmed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'recipient_name' => 'a quién se comunicó',
            'recipient_role' => 'rol o servicio',
            'channel' => 'medio',
        ]);

        /** @var User $user */
        $user = $request->user();
        $communication = $record->handle($user, $alert, new CommunicationData(
            idempotencyKey: $validated['idempotency_key'],
            recipientName: $validated['recipient_name'],
            recipientRole: $validated['recipient_role'] ?? null,
            channel: CommunicationChannel::from($validated['channel']),
            successful: (bool) $validated['successful'],
            readBackConfirmed: (bool) ($validated['read_back_confirmed'] ?? false),
            notes: $validated['notes'] ?? null,
        ));

        Inertia::flash('toast', [
            'type' => $communication->successful ? 'success' : 'warning',
            'message' => $communication->successful ? 'Comunicación registrada: resultado crítico cerrado.' : 'Intento registrado: el resultado sigue sin comunicar.',
        ]);

        return back();
    }
}
