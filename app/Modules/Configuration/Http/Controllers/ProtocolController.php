<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\ActivateProtocolVersion;
use App\Modules\Configuration\Actions\ApproveProtocolVersion;
use App\Modules\Configuration\Actions\CreateProtocol;
use App\Modules\Configuration\Actions\DraftProtocolVersion;
use App\Modules\Configuration\Actions\RetireProtocolVersion;
use App\Modules\Configuration\Actions\UpdateProtocolDraft;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Queries\ProtocolAdminQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración clínica: protocolos versionados (draft → approved → active → retired).
 * Prepara el Bioquímico o el Director técnico; aprueba y pone vigente el responsable (Q-A8).
 */
class ProtocolController extends Controller
{
    public function index(Request $request, ProtocolAdminQuery $query): Response
    {
        return Inertia::render('admin/protocols/Index', [
            'protocols' => $query->list(),
            ...$query->createOptions(),
            'can' => $query->abilities($this->user($request)),
        ]);
    }

    public function store(Request $request, CreateProtocol $create): RedirectResponse
    {
        $validated = $request->validate([
            'study_id' => ['required', 'integer'],
            'sample_type_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'processing_kind' => ['required', Rule::enum(ProcessingKind::class)],
        ], [], ['study_id' => 'estudio', 'sample_type_id' => 'tipo de muestra']);

        $version = $create->handle(
            $this->user($request),
            (int) $validated['study_id'],
            (int) $validated['sample_type_id'],
            (string) ($validated['name'] ?? ''),
            ProcessingKind::from($validated['processing_kind']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Protocolo creado: completá el borrador.']);

        return to_route('admin.protocol-versions.show', $version);
    }

    public function show(Request $request, ProtocolVersion $version, ProtocolAdminQuery $query): Response
    {
        return Inertia::render('admin/protocols/Version', [
            'version' => $query->detail($version),
            ...$query->editorOptions(),
            'can' => $query->abilities($this->user($request)),
        ]);
    }

    public function draft(Request $request, ProtocolVersion $version, DraftProtocolVersion $draft): RedirectResponse
    {
        $new = $draft->handle($this->user($request), $version);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Borrador v{$new->version} listo para editar."]);

        return to_route('admin.protocol-versions.show', $new);
    }

    public function update(Request $request, ProtocolVersion $version, UpdateProtocolDraft $update): RedirectResponse
    {
        $request->validate(['lock_version' => ['required', 'integer'], 'content' => ['required', 'array']]);

        /** @var array<string, mixed> $content */
        $content = $request->input('content');
        $update->handle($this->user($request), $version, $content, $request->integer('lock_version'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

        return back();
    }

    public function approve(Request $request, ProtocolVersion $version, ApproveProtocolVersion $approve): RedirectResponse
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:1000']]);
        $approve->handle($this->user($request), $version, (int) $validated['lock_version'], $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Versión {$version->version} aprobada."]);

        return back();
    }

    public function activate(Request $request, ProtocolVersion $version, ActivateProtocolVersion $activate): RedirectResponse
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer']]);
        $activate->handle($this->user($request), $version, (int) $validated['lock_version']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Versión {$version->version} vigente."]);

        return back();
    }

    public function retire(Request $request, ProtocolVersion $version, RetireProtocolVersion $retire): RedirectResponse
    {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['reason' => 'motivo']);
        $retire->handle($this->user($request), $version, $validated['reason'], (int) $validated['lock_version']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Versión {$version->version} retirada."]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
