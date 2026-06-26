<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserAccess
{
    /**
     * @return Builder<LegalEntity>
     */
    public static function legalEntitiesQuery(Request $request): Builder
    {
        $query = LegalEntity::query();
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin() || self::canViewAllGraph($user)) {
            return $query;
        }

        $legalIds = self::viewableLegalIds($user);

        if ($legalIds === []) {
            return $query->whereRaw('false');
        }

        return $query->whereIn('legal_id', $legalIds);
    }

    public static function canViewAllGraph(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->accessScopes()
            ->where('scope_type', 'all_graph')
            ->whereNull('scope_id')
            ->where('can_view', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public static function viewableLegalIds(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $user->accessScopes()
            ->where('scope_type', 'legal')
            ->where('can_view', true)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($legalId) => (string) $legalId)
            ->values()
            ->all();
    }

    public static function canViewLegal(?User $user, string $legalId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin() || self::canViewAllGraph($user)) {
            return true;
        }

        return in_array($legalId, self::viewableLegalIds($user), true);
    }

    /**
     * @return Builder<BankAccount>
     */
    public static function bankAccountsQuery(Request $request): Builder
    {
        $query = BankAccount::query();
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin() || self::canViewAllGraph($user)) {
            return $query;
        }

        $bankAccountIds = self::viewableBankAccountIds($user);

        if ($bankAccountIds === []) {
            return $query->whereRaw('false');
        }

        return $query->whereIn('bank_account_id', $bankAccountIds);
    }

    /**
     * @return list<int>
     */
    public static function viewableBankAccountIds(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $user->accessScopes()
            ->where('scope_type', 'bank_account')
            ->where('can_view', true)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($bankAccountId) => (int) $bankAccountId)
            ->filter(fn (int $bankAccountId) => $bankAccountId > 0)
            ->values()
            ->all();
    }

    public static function canViewBankAccount(?User $user, int $bankAccountId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin() || self::canViewAllGraph($user)) {
            return true;
        }

        return in_array($bankAccountId, self::viewableBankAccountIds($user), true);
    }

    public static function canViewCashPage(?User $user): bool
    {
        return self::canViewAllGraph($user);
    }

    public static function canEditManualOperations(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! self::canViewCashPage($user)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->accessScopes()
            ->where('scope_type', 'all_graph')
            ->whereNull('scope_id')
            ->where('can_edit_manual_operations', true)
            ->exists();
    }
}
