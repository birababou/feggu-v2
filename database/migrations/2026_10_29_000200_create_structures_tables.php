<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organisations partenaires (reprises de feg_profil en v1).
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('v1_id')->nullable()->unique();
            $table->string('nom');
            $table->string('sigle', 30)->nullable();
            $table->json('services')->nullable(); // juridique, medical, psychologique, hebergement, economique, securite, mediation, protection_enfant
            $table->timestamps();
        });

        // Antenne d'une organisation dans un département : c'est elle qui reçoit les cas.
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('departement_id')->index();
            $table->string('nom');
            $table->string('telephone', 30)->nullable();
            $table->json('services')->nullable(); // surcharge éventuelle des services de l'organisation
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('agent')->after('password'); // admin, superviseur, agent
            $table->foreignId('structure_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->unsignedInteger('region_id')->nullable()->after('structure_id');
            $table->string('telephone', 30)->nullable()->after('region_id');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('legacy_md5', 64)->nullable(); // mot de passe v1, remplacé par bcrypt à la 1re connexion
            $table->boolean('actif')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('structure_id');
            $table->dropColumn(['role', 'region_id', 'telephone', 'two_factor_secret', 'two_factor_confirmed_at', 'legacy_md5', 'actif', 'last_login_at']);
        });
        Schema::dropIfExists('structures');
        Schema::dropIfExists('organisations');
    }
};
