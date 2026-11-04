<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Cultures\Actions\SubcultureEnrichmentBroth;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Repique de los caldos de enriquecimiento de un cultivo (p. ej., selenito en coprocultivo).
 */
class EnrichmentController extends Controller
{
    public function show(Request $request, Culture $culture, SampleHeader $header): Response
    {
        $this->authorizeCulture($request, $culture);
        $culture->load(['sampleStudy.protocolVersion.media', 'plates.medium', 'plates.subcultures.medium']);
        $media = $culture->sampleStudy->protocolVersion->media->keyBy('medium_id');

        return Inertia::render('cultures/Enrichment', [
            'sample' => $header->for($culture->sampleStudy),
            'culture' => [
                'ulid' => $culture->ulid,
                'status_label' => $culture->status->label(),
                'inoculated_at' => $culture->inoculated_at->toIso8601String(),
            ],
            'broths' => $culture->plates->filter(fn (Plate $p) => $p->is_enrichment)->map(function (Plate $broth) use ($culture, $media) {
                /** @var ProtocolVersionMedium|null $setting */
                $setting = $media->get($broth->medium_id);
                $availableAt = $culture->inoculated_at->copy()->addHours((int) $setting?->enrichment_subculture_min_hours);

                return [
                    'id' => $broth->id,
                    'medium' => $broth->medium->name,
                    'status' => $broth->status->value,
                    'available_at' => $availableAt->toIso8601String(),
                    'available' => ! $availableAt->isFuture(),
                    'targets' => array_column($setting->enrichment_subculture_media ?? [], 'medium_code'),
                    'subcultures' => $broth->subcultures->map(fn (Plate $p) => [
                        'medium' => $p->medium->name,
                        'inoculated_at' => $p->inoculated_at?->toIso8601String(),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, Plate $plate, SubcultureEnrichmentBroth $subculture): RedirectResponse
    {
        $this->authorizeCulture($request, $plate->culture);

        /** @var User $user */
        $user = $request->user();
        $subculture->handle($user, $plate, EarlyConfirmation::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Repique del caldo registrado.']);

        return to_route('workbenches.show', 'processing');
    }

    private function authorizeCulture(Request $request, Culture $culture): void
    {
        abort_unless($request->user()?->can(Permission::CulturesInoculate->value), 403);
        abort_unless($culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
