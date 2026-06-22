<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalEntityController extends Controller
{
    public function index(): View
    {
        $legalEntities = LegalEntity::query()
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
            $request->session()->forget('current_legal_id');

            return back();
        }

        $exists = LegalEntity::query()
            ->where('legal_id', $legalId)
            ->exists();

        abort_unless($exists, 404);

        $request->session()->put('current_legal_id', $legalId);

        return back();
    }
}
