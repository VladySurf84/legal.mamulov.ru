<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Models\User;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserAccessController extends Controller
{
    private const GLOBAL_MODULES = [
        UserAccess::MODULE_KASSA,
        UserAccess::ACTION_KASSA_CREATE,
        UserAccess::ACTION_KASSA_DELETE_ANY,
        UserAccess::MODULE_CURRENCIES,
        UserAccess::MODULE_EXCHANGE_RATES,
        UserAccess::ACTION_EXCHANGE_RATES_SYNC,
        UserAccess::MODULE_DOCUMENT_TYPES,
        UserAccess::ACTION_DOCUMENT_TYPES_CREATE,
        UserAccess::ACTION_DOCUMENT_TYPES_EDIT,
        UserAccess::ACTION_DOCUMENT_TYPES_DELETE,
        UserAccess::MODULE_ELECTRONIC_SIGNATURES,
        UserAccess::ACTION_ELECTRONIC_SIGNATURES_IMPORT,
        UserAccess::MODULE_USERS,
    ];

    private const SCOPED_MODULES = [
        UserAccess::MODULE_LEGAL_ENTITIES,
        UserAccess::MODULE_BANK_ACCOUNTS,
        UserAccess::ACTION_BANK_ACCOUNTS_IMPORT,
        UserAccess::MODULE_BANK_TRANSACTIONS,
        UserAccess::ACTION_BANK_TRANSACTIONS_IMPORT,
        UserAccess::ACTION_BANK_TRANSACTIONS_SYNC,
        UserAccess::MODULE_DOCUMENTS,
        UserAccess::MODULE_COUNTERPARTIES,
        UserAccess::ACTION_COUNTERPARTIES_REBUILD_LINKS,
        UserAccess::MODULE_MONEY_LAYER,
        UserAccess::ACTION_MONEY_LAYER_REBUILD,
        UserAccess::MODULE_VAT_LAYER,
        UserAccess::ACTION_VAT_LAYER_REBUILD,
        UserAccess::ACTION_VAT_LAYER_REBUILD_BANK,
        UserAccess::MODULE_VAT_BOOKS,
        UserAccess::ACTION_VAT_BOOKS_IMPORT,
        UserAccess::MODULE_VAT_BOOK_ENTRIES,
    ];

    private const DATA_PERMISSIONS = [
        'can_view',
    ];

    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewUserAccess($request->user()), 403);

        $users = User::query()
            ->withCount('accessScopes')
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $selectedUserId = $request->integer('user_id') ?: $users->first()?->getKey();
        $selectedUser = $selectedUserId
            ? User::query()->with(['accessScopes', 'modulePermissions'])->find($selectedUserId)
            : null;

        if ($selectedUser === null && $users->isNotEmpty()) {
            $selectedUser = User::query()->with(['accessScopes', 'modulePermissions'])->find($users->first()->getKey());
        }

        $legalEntities = LegalEntity::query()
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn', 'legal_color']);

        return view('user-access.index', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'legalEntities' => $legalEntities,
            'globalModules' => self::GLOBAL_MODULES,
            'scopedModules' => self::SCOPED_MODULES,
            'dataPermissions' => self::DATA_PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless(UserAccess::canViewUserAccess($request->user()), 403);

        $validated = $request->validate([
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'modules_global' => ['nullable', 'array'],
            'modules' => ['nullable', 'array'],
            'scopes' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $isAdmin = (bool) ($validated['is_admin'] ?? false);

            $user->forceFill([
                'is_admin' => $isAdmin,
                'role' => $isAdmin ? 'admin' : ($user->role === 'admin' ? 'viewer' : $user->role),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ])->save();

            DB::table('legal.user_access_scopes')
                ->where('user_id', $user->getKey())
                ->delete();
            DB::table('legal.user_module_permissions')
                ->where('user_id', $user->getKey())
                ->delete();

            if ($isAdmin) {
                return;
            }

            $now = now();
            $moduleRows = [];

            $globalModulesInput = $validated['modules_global'] ?? [];

            if ((bool) ($globalModulesInput[UserAccess::ACTION_KASSA_DELETE_ANY] ?? false)) {
                $globalModulesInput[UserAccess::ACTION_KASSA_CREATE] = true;
            }

            foreach ($globalModulesInput as $module => $enabled) {
                abort_unless(in_array($module, self::GLOBAL_MODULES, true), 422);

                if (! (bool) $enabled) {
                    continue;
                }

                $moduleRows[] = [
                    'user_id' => $user->getKey(),
                    'module' => $module,
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'can_view' => true,
                    'can_edit' => false,
                    'can_manage' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (($validated['modules'] ?? []) as $module => $permissions) {
                abort_unless(in_array($module, self::SCOPED_MODULES, true), 422);
                abort_unless(is_array($permissions), 422);

                foreach ($permissions as $legalId => $enabled) {
                    if (! (bool) $enabled) {
                        continue;
                    }

                    abort_unless((bool) preg_match('/^[0-9]{10,12}$/', (string) $legalId), 422);

                    $moduleRows[] = [
                        'user_id' => $user->getKey(),
                        'module' => $module,
                        'scope_type' => 'legal',
                        'scope_id' => (string) $legalId,
                        'can_view' => true,
                        'can_edit' => false,
                        'can_manage' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($moduleRows !== []) {
                DB::table('legal.user_module_permissions')->insert($moduleRows);
            }

            $scopeRows = [];

            foreach (($validated['scopes'] ?? []) as $scopeKey => $permissions) {
                abort_unless(is_array($permissions), 422);
                [$scopeType, $scopeId] = $this->parseScopeKey((string) $scopeKey);
                $row = [
                    'user_id' => $user->getKey(),
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $hasPermission = false;
                foreach (self::DATA_PERMISSIONS as $permission) {
                    $allowed = (bool) ($permissions[$permission] ?? false);
                    $row[$permission] = $allowed;
                    $hasPermission = $hasPermission || $allowed;
                }

                if ($hasPermission) {
                    $scopeRows[] = $row;
                }
            }

            if ($scopeRows !== []) {
                DB::table('legal.user_access_scopes')->insert($scopeRows);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Права пользователя обновлены.',
            ]);
        }

        return back()->with('status', 'Права пользователя обновлены.');
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function parseScopeKey(string $scopeKey): array
    {
        if (str_starts_with($scopeKey, 'legal:')) {
            $legalId = substr($scopeKey, strlen('legal:'));

            abort_unless((bool) preg_match('/^[0-9]{10,12}$/', $legalId), 422);

            return ['legal', $legalId];
        }

        abort(422);
    }
}
