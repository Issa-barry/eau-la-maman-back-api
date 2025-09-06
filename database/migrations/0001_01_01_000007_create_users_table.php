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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 5)->unique();              // Généré automatiquement
            $table->string('nom_complet');                         // Obligatoire
            $table->string('phone')->unique();                     // Obligatoire + unique
            $table->string('email')->nullable()->unique();         // Facultatif pour les clients

            $table->timestamp('email_verified_at')->nullable();

            $table->foreignId('adresse_id')
                ->nullable()
                ->constrained('adresses')
                ->onDelete('cascade');                             // Adresse facultative

            $table->enum('statut', ['active', 'attente', 'bloque', 'archive'])->default('attente');

            $table->date('date_naissance')->nullable();
            $table->enum('civilite', ['Mr', 'Mme', 'Mlle', 'Autre'])->default('Autre');

            $table->string('password')->nullable();                // Facultatif pour les clients

           $table->foreignId('role_id')
            ->nullable()
            ->default(2) // 2 = client
            ->constrained('roles')
            ->onDelete('set null');
                                     // Rôle client par défaut

            $table->unsignedBigInteger('agence_id')->nullable();   // Agence liée si employé

            // 'specifique' (pas de véhicule) | 'vehicule' (un véhicule)
            $table->enum('type_client', ['specifique', 'vehicule'])->default('specifique');
            
            // Renseigné seulement si type_client = 'vehicule'
            $table->enum('type_vehicule', ['camion', 'fourgonette', 'tricycle'])->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
