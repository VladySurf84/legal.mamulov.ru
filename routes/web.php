<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\ElectronicSignatureController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\HhResumeController;
use App\Http\Controllers\HhBrowserCapturePageController;
use App\Http\Controllers\InternalApiDocsController;
use App\Http\Controllers\KassaController;
use App\Http\Controllers\LegalEntityController;
use App\Http\Controllers\MoneyLayerController;
use App\Http\Controllers\BankStatementImportController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImpersonationController;
use App\Http\Controllers\UserUiSettingController;
use App\Http\Controllers\VatBookController;
use App\Http\Controllers\VatLayerController;
use App\Support\UserAccess;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminAuthController::class, 'create'])->name('login');
Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');
Route::get('auth/google', [AdminAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('auth/google/callback', [AdminAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::middleware('admin.session')->group(function (): void {
    Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        $route = UserAccess::firstViewableRoute(auth()->user());

        abort_unless($route !== null, 403);

        return redirect()->route($route);
    });

    Route::resource('document-types', DocumentTypeController::class)->except('show');
    Route::get('legal-entities', [LegalEntityController::class, 'index'])->name('legal-entities.index');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/impersonate', [UserImpersonationController::class, 'store'])->name('users.impersonate');
    Route::post('impersonation/stop', [UserImpersonationController::class, 'destroy'])->name('users.impersonation.stop');
    Route::get('user-access', [UserAccessController::class, 'index'])->name('user-access.index');
    Route::put('user-access/{user}', [UserAccessController::class, 'update'])->name('user-access.update');
    Route::post('legal-entity-context', [LegalEntityController::class, 'updateContext'])->name('legal-entity-context.update');
    Route::get('ui-settings', [UserUiSettingController::class, 'show'])->name('ui-settings.show');
    Route::put('ui-settings', [UserUiSettingController::class, 'update'])->name('ui-settings.update');
    Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
    Route::post('exchange-rates/sync', [ExchangeRateController::class, 'sync'])->name('exchange-rates.sync');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('kassa', [KassaController::class, 'index'])->name('kassa.index');
    Route::post('kassa', [KassaController::class, 'store'])->name('kassa.store');
    Route::put('kassa/{kassaId}', [KassaController::class, 'update'])->name('kassa.update');
    Route::delete('kassa/{kassaId}', [KassaController::class, 'destroy'])->name('kassa.destroy');
    Route::post('kassa/rebuild', [KassaController::class, 'rebuild'])->name('kassa.rebuild');
    Route::get('electronic-signatures', [ElectronicSignatureController::class, 'index'])->name('electronic-signatures.index');
    Route::post('electronic-signatures/import', [ElectronicSignatureController::class, 'import'])->name('electronic-signatures.import');
    Route::get('hh/browser-captures', [HhBrowserCapturePageController::class, 'index'])->name('hh-browser-captures.index');
    Route::get('hh/browser-captures/{captureId}', [HhBrowserCapturePageController::class, 'show'])->whereNumber('captureId')->name('hh-browser-captures.show');
    Route::get('hh/resumes', [HhResumeController::class, 'index'])->name('hh-resumes.index');
    Route::post('hh/resumes/sync', [HhResumeController::class, 'sync'])->name('hh-resumes.sync');
    Route::delete('hh/resumes/{negotiationId}', [HhResumeController::class, 'destroy'])->whereNumber('negotiationId')->name('hh-resumes.destroy');
    Route::get('hh/oauth/redirect', [HhResumeController::class, 'redirect'])->name('hh.oauth.redirect');
    Route::get('hh/oauth/callback', [HhResumeController::class, 'callback'])->name('hh.oauth.callback');
    Route::get('internal-api-docs', [InternalApiDocsController::class, 'index'])->name('internal-api-docs.index');
    Route::get('internal-api-docs/openapi.json', [InternalApiDocsController::class, 'spec'])->name('internal-api-docs.spec');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('bank-accounts/import', [BankAccountController::class, 'import'])->name('bank-directories.import');
    Route::get('bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::post('bank-transactions/sync', [BankTransactionController::class, 'sync'])->name('bank-transactions.sync');
    Route::get('counterparties', [CounterpartyController::class, 'index'])->name('counterparties.index');
    Route::post('counterparties/rebuild-links', [CounterpartyController::class, 'rebuildLinks'])->name('counterparties.rebuild-links');
    Route::get('counterparties/{contractorInn}', [CounterpartyController::class, 'show'])
        ->where('contractorInn', '[0-9]{10,12}')
        ->name('counterparties.show');
    Route::post('counterparties/{contractorInn}/opening-balances', [CounterpartyController::class, 'storeOpeningBalance'])
        ->where('contractorInn', '[0-9]{10,12}')
        ->name('counterparties.opening-balances.store');
    Route::delete('counterparties/{contractorInn}/opening-balances/{openingBalanceId}', [CounterpartyController::class, 'destroyOpeningBalance'])
        ->where('contractorInn', '[0-9]{10,12}')
        ->whereNumber('openingBalanceId')
        ->name('counterparties.opening-balances.destroy');
    Route::get('bank-statement-imports/create', fn () => redirect()->route('bank-transactions.index'));
    Route::post('bank-statement-imports', [BankStatementImportController::class, 'store'])->name('bank-statement-imports.store');
    Route::get('ozon-bank-statements/create', fn () => redirect()->route('bank-transactions.index'));
    Route::post('ozon-bank-statements', [BankStatementImportController::class, 'store'])->name('ozon-bank-statements.store');
    Route::get('money-layer', [MoneyLayerController::class, 'index'])->name('money-layer.index');
    Route::post('money-layer/rebuild', [MoneyLayerController::class, 'rebuild'])->name('money-layer.rebuild');
    Route::get('vat-books', [VatBookController::class, 'index'])->name('vat-books.index');
    Route::get('vat-book-entries', [VatBookController::class, 'entries'])->name('vat-book-entries.index');
    Route::post('vat-books', [VatBookController::class, 'store'])->name('vat-books.store');
    Route::get('vat-layer', [VatLayerController::class, 'index'])->name('vat-layer.index');
    Route::post('vat-layer/rebuild', [VatLayerController::class, 'rebuild'])->name('vat-layer.rebuild');
    Route::post('vat-layer/rebuild-bank', [VatLayerController::class, 'rebuildBank'])->name('vat-layer.rebuild-bank');
    Route::get('scheduler', [SchedulerController::class, 'index'])->name('scheduler.index');
    Route::post('scheduler/run/{task}', [SchedulerController::class, 'run'])->name('scheduler.run');
});
