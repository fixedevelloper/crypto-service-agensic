<?php

use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\NowPaymentsIpnController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Models\CryptoCurrency;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ValidationController;

/*
|--------------------------------------------------------------------------
| FINTECH FLOW ROUTES
|--------------------------------------------------------------------------
*/

// 🔥 STEP 1 - GET QUOTE (prix + frais)
Route::post('/quote', [QuoteController::class, 'generate']);

// 🔥 STEP 2 - VALIDATE ADDRESS
Route::post('/validate-address', [ValidationController::class, 'validate']);

// 🔥 STEP 3 - EXECUTE TRANSFER
Route::post('/transfers', [TransferController::class, 'transfer']);
Route::get('/transfers', [TransferController::class, 'userTransactions']);
// Créer un dépôt
Route::post('/payments/deposit', [PaymentController::class, 'deposit']);

// Vérifier le statut (polling)
Route::get('/payments/status/{reference}', [PaymentController::class, 'checkStatus']);

// 🔥 WEBHOOK BLOCKCHAIN
Route::post('/webhook/blockchain', [WebhookController::class, 'handle'])->name('nowpayments.ipn');
Route::post('/nowpayments/ipn', NowPaymentsIpnController::class)->name('nowpayments.payement.ipn');
Route::resource('beneficiaries', BeneficiaryController::class);
Route::get('/cryptos', [QuoteController::class, 'getCurrencyCrypto']);
