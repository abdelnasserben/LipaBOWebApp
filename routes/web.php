<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackofficeController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
// Mandatory first-login password setup (spec §3.1a). Reachable only mid-flow,
// gated by the single-use setup token held in the session.
Route::get('/password-setup', [AuthController::class, 'showPasswordSetup'])->name('password-setup');
Route::post('/password-setup', [AuthController::class, 'passwordSetup'])->name('password-setup.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Backoffice Routes
Route::middleware(['backoffice.auth'])->group(function () {
    Route::get('/', [BackofficeController::class, 'dashboard'])->name('dashboard');
    Route::get('/customers', [BackofficeController::class, 'customers'])->name('customers');
    Route::get('/customers/{id}/kyc', [BackofficeController::class, 'customerKyc'])->name('customers.kyc');
    Route::get('/kyc-documents/{documentId}/file', [BackofficeController::class, 'downloadKycDocument'])->name('kyc-documents.file');
    Route::get('/agents', [BackofficeController::class, 'agents'])->name('agents');
    Route::get('/agents/{id}/kyc', [BackofficeController::class, 'agentKyc'])->name('agents.kyc');
    Route::get('/merchants', [BackofficeController::class, 'merchants'])->name('merchants');
    Route::get('/merchants/{id}/kyc', [BackofficeController::class, 'merchantKyc'])->name('merchants.kyc');
    Route::get('/{ownerType}/kyc-documents/{documentId}/file', [BackofficeController::class, 'downloadActorKycDocument'])
        ->whereIn('ownerType', ['agents', 'merchants'])
        ->name('actor-kyc-documents.file');
    Route::get('/transactions', [BackofficeController::class, 'transactions'])->name('transactions');
    Route::get('/wallets', [BackofficeController::class, 'wallets'])->name('wallets');
    Route::get('/approvals', [BackofficeController::class, 'approvals'])->name('approvals');
    Route::get('/audit', [BackofficeController::class, 'audit'])->name('audit');
    Route::get('/reconciliation', [BackofficeController::class, 'reconciliation'])->name('reconciliation');
    Route::get('/reports', [BackofficeController::class, 'reports'])->name('reports');
    Route::get('/rules-limits', [BackofficeController::class, 'rulesLimits'])->name('rules-limits');
    Route::get('/service-providers', [BackofficeController::class, 'serviceProviders'])->name('service-providers');
    Route::get('/bill-payments', [BackofficeController::class, 'billPayments'])->name('bill-payments');
    Route::get('/bill-payments/{id}/proof', [BackofficeController::class, 'downloadBillPaymentProof'])->name('bill-payments.proof');
    Route::get('/cards', [BackofficeController::class, 'cards'])->name('cards');
    Route::get('/terminals', [BackofficeController::class, 'terminals'])->name('terminals');
    Route::get('/treasury', [BackofficeController::class, 'treasury'])->name('treasury');
    Route::get('/users', [BackofficeController::class, 'users'])->name('users');
});
