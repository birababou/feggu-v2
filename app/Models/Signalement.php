<?php

namespace App\Models;

use App\Models\Ref\CategorieViolence;
use App\Models\Ref\Commune;
use App\Models\Ref\Departement;
use App\Models\Ref\LienAuteur;
use App\Models\Ref\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Signalement extends Model
{
    public const CANAUX = [
        'web' => 'Site web',
        'sms' => 'SMS 21211',
        'whatsapp' => 'WhatsApp',
        'ussd' => 'USSD',
        'voix' => 'Message vocal',
        'appel_manque' => 'Appel « bip »',
        'relais' => 'Relais communautaire',
        'hors_ligne' => 'Formulaire hors ligne',
    ];

    public const STATUTS = [
        'nouveau' => 'Nouveau',
        'oriente' => 'Orienté',
        'en_cours' => 'Contact établi',
        'pris_en_charge' => 'Pris en charge',
        'clos' => 'Clos',
    ];

    public const URGENCES = ['critique' => 4, 'haute' => 3, 'moyenne' => 2, 'basse' => 1];

    public const DOMAINES = [
        'juridique' => 'Assistance juridique',
        'medical' => 'Assistance médicale',
        'psychologique' => 'Soutien psychologique',
        'hebergement' => 'Hébergement',
        'economique' => 'Réinsertion socio-économique',
        'securite' => 'Protection / sécurité',
        'mediation' => 'Médiation familiale',
        'protection_enfant' => 'Protection de l\'enfant',
    ];

    protected $guarded = [];

    protected $casts = [
        'raisons_urgence' => 'array',
        'types' => 'array',
        'besoins' => 'array',
        'danger_immediat' => 'boolean',
        'victime_handicap' => 'boolean',
        'auteur_connu' => 'boolean',
        'consentement_contact' => 'boolean',
        'consentement_partage' => 'boolean',
        'est_demo' => 'boolean',
        'recit' => 'encrypted',
        'transcription' => 'encrypted',
        'contact_telephone' => 'encrypted',
        'date_faits' => 'date',
        'oriente_le' => 'datetime',
        'premier_contact_le' => 'datetime',
        'pris_en_charge_le' => 'datetime',
        'clos_le' => 'datetime',
    ];

    public function categorie()
    {
        return $this->belongsTo(CategorieViolence::class, 'categorie_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function lienAuteur()
    {
        return $this->belongsTo(LienAuteur::class, 'lien_auteur_id');
    }

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function suivis()
    {
        return $this->hasMany(Suivi::class)->orderBy('created_at')->orderBy('id');
    }

    /** Ordre de traitement : urgence d'abord, puis ancienneté. */
    public function scopeParPriorite(Builder $q): Builder
    {
        return $q->orderByRaw("CASE urgence WHEN 'critique' THEN 4 WHEN 'haute' THEN 3 WHEN 'moyenne' THEN 2 ELSE 1 END DESC")
            ->orderBy('created_at');
    }

    /** Restreint aux signalements qu'un utilisateur a le droit de voir. */
    public function scopeVisiblesPar(Builder $q, User $user): Builder
    {
        return match ($user->role) {
            'admin' => $q,
            'superviseur' => $user->region_id ? $q->where('region_id', $user->region_id) : $q->whereRaw('1 = 0'),
            default => $q->where(function (Builder $w) use ($user) {
                $w->where('structure_id', $user->structure_id ?? 0);
                // Les cas non encore orientés de son département sont aussi visibles, pour ne rien laisser en attente.
                if ($user->structure) {
                    $w->orWhere(fn ($n) => $n->whereNull('structure_id')->where('departement_id', $user->structure->departement_id));
                }
            }),
        };
    }

    public function estVisiblePar(User $user): bool
    {
        return static::query()->visiblesPar($user)->whereKey($this->getKey())->exists();
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}
