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
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('facture_livraisons')->onDelete('cascade');
            $table->decimal('montant', 12, 2);
            $table->enum('mode_paiement', ['espèces', 'orange-money', 'dépot-banque'])->default('espèces');
            $table->date('date_encaissement')->default(now());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encaissements');
    }
};
