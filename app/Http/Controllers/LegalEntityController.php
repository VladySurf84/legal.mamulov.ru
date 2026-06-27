<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalEntityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewModule($request->user(), UserAccess::MODULE_LEGAL_ENTITIES), 403);

        $legalEntities = UserAccess::legalEntitiesQuery($request)
            ->withCount('bankAccounts')
            ->orderBy('legal_name')
            ->get();

        return view('legal-entities.index', [
            'legalEntities' => $legalEntities,
        ]);
    }

    public function updateContext(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_id' => ['required', 'string', 'max:12'],
        ]);

        $legalId = (string) $validated['legal_id'];

        if ($legalId === '__all__') {
            abort_unless(UserAccess::canViewAllGraph($request->user()), 403);

            $request->session()->forget('current_legal_id');

            return back();
        }

        $exists = UserAccess::legalEntitiesQuery($request)
            ->where('legal_id', $legalId)
            ->exists();

        abort_unless($exists, 404);

        $request->session()->put('current_legal_id', $legalId);

        return back();
    }
}
