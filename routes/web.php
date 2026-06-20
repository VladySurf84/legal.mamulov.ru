<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\MoneyLayerController;
use App\Http\Controllers\OzonBankStatementController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\VatBookController;
use App\Http\Controllers\VatLayerController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminAuthController::class, 'create'])->name('login');
Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');
Route::get('auth/google', [AdminAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('auth/google/callback', [AdminAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::middleware('admin.session')->group(function (): void {
    Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('bank-accounts.index');
    });

    Route::resource('document-types', DocumentTypeController::class)->except('show');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('bank-accounts/import', [BankAccountController::class, 'import'])->name('bank-directories.import');
    Route::get('bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::get('ozon-bank-statements/create', [OzonBankStatementController::class, 'create'])->name('ozon-bank-statements.create');
    Route::post('ozon-bank-statements', [OzonBankStatementController::class, 'store'])->name('ozon-bank-statements.store');
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
