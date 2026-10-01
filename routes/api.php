<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\ApiDocumentationController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/openapi.yaml', ApiDocumentationController::class)->name('openapi');

Route::middleware([AuthenticateApiToken::class, 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/rooms', [ApiController::class, 'rooms'])->name('rooms.index');
    Route::get('/availability', [ApiController::class, 'availability'])->name('availability');
    Route::get('/reservations/{reservation}', [ApiController::class, 'reservation'])->name('reservations.show');
    Route::post('/reservations', [ApiController::class, 'storeReservation'])->name('reservations.store');
    Route::get('/clients/{client}', [ApiController::class, 'client'])->name('clients.show');
    Route::get('/payments/{payment}', [ApiController::class, 'payment'])->name('payments.show');
    Route::get('/payments', [ApiController::class, 'payments'])->name('payments.index');
    Route::post('/payments', [ApiController::class, 'storePayment'])->name('payments.store');
    Route::post('/payments/{payment}/confirm', [ApiController::class, 'confirmPayment'])->name('payments.confirm');
    Route::post('/payments/{payment}/void', [ApiController::class, 'voidPayment'])->name('payments.void');
    Route::post('/payments/{payment}/refund', [ApiController::class, 'refundPayment'])->name('payments.refund');
    Route::get('/pos/outlets', [ApiController::class, 'posOutlets'])->name('pos.outlets');
    Route::get('/pos/products', [ApiController::class, 'posProducts'])->name('pos.products');
    Route::get('/pos/orders', [ApiController::class, 'posOrders'])->name('pos.orders.index');
    Route::post('/pos/orders', [ApiController::class, 'storePosOrder'])->name('pos.orders.store');
    Route::get('/pos/orders/{order}', [ApiController::class, 'posOrder'])->name('pos.orders.show');
    Route::post('/pos/orders/{order}/void', [ApiController::class, 'voidPosOrder'])->name('pos.orders.void');
    Route::post('/pos/orders/{order}/refund', [ApiController::class, 'refundPosOrder'])->name('pos.orders.refund');
    Route::get('/pos/reports', [ApiController::class, 'posReport'])->name('pos.reports');
    Route::get('/finance/accounts', [ApiController::class, 'financeAccounts'])->name('finance.accounts');
    Route::get('/finance/transactions', [ApiController::class, 'financeTransactions'])->name('finance.transactions');
    Route::get('/finance/reports', [ApiController::class, 'financeReport'])->name('finance.reports');
    Route::get('/invoices/{invoice}', [ApiController::class, 'invoice'])->name('invoices.show');
    Route::get('/reports', [ApiController::class, 'hotelReport'])->name('reports.show');
    Route::get('/staff', [ApiController::class, 'staff'])->name('staff.index');
    Route::get('/staff/{staff}', [ApiController::class, 'staffMember'])->name('staff.show');
});
