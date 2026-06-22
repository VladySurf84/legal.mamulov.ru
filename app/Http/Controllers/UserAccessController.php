<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAccessController extends Controller
{
    private const PERMISSIONS = [
        'can_view',
        'can_import_bank_statements',
        'can_sync_bank_api',
        'can_manage_api_credentials',
        'can_edit_manual_operations',
        'can_manage_reference_data',
    ];

    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount('accessScopes')
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $selectedUserId = $request->integer('user_id') ?: $users->first()?->getKey();
        $selectedUser = $selectedUserId
            ? User::query()->with('accessScopes')->find($selectedUserId)
            : null;

        if ($selectedUser === null && $users->isNotEmpty()) {
            $selectedUser = User::query()->with('accessScopes')->find($users->first()->getKey());
        }

        $legalEntities = LegalEntity::query()
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn', 'legal_color']);

        return view('user-access.index', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'legalEntities' => $legalEntities,
            'permissions' => self::PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'viewer', 'accountant', 'manager'])],
            'is_active' => ['nullable', 'boolean'],
            'scopes' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'role' => $validated['role'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ])->save();

            DB::table('legal.user_access_scopes')
                ->where('user_id', $user->getKey())
                ->delete();

            if ($validated['role'] === 'admin') {
                return;
            }

            $now = now();
            $rows = [];

            foreach (($validated['scopes'] ?? []) as $scopeKey => $permissions) {
                [$scopeType, $scopeId] = $this->parseScopeKey((string) $scopeKey);
                $row = [
                    'user_id' => $user->getKey(),
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $hasPermission = false;
                foreach (self::PERMISSIONS as $permission) {
                    $allowed = (bool) ($permissions[$permission] ?? false);
                    $row[$permission] = $allowed;
                    $hasPermission = $hasPermission || $allowed;
                }

                if ($hasPermission) {
                    $rows[] = $row;
                }
            }

            if ($rows !== []) {
                DB::table('legal.user_access_scopes')->insert($rows);
            }
        });

        return back()->with('status', 'Права пользователя обновлены.');
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function parseScopeKey(string $scopeKey): array
    {
        if ($scopeKey === 'all_graph') {
            return ['all_graph', null];
        }

        if (str_starts_with($scopeKey, 'legal:')) {
            $legalId = substr($scopeKey, strlen('legal:'));

            abort_unless((bool) preg_match('/^[0-9]{10,12}$/', $legalId), 422);

            return ['legal', $legalId];
        }

        abort(422);
    }
}
