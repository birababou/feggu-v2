<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels repris de FEGGU v1 (mêmes identifiants, pour faciliter la reprise des données).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 80);
        });

        Schema::create('departements', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 80);
            $table->unsignedInteger('region_id')->index();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 120);
            $table->unsignedInteger('departement_id')->index();
        });

        Schema::create('localites', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 120);
            $table->unsignedInteger('commune_id')->index();
        });

        Schema::create('categories_violence', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 120);
            $table->string('nom_wo', 120)->nullable();
            $table->string('icone', 40)->nullable();
        });

        Schema::create('types_violence', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 160);
            $table->unsignedInteger('categorie_id')->index();
            $table->unsignedTinyInteger('gravite')->default(1); // 1 à 3, utilisé par le tri
        });

        Schema::create('besoins', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('nom', 120);
            $table->string('nom_wo', 120)->nullable();
        });

        Schema::create('liens_auteur', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom', 80);
        });
    }

    public function down(): void
    {
        foreach (['liens_auteur', 'besoins', 'types_violence', 'categories_violence', 'localites', 'communes', 'departements', 'regions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
