<?php

use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\User\Clients\CreateClientController;
use App\Http\Controllers\User\Clients\DeleteClientController;
use App\Http\Controllers\User\Clients\ShowClientController;
use App\Http\Controllers\User\Clients\UpdateClientController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\DeleteUserController;
use App\Http\Controllers\User\ShowUserController;
use App\Http\Controllers\User\updateUserController;
use App\Http\Controllers\User\UserStatutController;
use App\Http\Controllers\User\UserAffecterAgenceController;
use App\Http\Controllers\User\UserDesacfecterAgenceController;
use App\Http\Controllers\User\Employe\EmployeCreateController;
use App\Http\Controllers\User\Employes\CreateEmployeController;

Route::prefix('users')->name('users.')->group(function () {
        Route::post('/clients/create', [CreateClientController::class, 'store'])->name('clients.store')->middleware('throttle:20,1'); 
        Route::get('/clients/all', [ShowClientController::class, 'index']);
        Route::get('/clients/showByReference/{id}', [ShowClientController::class, 'showByReference']);
        Route::put('/users/clients/updateById/{id}', [UpdateClientController::class, 'update']);
        Route::delete('/clients/deleteByReference/{reference}', [DeleteClientController::class, 'destroy']);

        // Avec Sanctum (connexion obligatoire )
        Route::middleware(['auth:sanctum','throttle:60,1'])->group(function () {
        Route::post('/employes/create', [CreateEmployeController::class, 'store'])->name('employes.store');

        Route::get('/all', [ShowUserController::class, 'index'])->name('index');
        Route::get('/getById/{id}', [ShowUserController::class, 'getById'])->name('show')->whereNumber('id');
        Route::put('/updateById/{id}', [updateUserController::class, 'updateById'])->name('update')->whereNumber('id');
        Route::delete('/deleteById/{id}', [DeleteUserController::class, 'delateById'])->name('delete')->whereNumber('id');

        Route::patch('/{id}/statutUpdate', [UserStatutController::class, 'updateStatut'])->name('statut.update')->whereNumber('id');

        Route::post('/affecterByReference/{id}', [UserAffecterAgenceController::class, 'affecterParReferenceAgence'])->name('affect.ref')->whereNumber('id');
        Route::post('/affecter-agence/{id}', [UserAffecterAgenceController::class, 'affecterAgence'])->name('affect')->whereNumber('id');
        Route::delete('/desaffecter-agence/{id}', [UserDesacfecterAgenceController::class, 'desaffecterAgence'])->name('desaffect')->whereNumber('id');
    
    
    
         /**********************************************************
             *   
             * USER  
             * 
             * ********************************************************/
            Route::post('/users/affecterByReference/{id}', [UserAffecterAgenceController::class, 'affecterParReferenceAgence']);
            Route::post('/users/affecter-agence/{id}', [UserAffecterAgenceController::class, 'affecterAgence']);
            Route::delete('/users/desaffecter-agence/{id}', [UserDesacfecterAgenceController::class, 'desaffecterAgence']);
            Route::get('/users/all', [ShowUserController::class, 'index']);
            Route::get('/users/getById/{id}', [ShowUserController::class, 'getById']);
            Route::put('/users/updateById/{id}', [updateUserController::class, 'updateById']);
            Route::delete('/users/delateById/{id}', [DeleteUserController::class, 'delateById']);

            Route::patch('/users/{id}/statutUpdate', [UserStatutController::class, 'updateStatut']);

            // ME 
             Route::get('/me', [MeController::class, 'show'])->name('users.me');

    });
});
