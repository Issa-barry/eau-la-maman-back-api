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
        Schema::create('facture_livraisons', function (Blueprint $table) {
             $table->id();
             $table->string('numero')->unique();
             $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->decimal('montant_du', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->enum('statut', ['brouillon', 'partiel', 'payé', 'impayé'])->default('brouillon');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facture_livraisons');
    }
};
