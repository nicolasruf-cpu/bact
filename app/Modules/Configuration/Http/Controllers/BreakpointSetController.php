<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\ChangeBreakpointSetStatus;
use App\Modules\Configuration\Actions\CreateBreakpointSet;
use App\Modules\Configuration\Actions\SaveBreakpoints;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Configuration\Queries\BreakpointAdminQuery;
use App\Modules\Configuration\Queries\ProtocolAdminQuery;
use App\Modules\Configuration\Services\BreakpointCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Puntos de corte (Q-C4): sets versionados que carga el laboratorio desde su edición del
 * CLSI M100, por CSV o en la tabla. El sistema no trae valores reales.
 */
class BreakpointSetController extends Controller
{
    public function index(Request $request, BreakpointAdminQuery $query, ProtocolAdminQuery $protocols): Response
    {
        return Inertia::render('admin/breakpoints/Index', [
            'sets' => $query->list(),
            'can' => $protocols->abilities($this->user($request)),
        ]);
    }

    public function store(Request $request, CreateBreakpointSet $create): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'edition' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'copy_from_id' => ['nullable', 'integer', 'exists:breakpoint_sets,id'],
        ], [], ['name' => 'nombre', 'edition' => 'edición']);

        $from = isset($validated['copy_from_id']) ? BreakpointSet::query()->with('breakpoints')->findOrFail((int) $validated['copy_from_id']) : null;
        $set = $create->handle($this->user($request), $validated['name'], $validated['edition'], $validated['notes'] ?? null, $from);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador creado: cargá los puntos de corte.']);

        return to_route('admin.breakpoint-sets.show', $set);
    }

    public function show(Request $request, BreakpointSet $set, BreakpointAdminQuery $query, ProtocolAdminQuery $protocols): Response
    {
        return Inertia::render('admin/breakpoints/Show', [
            'set' => $query->detail($set),
            ...$query->options(),
            'can' => $protocols->abilities($this->user($request)),
        ]);
    }

    public function import(Request $request, BreakpointSet $set, BreakpointCsv $csv, SaveBreakpoints $save): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'lock_version' => ['required', 'integer'],
        ], [], ['file' => 'archivo']);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $content = (string) file_get_contents($file->getRealPath());
        $rows = $csv->parse($content);

        $save->handle($this->user($request), $set, $rows, $request->integer('lock_version'), 'csv', hash('sha256', $content));

        Inertia::flash('toast', ['type' => 'success', 'message' => count($rows).' puntos de corte importados.']);

        return back();
    }

    public function save(Request $request, BreakpointSet $set, SaveBreakpoints $save): RedirectResponse
    {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer'],
            'rows' => ['present', 'array', 'max:2000'],
            'rows.*.organism_group' => ['required', 'string'],
            'rows.*.antibiotic_code' => ['required', 'string'],
            'rows.*.method' => ['required', 'string'],
            'rows.*.susceptible' => ['required'],
            'rows.*.resistant' => ['required'],
        ]);

        /** @var list<array{organism_group: string, antibiotic_code: string, method: string, susceptible: string, resistant: string}> $rows */
        $rows = array_values($validated['rows']);
        $save->handle($this->user($request), $set, $rows, (int) $validated['lock_version'], 'manual');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puntos de corte guardados.']);

        return back();
    }

    public function status(Request $request, BreakpointSet $set, ChangeBreakpointSetStatus $change): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ProtocolVersionStatus::Approved->value, ProtocolVersionStatus::Active->value, ProtocolVersionStatus::Retired->value])],
            'lock_version' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['reason' => 'motivo']);

        $set = $change->handle($this->user($request), $set, ProtocolVersionStatus::from($validated['status']), (int) $validated['lock_version'], $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Set {$set->name}: {$set->status->label()}."]);

        return back();
    }

    public function template(BreakpointCsv $csv): StreamedResponse
    {
        return $this->csv($csv->export(null), 'puntos-de-corte-plantilla.csv');
    }

    public function export(BreakpointSet $set, BreakpointCsv $csv): StreamedResponse
    {
        abort_if($set->is_synthetic, 404);

        return $this->csv($csv->export($set), 'puntos-de-corte-'.$set->id.'.csv');
    }

    private function csv(string $content, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($content) {
            echo "\xEF\xBB\xBF".$content;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
