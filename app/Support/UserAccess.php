<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserAccess
{
    public const MODULE_LEGAL_ENTITIES = 'legal_entities';
    public const MODULE_BANK_ACCOUNTS = 'bank_accounts';
    public const MODULE_BANK_TRANSACTIONS = 'bank_transactions';
    public const MODULE_DOCUMENTS = 'documents';
    public const MODULE_COUNTERPARTIES = 'counterparties';
    public const MODULE_MONEY_LAYER = 'money_layer';
    public const MODULE_VAT_LAYER = 'vat_layer';
    public const MODULE_VAT_BOOKS = 'vat_books';
    public const MODULE_VAT_BOOK_ENTRIES = 'vat_book_entries';
    public const MODULE_CURRENCIES = 'currencies';
    public const MODULE_EXCHANGE_RATES = 'exchange_rates';
    public const MODULE_DOCUMENT_TYPES = 'document_types';
    public const MODULE_ELECTRONIC_SIGNATURES = 'electronic_signatures';
    public const MODULE_HH_RESUMES = 'hh_resumes';
    public const MODULE_KASSA = 'kassa';
    public const MODULE_USERS = 'users';
    public const ACTION_BANK_ACCOUNTS_IMPORT = 'bank_accounts.import';
    public const ACTION_BANK_TRANSACTIONS_IMPORT = 'bank_transactions.import';
    public const ACTION_BANK_TRANSACTIONS_SYNC = 'bank_transactions.sync';
    public const ACTION_COUNTERPARTIES_REBUILD_LINKS = 'counterparties.rebuild_links';
    public const ACTION_DOCUMENT_TYPES_CREATE = 'document_types.create';
    public const ACTION_DOCUMENT_TYPES_EDIT = 'document_types.edit';
    public const ACTION_DOCUMENT_TYPES_DELETE = 'document_types.delete';
    public const ACTION_ELECTRONIC_SIGNATURES_IMPORT = 'electronic_signatures.import';
    public const ACTION_EXCHANGE_RATES_SYNC = 'exchange_rates.sync';
    public const ACTION_KASSA_CREATE = 'kassa.create';
    public const ACTION_KASSA_DELETE_ANY = 'kassa.delete_any';
    public const ACTION_MONEY_LAYER_REBUILD = 'money_layer.rebuild';
    public const ACTION_VAT_BOOKS_IMPORT = 'vat_books.import';
    public const ACTION_VAT_LAYER_REBUILD = 'vat_layer.rebuild';
    public const ACTION_VAT_LAYER_REBUILD_BANK = 'vat_layer.rebuild_bank';

    /**
     * @return Builder<LegalEntity>
     */
    public static function legalEntitiesQuery(Request $request): Builder
    {
        $query = LegalEntity::query();
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin()) {
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

        return $user->isAdmin();
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

        if ($user->isAdmin()) {
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

        if (! $user instanceof User || $user->isAdmin()) {
            return $query;
        }

        $legalIds = self::viewableLegalIds($user);

        if ($legalIds === []) {
            return $query->whereRaw('false');
        }

        return $query->whereIn('legal_id', $legalIds);
    }

    /**
     * @return list<int>
     */
    public static function viewableBankAccountIds(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isAdmin()) {
            return BankAccount::query()
                ->orderBy('bank_account_id')
                ->pluck('bank_account_id')
                ->map(fn ($bankAccountId) => (int) $bankAccountId)
                ->filter(fn (int $bankAccountId) => $bankAccountId > 0)
                ->values()
                ->all();
        }

        $legalIds = self::viewableLegalIds($user);

        if ($legalIds === []) {
            return [];
        }

        return BankAccount::query()
            ->whereIn('legal_id', $legalIds)
            ->orderBy('bank_account_id')
            ->pluck('bank_account_id')
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

        if ($user->isAdmin()) {
            return true;
        }

        $legalId = BankAccount::query()
            ->where('bank_account_id', $bankAccountId)
            ->value('legal_id');

        return $legalId !== null && self::canViewLegal($user, (string) $legalId);
    }

    public static function canViewModule(?User $user, string $module): bool
    {
        return self::hasModulePermission($user, $module);
    }

    public static function canEditModule(?User $user, string $module): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function canManageModule(?User $user, string $module): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function firstViewableRoute(?User $user): ?string
    {
        return collect([
            'legal-entities.index' => self::canViewModule($user, self::MODULE_LEGAL_ENTITIES),
            'bank-accounts.index' => self::canViewBankAccounts($user),
            'bank-transactions.index' => self::canViewBankTransactions($user),
            'kassa.index' => self::canViewCashPage($user),
            'documents.index' => self::canViewModule($user, self::MODULE_DOCUMENTS),
            'counterparties.index' => self::canViewModule($user, self::MODULE_COUNTERPARTIES),
            'money-layer.index' => self::canViewModule($user, self::MODULE_MONEY_LAYER),
            'vat-layer.index' => self::canViewModule($user, self::MODULE_VAT_LAYER),
            'vat-books.index' => self::canViewModule($user, self::MODULE_VAT_BOOKS),
            'vat-book-entries.index' => self::canViewModule($user, self::MODULE_VAT_BOOK_ENTRIES),
            'currencies.index' => self::canViewModule($user, self::MODULE_CURRENCIES),
            'exchange-rates.index' => self::canViewModule($user, self::MODULE_EXCHANGE_RATES),
            'document-types.index' => self::canViewModule($user, self::MODULE_DOCUMENT_TYPES),
            'electronic-signatures.index' => self::canViewElectronicSignatures($user),
            'hh-resumes.index' => self::canViewHhResumes($user),
            'hh-browser-captures.index' => self::canViewHhResumes($user),
            'users.index' => self::canViewUsers($user),
            'user-access.index' => self::canViewUserAccess($user),
            'scheduler.index' => self::canViewScheduler($user),
        ])->filter()->keys()->first();
    }

    public static function canViewCashPage(?User $user): bool
    {
        return self::canViewModule($user, self::MODULE_KASSA);
    }

    public static function canViewBankAccounts(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return self::canViewModule($user, self::MODULE_BANK_ACCOUNTS);
    }

    public static function canManageBankAccounts(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_BANK_ACCOUNTS_IMPORT);
    }

    public static function canViewBankTransactions(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return self::canViewModule($user, self::MODULE_BANK_TRANSACTIONS);
    }

    public static function canImportBankStatements(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_BANK_TRANSACTIONS_IMPORT);
    }

    public static function canSyncBankApi(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_BANK_TRANSACTIONS_SYNC);
    }

    public static function canEditManualOperations(?User $user): bool
    {
        return self::canCreateCashEntry($user) || self::canRebuildCashLayer($user);
    }

    public static function canCreateCashEntry(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_KASSA_CREATE);
    }

    public static function canEditAnyCashEntry(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_KASSA_DELETE_ANY);
    }

    public static function canDeleteFreshCashEntry(?User $user): bool
    {
        return self::canDeleteAnyCashEntry($user)
            || self::canCreateCashEntry($user);
    }

    public static function canDeleteAnyCashEntry(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_KASSA_DELETE_ANY);
    }

    public static function canDeleteCashEntry(?User $user): bool
    {
        return self::canDeleteFreshCashEntry($user);
    }

    public static function canRebuildCashLayer(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function canViewScheduler(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function canRunScheduler(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function canViewUsers(?User $user): bool
    {
        return self::canViewModule($user, self::MODULE_USERS);
    }

    public static function canManageUsers(?User $user): bool
    {
        return self::canManageModule($user, self::MODULE_USERS);
    }

    public static function canViewUserAccess(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function canViewElectronicSignatures(?User $user): bool
    {
        return self::canViewModule($user, self::MODULE_ELECTRONIC_SIGNATURES);
    }

    public static function canViewHhResumes(?User $user): bool
    {
        return self::canViewModule($user, self::MODULE_HH_RESUMES);
    }

    public static function canManageElectronicSignatures(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_ELECTRONIC_SIGNATURES_IMPORT);
    }

    public static function canRebuildMoneyLayer(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_MONEY_LAYER_REBUILD);
    }

    public static function canRebuildVatLayer(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_VAT_LAYER_REBUILD);
    }

    public static function canRebuildBankVatLayer(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_VAT_LAYER_REBUILD_BANK);
    }

    public static function canImportVatBooks(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_VAT_BOOKS_IMPORT);
    }

    public static function canSyncExchangeRates(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_EXCHANGE_RATES_SYNC);
    }

    public static function canRebuildCounterpartyLinks(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_COUNTERPARTIES_REBUILD_LINKS);
    }

    public static function canCreateDocumentTypes(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_DOCUMENT_TYPES_CREATE);
    }

    public static function canEditDocumentTypes(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_DOCUMENT_TYPES_EDIT);
    }

    public static function canDeleteDocumentTypes(?User $user): bool
    {
        return self::canViewModule($user, self::ACTION_DOCUMENT_TYPES_DELETE);
    }

    /**
     * @return list<string>
     */
    public static function moduleLegalIds(?User $user, string $module): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isAdmin()) {
            return LegalEntity::query()
                ->orderBy('legal_id')
                ->pluck('legal_id')
                ->map(fn ($legalId) => (string) $legalId)
                ->values()
                ->all();
        }

        return $user->modulePermissions()
            ->where('module', $module)
            ->where('scope_type', 'legal')
            ->where('can_view', true)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($legalId) => (string) $legalId)
            ->values()
            ->all();
    }

    private static function hasModulePermission(?User $user, string $module): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->modulePermissions()
            ->where('module', $module)
            ->where('can_view', true)
            ->exists();
    }
}
