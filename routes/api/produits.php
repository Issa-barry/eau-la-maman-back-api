<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Produit\CreateProduitController;
use App\Http\Controllers\Produit\DeleteProduitController;
use App\Http\Controllers\Produit\ShowProduitController;
use App\Http\Controllers\Produit\UpdateProduitController;

 
Route::post('/produits/create', [CreateProduitController::class, 'store']);
Route::get('/produits/all', [ShowProduitController::class, 'index']);
Route::get('/produits/getById/{id}', [ShowProduitController::class, 'getById']);
Route::put('/produits/updateById/{id}', [UpdateProduitController::class, 'update']);
Route::delete('/produits/deleteById/{id}', [DeleteProduitController::class, 'deleteById']);