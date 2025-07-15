<?php

use App\Http\Controllers\Agence\AgenceCreateController;
use App\Http\Controllers\Agence\AgenceDeleteController;
use App\Http\Controllers\Agence\AgenceShowController;
use App\Http\Controllers\Agence\AgenceUpdateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Auth\Events\Verified;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\DeviseController;
use App\Http\Controllers\AgenceController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\User\DeleteUserController;
use App\Http\Controllers\User\ShowUserController;
use App\Http\Controllers\User\updateUserController;
use App\Http\Controllers\User\CreateUserController;

use App\Http\Controllers\Devises\DeviseCreateController;
use App\Http\Controllers\Devises\DeviseDeleteController;
use App\Http\Controllers\Devises\DeviseShowController;
use App\Http\Controllers\Devises\DeviseUpdateController; 
use App\Http\Controllers\Permissions\PermissionController;
use App\Http\Controllers\Roles\RoleAssigneController;
use App\Http\Controllers\Roles\RoleCreateController;
use App\Http\Controllers\Roles\RoleDeleteController;
use App\Http\Controllers\Roles\RoleListeUsersDuRoleController;
use App\Http\Controllers\Roles\RoleShowController;
use App\Http\Controllers\Roles\RoleUpdateController; 
use App\Http\Controllers\Roles\RolePermissions\RolePermissionsAssignPermissionController;
use App\Http\Controllers\Roles\RolePermissions\RolePermissionsRevokePermissionController;
use App\Http\Controllers\Roles\RolePermissions\RolePermissionsShowController; 
use App\Http\Controllers\User\UserAffecterAgenceController;
use App\Http\Controllers\User\UserDesacfecterAgenceController;
use App\Http\Controllers\User\UserStatutController;
 use App\Http\Controllers\Agence\AgenceStatutController;
use App\Http\Controllers\Commande\CommandeDeleteController;
use App\Http\Controllers\Produit\DeleteProduitController;
use App\Http\Controllers\Produit\ShowProduitController;
use App\Http\Controllers\Produit\UpdateProduitController;
use App\Http\Controllers\Produit\CreateProduitController;
use App\Http\Controllers\Commande\CommandeShowController;
use App\Http\Controllers\Commande\CommandeStatutController;
use App\Http\Controllers\Commande\CommandeStoreController;
use App\Http\Controllers\Commande\CommandeUpdateController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:sanctum');
Route::post('/ResetPassword', [AuthController::class, 'resetPassword']);
Route::post('/sendResetPasswordLink', [AuthController::class, 'sendResetPasswordLink']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
// Route::post('resend-verification-email', [AuthController::class, 'resendVerificationEmail']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/check-token-header', [AuthController::class, 'checkTokenInHeader']);
});


/**********************************************************
 *   
 * USER  
 * 
 * ********************************************************/
Route::post('/users/affecterByReference/{id}', [UserAffecterAgenceController::class, 'affecterParReferenceAgence']);
Route::post('/users/affecter-agence/{id}', [UserAffecterAgenceController::class, 'affecterAgence']);
Route::delete('/users/desaffecter-agence/{id}', [UserDesacfecterAgenceController::class, 'desaffecterAgence']);
Route::post('/users/create', [CreateUserController::class, 'store']);
Route::get('/users/all', [ShowUserController::class, 'index']);
Route::get('/users/getById/{id}', [ShowUserController::class, 'getById']);
Route::put('/users/updateById/{id}', [updateUserController::class, 'updateById']);
Route::delete('/users/delateById/{id}', [DeleteUserController::class, 'delateById']);



Route::patch('/users/{id}/statutUpdate', [UserStatutController::class, 'updateStatut']);



/**********************************************************
 *   
 * AGENCE 
 * 
 * ********************************************************/
Route::post('/agences/create', [AgenceCreateController::class, 'store']);
Route::get('/agences/all', [AgenceShowController::class, 'index']);
Route::get('/agences/getById/{id}', [AgenceShowController::class, 'show']);
Route::get('/agences/getByReference/{reference}', [AgenceShowController::class, 'showByReference']);
Route::put('/agences/updateById/{id}', [AgenceUpdateController::class, 'updateById']);
Route::delete('/agences/deleteById/{id}', [AgenceDeleteController::class, 'deleteById']);



Route::patch('/agences/{id}/statutUpdate', [AgenceStatutController::class, 'updateStatut']);

 

/**********************************************************
 *   
 * DEVISE 
 * 
 * ********************************************************/
Route::post('/devises/create', [DeviseCreateController::class, 'store']);
Route::get('/devises/all', [DeviseShowController::class, 'index']);
Route::get('/devises/getById/{id}', [DeviseShowController::class, 'getById']);
Route::put('/devises/updateById/{id}', [DeviseUpdateController::class, 'updateById']);
Route::delete('/devises/deleteById/{id}', [DeviseDeleteController::class, 'deleteById']);

/**********************************************************
 *   
 * PERMISSIONS 
 * 
 * ********************************************************/
Route::get('/permissions', [PermissionController::class, 'index']);
Route::post('/permissions', [PermissionController::class, 'create']);
Route::get('/permissions/{id}', [PermissionController::class, 'show']);
Route::put('/permissions/{id}', [PermissionController::class, 'update']);
Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
//Role permissions : 
Route::post('roles/{roleId}/assign-permissions', [RolePermissionsAssignPermissionController::class, 'assignPermissionsToRole']); // Assigner une ou plusieurs permissions à un rôle
Route::post('roles/{roleId}/revoke-permission', [RolePermissionsRevokePermissionController::class, 'revokePermissionFromRole']); // Retirer une permission d'un rôle
Route::get('/roles-permissions-liste', [RolePermissionsShowController::class, 'listRolesPermissions']); // Lister rôles et permissions
Route::get('/role/{roleId}/oneRolePermissions', [RolePermissionsShowController::class, 'getRolePermissions']); // Route pour récupérer les permissions d'un rôle spécifique


 

/**********************************************************
 *   
 * ROLE 
 * 
 * ********************************************************/
//partie 1 :
Route::post('/roles/create', [RoleCreateController::class, 'store']);
Route::get('/roles/all', [RoleShowController::class, 'index']);
Route::get('/roles/getById/{id}', [RoleShowController::class, 'getById']);
Route::get('/roles/getByName/{name}', [RoleShowController::class, 'getByName']);
Route::put('/roles/updateById/{id}', [RoleUpdateController::class, 'updateById']);
Route::delete('/roles/deleteById/{id}', [RoleDeleteController::class, 'destroy']);
 
Route::post('/roles/assigne-role', [RoleAssigneController::class, 'assigneRole']);
Route::get('/roles/{id}/all-users-du-role', [RoleListeUsersDuRoleController::class, 'checkRoleUsers']);// fonctionne pas
//Revoke ne marche pas
// Route::post('users/{userId}/revoke-role', [RoleController::class, 'revokeRole']);// Retirer un rôle d'un utilisateur
 

 
 /**********************************************************
 *   
 * PRODUIT 
 * 
 * ********************************************************/

 
Route::post('/produits/create', [CreateProduitController::class, 'store']);
Route::get('/produits/all', [ShowProduitController::class, 'index']);
Route::get('/produits/getById/{id}', [ShowProduitController::class, 'getById']);
Route::put('/produits/updateById/{id}', [UpdateProduitController::class, 'update']);
Route::delete('/produits/deleteById/{id}', [DeleteProduitController::class, 'deleteById']);

 /**********************************************************
 *   
 * Commande 
 * 
 * ********************************************************/

 
Route::prefix('commandes')->group(function () {
//creation et modification 
Route::post('/create', [CommandeStoreController::class, 'store']);
Route::put('/updateByNumero/{numero}', [CommandeUpdateController::class, 'updateByNumero']);
// Affichage
Route::get('/all', [CommandeShowController::class, 'all']);
Route::get('/showByNumero/{numero}', [CommandeShowController::class, 'showByNumero']); // /api/commandes/numero/CO00000001
// Validation & statut
Route::patch('/validation/{numero}', [CommandeStatutController::class, 'valider']);
Route::patch('/{numero}/majStatut', [CommandeStatutController::class, 'changerStatut']);
// Supression
Route::delete('/deleteById/{id}', [CommandeDeleteController::class, 'deleteById']);
Route::delete('/deleteByNumero/{numero}', [CommandeDeleteController::class, 'deleteByNumero']);

});

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

 use App\Http\Controllers\Packing\PackingDeleteController;
use App\Http\Controllers\Packing\PackingShowController;
use App\Http\Controllers\Packing\PackingUpdateController;
use App\Http\Controllers\Packing\PackingStoreController;

    Route::post('/packings/create', [PackingStoreController::class, 'store']);
    Route::get('/packings', [PackingShowController::class, 'index']);
    Route::get('/packings/all', [PackingShowController::class, 'index']);
    Route::get('/packings/{id}', [PackingShowController::class, 'show']);
    Route::put('/packings/update/{id}', [PackingUpdateController::class, 'update']);
    Route::delete('/packings/{id}', [PackingDeleteController::class, 'destroy']);
 
 /**********************************************************
 *   
 * Livraison 
 * 
 * ********************************************************/

// use App\Http\Controllers\Livraison\LivraisonShowController;

// Route::prefix('livraisons')->group(function () {
//     // Route::post('/create', [LivraisonController::class, 'store']);
//     Route::get('/all', [LivraisonShowController::class, 'index']);
//      Route::get('/', [LivraisonShowController::class, 'index']);
//  });



