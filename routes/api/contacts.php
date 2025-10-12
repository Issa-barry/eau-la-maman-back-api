<?php

use App\Http\Controllers\Contacts\ContactIndexShowController;
use Illuminate\Support\Facades\Route;
 

Route::middleware(['auth:sanctum'])
    ->prefix('contacts')->name('contacts.')
    ->group(function () {
        // Route::post('/create', [ContactCreateController::class, 'store'])->name('store');
        Route::get('/all', [ContactIndexShowController::class, 'index'])->name('index');
        Route::get('/{id}', [ContactIndexShowController::class, 'show'])->name('show');
    });
