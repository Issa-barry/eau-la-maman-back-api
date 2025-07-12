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

use App\Http\Controllers\User\UserStatutController;

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

use App\Http\Controllers\Agence\AgenceStatutController;

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
// Route::apiResource('roles', RoleController::class);

Route::post('/roles/assigne-role', [RoleAssigneController::class, 'assigneRole']);
Route::get('/roles/{id}/all-users-du-role', [RoleListeUsersDuRoleController::class, 'checkRoleUsers']);// fonctionne pas
//Revoke ne marche pas
// Route::post('users/{userId}/revoke-role', [RoleController::class, 'revokeRole']);// Retirer un rôle d'un utilisateur
 

 
 