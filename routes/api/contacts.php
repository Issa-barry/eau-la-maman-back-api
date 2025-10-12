<?php

use App\Http\Controllers\Contacts\ContactCreateController;
use App\Http\Controllers\Contacts\ContactDeleteController;
use App\Http\Controllers\Contacts\ContactShowController;
use App\Http\Controllers\Contacts\ContactUpdateController;
use Illuminate\Support\Facades\Route;
 

Route::middleware(['auth:sanctum'])
    ->prefix('contacts')->name('contacts.')->group(function () {
        Route::post('/create', [ContactCreateController::class, 'store'])->name('store');
        Route::get('/all', [ContactShowController::class, 'index'])->name('index');
        Route::get('/{id}', [ContactShowController::class, 'getById'])->name('getById');
        Route::put('/updateById/{id}', [ContactUpdateController::class, 'updateById'])->name('updateById');
        Route::delete('/deleteById/{id}', [ContactDeleteController::class, 'deleteById'])->name('deleteById');
    });
  