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
            $t->string('marque')->nullable();
            $t->string('immatriculation', 60)->nullable()->unique();

            $t->foreignId('owner_contact_id')
                ->constrained('contacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $t->foreignId('livreur_contact_id')
                ->unique()
                ->constrained('contacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
