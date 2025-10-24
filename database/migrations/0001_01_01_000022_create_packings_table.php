<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // 👇 remplace user_id par contact_id
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnUpdate()->restrictOnDelete();

            $table->date('date');
            $table->enum('shift', ['jour', 'nuit'])->default('jour');

            $table->enum('statut', ['brouillon', 'en_cours', 'validé', 'annulé'])->default('brouillon');

            // 👇 plus de lignes: on stocke le produit utilisé et la quantité totale
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('quantite_packed')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packings');
    }
};
