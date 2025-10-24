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

            // Référence unique (ex : IB123)
            $table->string('reference', 6)->unique();

            // Identité
            $table->string('prenom')->nullable();
            $table->string('nom')->nullable();

            // Coordonnées
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // Informations personnelles
            $table->date('date_naissance')->nullable();
            $table->enum('civilite', ['Mr', 'Mme', 'Mlle', 'Autre'])->default('Autre');

            // Liens vers autres tables
            $table->foreignId('adresse_id')
                ->nullable()
                ->constrained('adresses')
                ->onDelete('cascade');

            $table->foreignId('role_id')
                ->nullable()
                ->default(2) // 2 = client par défaut
                ->constrained('roles')
                ->onDelete('set null');

            $table->unsignedBigInteger('agence_id')->nullable(); // si employé lié à une agence

            // Statut du compte
            $table->enum('statut', ['active', 'attente', 'bloque', 'archive'])->default('attente');

            $table->rememberToken();
            $table->timestamps();
        });

        // Table pour la réinitialisation de mot de passe (standard Laravel)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Table des sessions (option Sanctum)
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
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
