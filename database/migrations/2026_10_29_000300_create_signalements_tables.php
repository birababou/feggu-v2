<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('canal', 20);                 // sms, whatsapp, ussd, web, voix
            $table->string('contact_hash', 64)->index(); // HMAC du numéro : retrouver une conversation sans stocker le numéro en clair
            $table->text('contact')->nullable();          // chiffré
            $table->string('session_externe', 120)->nullable()->index(); // ex. sessionId USSD
            $table->string('langue', 2)->default('fr');
            $table->string('etape', 40)->default('accueil');
            $table->json('donnees')->nullable();
            $table->unsignedBigInteger('signalement_id')->nullable();
            $table->timestamp('dernier_message_le')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sens', 3); // in, out
            $table->text('contenu')->nullable(); // chiffré
            $table->string('media_type', 20)->nullable(); // audio, image
            $table->text('media_url')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('canal', 20)->index();      // web, sms, whatsapp, ussd, voix, appel_manque, relais, hors_ligne
            $table->string('statut', 20)->default('nouveau')->index(); // nouveau, oriente, en_cours, pris_en_charge, clos
            $table->string('urgence', 10)->default('moyenne')->index(); // critique, haute, moyenne, basse
            $table->unsignedSmallInteger('score_urgence')->default(0);
            $table->json('raisons_urgence')->nullable();
            $table->string('declarant', 20)->nullable(); // victime, temoin, relais, structure
            $table->boolean('danger_immediat')->default(false);
            $table->unsignedInteger('categorie_id')->nullable()->index();
            $table->json('types')->nullable();
            $table->json('besoins')->nullable();
            $table->unsignedInteger('region_id')->nullable()->index();
            $table->unsignedInteger('departement_id')->nullable()->index();
            $table->unsignedInteger('commune_id')->nullable();
            $table->unsignedInteger('localite_id')->nullable();
            $table->string('zone', 10)->nullable();      // rural, urbain
            $table->string('lieu', 20)->nullable();      // maison, ecole, travail, rue, autre
            $table->string('periode_faits', 20)->nullable(); // en_cours, aujourdhui, semaine, mois, plus_ancien
            $table->date('date_faits')->nullable();
            $table->string('victime_sexe', 1)->nullable();
            $table->string('victime_tranche_age', 10)->nullable(); // 0-11, 12-17, 18-24, 25-49, 50+
            $table->boolean('victime_handicap')->nullable();
            $table->boolean('auteur_connu')->nullable();
            $table->unsignedInteger('lien_auteur_id')->nullable();
            $table->string('langue', 2)->default('fr');
            $table->text('recit')->nullable();             // chiffré
            $table->text('transcription')->nullable();     // chiffré
            $table->text('contact_telephone')->nullable(); // chiffré
            $table->boolean('consentement_contact')->default(false);
            $table->boolean('consentement_partage')->default(false);
            $table->foreignId('structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('oriente_le')->nullable();
            $table->timestamp('premier_contact_le')->nullable();
            $table->timestamp('pris_en_charge_le')->nullable();
            $table->timestamp('clos_le')->nullable();
            $table->boolean('est_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('suivis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signalement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // creation, triage, orientation, contact, prise_en_charge, statut, note, cloture
            $table->string('domaine', 20)->nullable(); // juridique, medical, psychologique, hebergement, economique, securite, mediation
            $table->text('contenu')->nullable(); // chiffré
            $table->json('meta')->nullable();
            $table->boolean('visible_declarant')->default(false); // affiché sur la page de suivi publique (sans détail)
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('cible_type', 40)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'suivis', 'signalements', 'messages', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
