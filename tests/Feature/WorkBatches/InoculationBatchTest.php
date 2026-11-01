<?php

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Enums\WorkBatchStatus;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->site = Site::factory()->create();
    setPermissionsTeamId(null);
    $this->assistant = userWithRole($this->site, Role::Assistant);
});

function startInoculationBatch($user, ?array $activityUlids = null): WorkBatch
{
    $ulids = $activityUlids ?? Activity::where('type', ActivityType::Inoculate->value)->orderBy('id')->pluck('ulid')->all();
    test()->actingAs($user)
        ->post(route('batches.store'), ['type' => 'inoculation', 'activities' => $ulids])
        ->assertSessionHasNoErrors();

    return WorkBatch::latest('id')->firstOrFail();
}

test('a batch claims the selected inoculations', function () {
    receiveUrineOrder($this->assistant, ['M-1', 'M-2']);

    $batch = startInoculationBatch($this->assistant);

    expect($batch->code)->toStartWith('SIE-'.Date::now()->format('Ymd').'-01')
        ->and($batch->status)->toBe(WorkBatchStatus::InProgress)
        ->and($batch->items)->toHaveCount(2)
        ->and(Activity::pluck('status')->unique()->all())->toBe([ActivityStatus::InProgress])
        ->and(Activity::pluck('claimed_by')->unique()->all())->toBe([$this->assistant->id]);

    $this->actingAs($this->assistant)->get(route('batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->component('batches/Show')
            ->has('batch.items', 2)
            ->where('batch.items.0.activity.barcode', 'M-1')
            ->has('batch.items.0.activity.media', 2));
});

test('inoculating an item creates culture and plates, completes the activity and schedules the reading', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $batch = startInoculationBatch($this->assistant);
    $study = SampleStudy::sole();

    $this->actingAs($this->assistant)
        ->post(route('inoculations.store', $study), ['batch' => $batch->ulid])
        ->assertSessionHasNoErrors();

    $culture = Culture::with('plates.medium')->sole();
    expect($culture->status)->toBe(CultureStatus::Incubating)
        ->and($culture->plates->pluck('medium.code')->all())->toBe(['AS', 'MAC'])
        ->and($study->fresh()->status)->toBe(SampleStudyStatus::InProcess)
        ->and($study->sample->fresh()->status)->toBe(SampleStatus::InProcess);

    $inoculation = Activity::where('type', ActivityType::Inoculate->value)->sole();
    expect($inoculation->status)->toBe(ActivityStatus::Completed)
        ->and($inoculation->completed_by)->toBe($this->assistant->id)
        ->and($batch->items()->sole()->status)->toBe(WorkBatchItemStatus::Done);

    $reading = Activity::where('type', ActivityType::ReadCulture->value)->sole();
    expect($reading->status)->toBe(ActivityStatus::Scheduled)
        ->and($reading->subject_type)->toBe('culture')
        ->and($reading->caused_by_activity_id)->toBe($inoculation->id)
        ->and($reading->available_at->diffInHours($culture->inoculated_at, true))->toEqual(18.0)
        ->and($reading->due_at->diffInHours($culture->inoculated_at, true))->toEqual(24.0);

    expect(AuditEvent::where('action', 'culture.inoculated')->sole()->context)->toBe(['batch' => $batch->ulid]);
});

test('omitting a protocol medium requires a reason', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    startInoculationBatch($this->assistant);
    $study = SampleStudy::sole();
    $bloodAgar = $study->protocolVersion->media->first()->medium_id;

    $this->actingAs($this->assistant)
        ->post(route('inoculations.store', $study), ['medium_ids' => [$bloodAgar]])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->assistant)
        ->post(route('inoculations.store', $study), ['medium_ids' => [$bloodAgar], 'reason' => 'Sin MacConkey en stock'])
        ->assertSessionHasNoErrors();

    expect(Culture::sole()->plates)->toHaveCount(1)
        ->and(AuditEvent::where('action', 'culture.inoculated')->sole()->reason)->toBe('Sin MacConkey en stock');
});

test('inoculating twice does not duplicate the culture', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $study = SampleStudy::sole();

    $this->actingAs($this->assistant)->post(route('inoculations.store', $study));
    $this->actingAs($this->assistant)->post(route('inoculations.store', $study));

    expect(Culture::count())->toBe(1)
        ->and(Activity::where('type', ActivityType::ReadCulture->value)->count())->toBe(1);
});

test('the remaining items can be confirmed with the standard protocol', function () {
    receiveUrineOrder($this->assistant, ['M-1', 'M-2', 'M-3']);
    $batch = startInoculationBatch($this->assistant);

    $this->actingAs($this->assistant)
        ->post(route('inoculations.bulk'), ['sample_study_ids' => SampleStudy::pluck('id')->all(), 'batch' => $batch->ulid])
        ->assertSessionHasNoErrors();

    expect(Culture::count())->toBe(3)
        ->and($batch->items()->pluck('status')->unique()->all())->toBe([WorkBatchItemStatus::Done])
        ->and(Activity::where('type', ActivityType::ReadCulture->value)->count())->toBe(3);
});

test('an activity cannot be in two batches', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $other = userWithRole($this->site, Role::Biochemist);
    $ulids = Activity::pluck('ulid')->all();

    startInoculationBatch($this->assistant, $ulids);

    $this->actingAs($other)
        ->post(route('batches.store'), ['type' => 'inoculation', 'activities' => $ulids])
        ->assertSessionHasErrors('activities');

    expect(WorkBatch::count())->toBe(1);
});

test('pause, resume and finish; pending items go back to the workbench', function () {
    receiveUrineOrder($this->assistant, ['M-1', 'M-2']);
    $batch = startInoculationBatch($this->assistant);
    $this->actingAs($this->assistant)->post(route('inoculations.store', SampleStudy::orderBy('id')->first()));

    $this->actingAs($this->assistant)->post(route('batches.pause', $batch))->assertSessionHasNoErrors();
    expect($batch->fresh()->status)->toBe(WorkBatchStatus::Paused);

    $this->actingAs($this->assistant)->post(route('batches.resume', $batch));
    expect($batch->fresh()->status)->toBe(WorkBatchStatus::InProgress);

    $this->actingAs($this->assistant)->post(route('batches.finish', $batch))->assertRedirect(route('workbenches.inoculation'));

    expect($batch->fresh()->status)->toBe(WorkBatchStatus::Completed)
        ->and($batch->items()->orderBy('position')->pluck('status')->all())->toBe([WorkBatchItemStatus::Done, WorkBatchItemStatus::Released])
        ->and(Activity::where('type', ActivityType::Inoculate->value)->where('status', ActivityStatus::Ready->value)->count())->toBe(1);

    // La actividad liberada puede entrar en otra tanda.
    $released = Activity::where('status', ActivityStatus::Ready->value)->pluck('ulid')->all();
    startInoculationBatch($this->assistant, $released);
    expect(WorkBatch::count())->toBe(2);
});

test('an item can be removed with a reason', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $batch = startInoculationBatch($this->assistant);
    $item = $batch->items()->sole();

    $this->actingAs($this->assistant)->post(route('batches.items.remove', [$batch, $item]))->assertSessionHasErrors('reason');
    $this->actingAs($this->assistant)->post(route('batches.items.remove', [$batch, $item]), ['reason' => 'Muestra derramada'])->assertSessionHasNoErrors();

    expect($item->fresh()->status)->toBe(WorkBatchItemStatus::Removed)
        ->and($item->fresh()->removed_reason)->toBe('Muestra derramada')
        ->and(Activity::sole()->status)->toBe(ActivityStatus::Ready);
});

test('only the owner manages the batch', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $batch = startInoculationBatch($this->assistant);
    $colleague = userWithRole($this->site, Role::Biochemist);

    $this->actingAs($colleague)->get(route('batches.show', $batch))->assertForbidden();
    $this->actingAs($colleague)->post(route('batches.pause', $batch))->assertForbidden();

    $director = userWithRole($this->site, Role::TechnicalDirector);
    $this->actingAs($director)->get(route('batches.show', $batch))->assertOk();
});

test('a batch from another site is not accessible', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $batch = startInoculationBatch($this->assistant);
    $otherSite = Site::factory()->create();
    $director = userWithRole($otherSite, Role::TechnicalDirector);

    $this->actingAs($director)->get(route('batches.show', $batch))->assertForbidden();
});

test('users without permission cannot inoculate', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $viewer = userWithRole($this->site, Role::Viewer);

    $this->actingAs($viewer)->post(route('inoculations.store', SampleStudy::sole()))->assertForbidden();
    $this->actingAs($viewer)->post(route('batches.store'), ['type' => 'inoculation', 'activities' => Activity::pluck('ulid')->all()])->assertForbidden();
});

test('the scheduled reading becomes ready when the incubation time is reached', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $this->actingAs($this->assistant)->post(route('inoculations.store', SampleStudy::sole()));

    $this->artisan('activities:release')->assertSuccessful();
    expect(Activity::where('type', ActivityType::ReadCulture->value)->value('status'))->toBe(ActivityStatus::Scheduled);

    $this->travel(18)->hours();
    $this->artisan('activities:release')->assertSuccessful();
    expect(Activity::where('type', ActivityType::ReadCulture->value)->value('status'))->toBe(ActivityStatus::Ready);
});

test('the demo clock advances scheduled activities (not in production)', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $this->actingAs($this->assistant)->post(route('inoculations.store', SampleStudy::sole()));

    $this->artisan('demo:advance', ['hours' => 18])->assertSuccessful();

    expect(Activity::where('type', ActivityType::ReadCulture->value)->value('status'))->toBe(ActivityStatus::Ready);
});
