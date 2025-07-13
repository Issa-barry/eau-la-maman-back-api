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
            $table->string('code')->unique(); // code unique du produit (ex: code-barre)
            $table->string('nom');
            $table->decimal('prix_vente', 15, 2);
            $table->integer('quantite_stock');
            $table->enum('categorie', ['vente', 'achat', 'all']);
            $table->decimal('prix_achat', 15, 2)->nullable();
            $table->decimal('cout', 15, 2)->nullable();
            $table->string('image')->nullable();
             $table->enum('statut', ['disponible', 'rupture', 'archivé'])->default('disponible');
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
