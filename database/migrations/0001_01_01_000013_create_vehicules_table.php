// database/migrations/xxxx_xx_xx_xxxxxx_create_vehicules_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $t) {
            $t->id();

            $t->enum('type', ['camion','fourgonette','tricycle'])->index();
            $t->string('immatriculation', 60)->unique();
            $t->string('nom', 120)->nullable()->index();

            // Propriétaire   
            $t->string('nom_proprietaire', 120);
            $t->string('prenom_proprietaire', 120);
            $t->string('phone_proprietaire', 30);

            // Livreur  
            $t->string('nom_livreur', 120);
            $t->string('prenom_livreur', 120);
            $t->string('phone_livreur', 30);

            // Statut
            $t->enum('statut', ['active', 'attente', 'bloque', 'archive'])->default('active');


            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
