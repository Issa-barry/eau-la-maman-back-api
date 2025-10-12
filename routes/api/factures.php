<?php
use Illuminate\Support\Facades\Route;





    /**********************************************************
    *   
    * Dashboard 
    * 
    * ********************************************************/

use App\Http\Controllers\Factures\{
    FactureLivraisonIndexController,
    FactureLivraisonUpdateController,
    FactureLivraisonDeleteController,
    FactureLivraisonCreateController,
    FactureLivraisonValidateController
};

Route::prefix('factures')->group(function () {
    Route::get('/', [FactureLivraisonIndexController::class, 'index']);
    Route::get('/all', [FactureLivraisonIndexController::class, 'index']);
    Route::get('/getByID/{id}', [FactureLivraisonIndexController::class, 'show']);
    Route::put('/updateById/{id}', [FactureLivraisonUpdateController::class, 'update']);
    Route::delete('/deleteById/{id}', [FactureLivraisonDeleteController::class, 'destroy']);
    Route::post('/create', [FactureLivraisonCreateController::class, 'store']);
    Route::post('/{id}/valider', [FactureLivraisonValidateController::class, 'validateDraft']);

});

 
 /**********************************************************
 *   
 * Encaissement 
 * 
 * ********************************************************/
use App\Http\Controllers\Encaissement\{
    EncaissementIndexController,
    EncaissementShowController,
    EncaissementStoreController,
    EncaissementUpdateController,
    EncaissementDeleteController
};

Route::prefix('encaissements')->group(function () {
    Route::get('/', [EncaissementIndexController::class, 'index']);
    Route::get('/all', [EncaissementIndexController::class, 'index']);
    Route::get('/{id}', [EncaissementShowController::class, 'show']);
    Route::post('/create', [EncaissementStoreController::class, 'store']);
    Route::put('/updateById/{id}', [EncaissementUpdateController::class, 'update']);
    Route::delete('/{id}', [EncaissementDeleteController::class, 'destroy']);
});


    /**********************************************************
    *   
    * Dashboard 
    * 
    * ********************************************************/
use App\Http\Controllers\Dashboard\StatistiqueCommandeController;
use App\Http\Controllers\Dashboard\StatistiqueEncaissementController;
use App\Http\Controllers\Dashboard\StatistiqueFactureController;
use App\Http\Controllers\Dashboard\StatistiqueLivraisonController;
use App\Http\Controllers\Dashboard\StatistiqueUserController;

Route::prefix('dashboards')->group(function () {
    Route::get('/statistiques/encaissements', [StatistiqueEncaissementController::class, 'index']);
    Route::get('/statistiques/factures', [StatistiqueFactureController::class, 'index']);
    Route::get('/statistiques/commandes', [StatistiqueCommandeController::class, 'index']);
    Route::get('/statistiques/livraisons', [StatistiqueLivraisonController::class, 'index']);
    Route::get('/statistiques/users', [StatistiqueUserController::class, 'index']);
});

 