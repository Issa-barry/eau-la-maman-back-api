<?php
use Illuminate\Support\Facades\Route;

 use App\Http\Controllers\Packing\PackingDeleteController;
use App\Http\Controllers\Packing\PackingShowController;
use App\Http\Controllers\Packing\PackingUpdateController;
use App\Http\Controllers\Packing\PackingStoreController;
use App\Http\Controllers\Packing\PackingValidationController;

    Route::post('/packings/create', [PackingStoreController::class, 'store']);
    Route::get('/packings', [PackingShowController::class, 'index']);
    Route::get('/packings/all', [PackingShowController::class, 'index']);
    Route::get('/packings/getById/{id}', [PackingShowController::class, 'show']);
    Route::put('/packings/update/{id}', [PackingUpdateController::class, 'update']);
    Route::delete('/packings/deleteById/{id}', [PackingDeleteController::class, 'deleteById']);
    Route::put('/packings/{id}/valider', [PackingValidationController::class, 'valider']);


    
 /**********************************************************
 *   
 * Packing 
 * 
 * ********************************************************/


// use App\Http\Controllers\Packing\{
//     PackingShowController,
//     PackingUpdateController,
//     PackingDeleteController,
// };

// Route::prefix('packings')->middleware('auth:sanctum')->group(function () {
//     Route::get('/', PackingShowController::class);
//     Route::post('/', PackingStoreController::class);
//     Route::get('/{id}', PackingShowController::class);
//     Route::put('/{id}', PackingUpdateController::class);
//     Route::delete('/{id}', PackingDeleteController::class);
// });


// Route::get('/packings/', [PackingShowController::class, 'index']);
