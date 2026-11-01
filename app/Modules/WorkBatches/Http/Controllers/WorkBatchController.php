<?php

namespace App\Modules\WorkBatches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Organization\Models\Site;
use App\Modules\WorkBatches\Actions\ChangeWorkBatchState;
use App\Modules\WorkBatches\Actions\RemoveBatchItem;
use App\Modules\WorkBatches\Actions\StartWorkBatch;
use App\Modules\WorkBatches\Enums\WorkBatchType;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\WorkBatches\Models\WorkBatchItem;
use App\Modules\WorkBatches\Queries\WorkBatchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkBatchController extends Controller
{
    public function store(Request $request, StartWorkBatch $start): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(WorkBatchType::class)],
            'activities' => ['required', 'array', 'min:1', 'max:200'],
            'activities.*' => ['string', 'size:26'],
        ]);

        $type = WorkBatchType::from($validated['type']);
        $this->authorizeType($request->user(), $type);

        $batch = $start->handle($this->user($request), $this->site($request)->id, $type, array_values($validated['activities']));

        return to_route('batches.show', $batch);
    }

    public function show(WorkBatch $batch, WorkBatchQuery $query): Response
    {
        Gate::authorize('manage', $batch);

        return Inertia::render('batches/Show', ['batch' => $query->show($batch)]);
    }

    public function pause(Request $request, WorkBatch $batch, ChangeWorkBatchState $change): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        $change->handle($this->user($request), $batch, ChangeWorkBatchState::PAUSE, $request->string('reason')->toString() ?: null);

        return back();
    }

    public function resume(Request $request, WorkBatch $batch, ChangeWorkBatchState $change): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        $change->handle($this->user($request), $batch, ChangeWorkBatchState::RESUME);

        return back();
    }

    public function finish(Request $request, WorkBatch $batch, ChangeWorkBatchState $change): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        $change->handle($this->user($request), $batch, ChangeWorkBatchState::FINISH);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tanda {$batch->code} finalizada."]);

        return to_route($batch->type === WorkBatchType::Inoculation ? 'workbenches.inoculation' : 'dashboard');
    }

    public function removeItem(Request $request, WorkBatch $batch, WorkBatchItem $item, RemoveBatchItem $remove): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        abort_unless($item->work_batch_id === $batch->id, 404);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'motivo']);
        $remove->handle($this->user($request), $item, $validated['reason']);

        return back();
    }

    private function authorizeType(?User $user, WorkBatchType $type): void
    {
        $permission = match ($type) {
            WorkBatchType::Inoculation => Permission::CulturesInoculate,
            WorkBatchType::Reading => Permission::CulturesReadPositive,
        };

        abort_unless($user?->can($permission->value) && $user->can(Permission::BatchesManageOwn->value), 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function site(Request $request): Site
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        return $site;
    }
}
