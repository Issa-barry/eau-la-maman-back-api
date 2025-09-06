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
        Schema::create('commandes', function (Blueprint $table) {
           $table->id();
            $table->string('numero')->unique();
            $table->foreignId('contact_id')->constrained('users')->onDelete('cascade');
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->integer('qte_total')->default(0);
            $table->decimal('reduction', 8, 2)->default(0); 
            $table->enum('statut', [
                'brouillon',
                'annulé',
                'livraison_en_cours',
                'livré',
                'cloturé'
            ])->default('brouillon');
            $table->timestamps();
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
