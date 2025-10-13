<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Référence fonctionnelle unique (LLNNNN, ex: AB1234)
            $table->string('reference', 6)->unique()->index();

            // Infos d’identité
            $table->string('nom')->nullable()->index();
            $table->string('prenom')->nullable()->index();
            $table->string('phone', 30)->unique();
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();

            // Typage métier
            $table->enum('type', ['client_specifique','livreur','proprietaire','packing'])->index();

            // Lien optionnel vers un véhicule (pour les livreurs)
            $table->foreignId('vehicule_id')
                ->nullable()
                ->constrained('vehicules')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
