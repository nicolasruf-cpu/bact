<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\SwitchActiveSite;
use App\Modules\Identity\Http\Requests\SwitchActiveSiteRequest;
use App\Modules\Organization\Models\Site;
use Illuminate\Http\RedirectResponse;

class ActiveSiteController extends Controller
{
    public function update(SwitchActiveSiteRequest $request, SwitchActiveSite $switchActiveSite): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $switchActiveSite->handle($user, Site::findOrFail($request->integer('site_id')));

        return back();
    }
}
