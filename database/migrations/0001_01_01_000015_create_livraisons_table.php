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
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // Référence unique de la livraison
            $table->foreignId('commande_id')->constrained()->onDelete('cascade'); // Référence à la commande
            $table->integer('quantite_livree'); // Quantité livrée
            $table->date('date_livraison'); // Date de livraison
            $table->timestamps(); // Date de création et de mise à jour
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};
