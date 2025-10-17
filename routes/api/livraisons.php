<?php
use Illuminate\Support\Facades\Route;
 
use App\Http\Controllers\Livraison\LivraisonShowController;
use App\Http\Controllers\Livraison\LivraisonUpdateController;
use App\Http\Controllers\Livraison\LivraisonValidationController;
use App\Http\Controllers\Livraison\LivraisonDeleteController;

Route::get('/livraisons/all', [LivraisonShowController::class, 'index']); 
Route::get('/livraisons/byId/{id}', [LivraisonShowController::class, 'show']);
Route::get('/livraisons/getLivraisonByCommandeNumero/{numero}', [LivraisonShowController::class, 'getLivraisonByCommandeNumero']);
Route::post('/livraisons/valider/{id}', [LivraisonValidationController::class, 'valider']); 
Route::put('/livraisons/updateById/{id}', [LivraisonUpdateController::class, 'update']);
Route::delete('/livraisons/deleteById/{id}', LivraisonDeleteController::class);



