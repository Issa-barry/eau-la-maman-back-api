<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Vehicules\VehiculesIndexShowController;
use App\Http\Controllers\Vehicules\VehiculeStoreController;
use App\Http\Controllers\Vehicules\VehiculeUpdateController;
use App\Http\Controllers\Vehicules\VehiculeDeleteController;

Route::prefix('vehicules')->group(function () {
    Route::get('/',        [VehiculesIndexShowController::class, 'index']);
    Route::get('/all',    [VehiculesIndexShowController::class, 'index']);
    Route::get('/{id}',   [VehiculesIndexShowController::class, 'show']);

    Route::post('/create',       VehiculeStoreController::class);
    // Route::match(['put','patch'], '/updateById/{id}', VehiculeUpdateController::class);
    Route::delete('/{id}', VehiculeDeleteController::class);
});


