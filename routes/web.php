<?php

use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('bank-accounts.index');
});

Route::resource('document-types', DocumentTypeController::class)->except('show');
Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
Route::post('bank-accounts/import', [BankAccountController::class, 'import'])->name('bank-directories.import');
Route::get('bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
