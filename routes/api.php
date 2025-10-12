  <?php

use App\Http\Controllers\Payment\Stripe\PaymentIntentStoreController;
use App\Http\Controllers\Payment\Stripe\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/users.php';
    require __DIR__.'/api/factures.php';
    require __DIR__.'/api/produits.php';
    require __DIR__.'/api/packings.php';
    require __DIR__.'/api/commandes.php';
    require __DIR__.'/api/livraisons.php';
    require __DIR__.'/api/agences.php';
    require __DIR__.'/api/devises.php';
    require __DIR__.'/api/permissions.php';
    require __DIR__.'/api/roles.php';
});

