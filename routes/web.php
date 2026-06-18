<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\SchedulerController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminAuthController::class, 'create'])->name('login');
Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');

Route::middleware('admin.session')->group(function (): void {
    Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('bank-accounts.index');
    });

    Route::resource('document-types', DocumentTypeController::class)->except('show');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('bank-accounts/import', [BankAccountController::class, 'import'])->name('bank-directories.import');
    Route::get('bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::get('scheduler', [SchedulerController::class, 'index'])->name('scheduler.index');
    Route::post('scheduler/run/{task}', [SchedulerController::class, 'run'])->name('scheduler.run');
});
