<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            //  Référence fonctionnelle unique (ex: CT-2025-AB12CD)
            $table->string('reference', 32)->unique()->index();

            // Infos d’identité
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('phone', 30)->unique();
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();

            // Typage métier
            $table->enum('type', ['client_specifique','livreur','proprietaire','packing'])->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
