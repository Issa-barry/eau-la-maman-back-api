<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Commande\CommandeShowController;
use App\Http\Controllers\Commande\CommandeStatutController;
use App\Http\Controllers\Commande\CommandeStoreController;
use App\Http\Controllers\Commande\CommandeUpdateController;
use App\Http\Controllers\Commande\CommandeDeleteController;
use App\Http\Controllers\Commande\CommandeValiderController;

Route::prefix('commandes')->group(function () {
//creation et modification 
Route::post('/create', [CommandeStoreController::class, 'store']);
Route::put('/updateByNumero/{numero}', [CommandeUpdateController::class, 'updateByNumero']);
// Affichage
Route::get('/', [CommandeShowController::class, 'index']); 
Route::get('/showByNumero/{numero}', [CommandeShowController::class, 'showByNumero']); // /api/commandes/numero/CO00000001
// Validation & statut
Route::patch('/validation/{numero}', [CommandeValiderController::class, 'valider']);
Route::patch('/{numero}/majStatut', [CommandeStatutController::class, 'changerStatut']);
// Supression
Route::delete('/deleteById/{id}', [CommandeDeleteController::class, 'deleteById']);
Route::delete('/deleteByNumero/{numero}', [CommandeDeleteController::class, 'deleteByNumero']);
// Filtre 
Route::post('/search', [CommandeShowController::class, 'index']);
});
