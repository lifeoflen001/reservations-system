<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::middleware([AuthenticateApiToken::class, 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/rooms', [ApiController::class, 'rooms'])->name('rooms.index');
    Route::get('/availability', [ApiController::class, 'availability'])->name('availability');
    Route::get('/reservations/{reservation}', [ApiController::class, 'reservation'])->name('reservations.show');
    Route::post('/reservations', [ApiController::class, 'storeReservation'])->name('reservations.store');
    Route::get('/clients/{client}', [ApiController::class, 'client'])->name('clients.show');
    Route::get('/payments/{payment}', [ApiController::class, 'payment'])->name('payments.show');
});
