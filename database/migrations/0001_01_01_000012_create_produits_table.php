<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();

            // Code produit unique (ex: UUID ou code-barres)
            $table->string('code')->unique();

            // Informations principales
            $table->string('nom');
            $table->enum('type', ['vente', 'achat', 'all'])->index(); // type fonctionnel
            $table->string('categorie')->nullable();                  // libre (riz, eau, etc.)

            // Prix en GNF (entiers)
            $table->bigInteger('prix_vente')->nullable();
            $table->bigInteger('prix_usine')->nullable();
            $table->bigInteger('prix_achat')->nullable();
            $table->bigInteger('cout')->nullable();

            // Stock et statut
            $table->integer('quantite_stock')->default(0);
            $table->enum('statut', ['disponible', 'rupture', 'archivé'])->default('disponible');

            // Autres infos
            $table->string('image')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
