<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe les référentiels de FEGGU v1 (régions → localités, typologie, besoins, structures)
 * depuis l'export mysqldump, en gardant les identifiants d'origine.
 */
class ImporterReferentielsV1 extends Command
{
    protected $signature = 'feggu:import-referentiels {fichier? : export SQL v1 (par défaut database/data/referentiels_v1.sql)}';

    protected $description = 'Importe les référentiels de FEGGU v1 dans la V2';

    /** Icônes et libellés wolof des catégories (traduction à valider par GESTES). */
    private const CATEGORIES = [
        1 => ['Violences sexuelles', 'Fitna ci wàll jëmm', 'sexuelle'],
        2 => ['Violences physiques', 'Dóor ak gaañ', 'physique'],
        3 => ['Violences psychologiques', 'Sonal xel ak saaga', 'psychologique'],
        4 => ['Abandon et refus des obligations familiales', 'Bàyyi njaboot', 'economique'],
        5 => ['Maltraitance d\'enfant et traite', 'Fitnaal xale ak jaay nit', 'enfant'],
        6 => ['Mutilations génitales féminines (MGF)', 'Xaraf (MGF)', 'mgf'],
        7 => ['Mariage d\'enfant ou forcé', 'Séyal xale walla sañul', 'mariage'],
        8 => ['Exploitation sexuelle', 'Jëfandikoo jëmm ñeneen', 'exploitation'],
    ];

    private const BESOINS = [
        1 => ['hebergement', 'Hébergement', 'Fu ma dëkk'],
        2 => ['juridique', 'Assistance juridique', 'Ndimbal ci àtte'],
        3 => ['psychologique', 'Soutien psychologique', 'Ku may déglu'],
        4 => ['medical', 'Soins médicaux', 'Fajoo'],
        5 => ['retour_famille', 'Retour en famille', 'Dellu ci njaboot'],
        6 => ['mediation', 'Médiation familiale', 'Juboo ci njaboot'],
        7 => ['reinsertion_scolaire', 'Retour à l\'école', 'Dellu lekkool'],
        9 => ['securite', 'Protection / sécurité', 'Aar sama bakkan'],
        10 => ['economique', 'Réinsertion socio-économique', 'Ndimbal ci liggéey'],
        11 => ['retour_pays', 'Retour au pays d\'origine', 'Dellu sama réew'],
    ];

    public function handle(): int
    {
        $fichier = $this->argument('fichier') ?? database_path('data/referentiels_v1.sql');
        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : $fichier");

            return self::FAILURE;
        }

        $sql = file_get_contents($fichier);
        $accents = ['Thies' => 'Thiès', 'Kedougou' => 'Kédougou', 'Sedhiou' => 'Sédhiou', 'Mbacke' => 'Mbacké', 'Guediawaye' => 'Guédiawaye',
            'Velingara' => 'Vélingara', 'Linguere' => 'Linguère', 'Kebemer' => 'Kébémer', 'Ranerou' => 'Ranérou',
            'Medina Yoro Foulah' => 'Médina Yoro Foulah', 'Nioro Du Rip' => 'Nioro du Rip'];
        $titre = fn (string $s) => $accents[$t = mb_convert_case(mb_strtolower(trim($s)), MB_CASE_TITLE)] ?? $t;

        DB::transaction(function () use ($sql, $titre) {
            $this->remplir('regions', $this->lignes($sql, 'ml_region'), fn ($r) => ['id' => $r[0], 'nom' => $titre($r[1])]);
            $this->remplir('departements', $this->lignes($sql, 'ml_departement'), fn ($r) => ['id' => $r[0], 'nom' => $titre($r[1]), 'region_id' => $r[2]]);
            $this->remplir('communes', $this->lignes($sql, 'ml_commune'), fn ($r) => ['id' => $r[0], 'nom' => $titre($r[1]), 'departement_id' => $r[2]]);
            $this->remplir('localites', $this->lignes($sql, 'ml_localite'), fn ($r) => ['id' => $r[0], 'nom' => $titre($r[1]), 'commune_id' => $r[2]]);

            $this->remplir('categories_violence', $this->lignes($sql, 'feg_cat_vbg'), fn ($r) => [
                'id' => $r[0],
                'nom' => self::CATEGORIES[$r[0]][0] ?? trim($r[1]),
                'nom_wo' => self::CATEGORIES[$r[0]][1] ?? null,
                'icone' => self::CATEGORIES[$r[0]][2] ?? null,
            ]);

            $this->remplir('types_violence', $this->lignes($sql, 'feg_tipologie_viol'), fn ($r) => [
                'id' => $r[0], 'nom' => trim($r[1]), 'categorie_id' => $r[2], 'gravite' => $this->gravite($r[1]),
            ]);

            $this->remplir('besoins', $this->lignes($sql, 'feg_besoin_exprime'), fn ($r) => [
                'id' => $r[0],
                'code' => self::BESOINS[$r[0]][0] ?? 'besoin_'.$r[0],
                'nom' => self::BESOINS[$r[0]][1] ?? trim($r[1]),
                'nom_wo' => self::BESOINS[$r[0]][2] ?? null,
            ]);

            $this->remplir('liens_auteur', $this->lignes($sql, 'feg_lien_auteur_victime'), fn ($r) => ['id' => $r[0], 'nom' => trim($r[1])]);

            foreach ($this->lignes($sql, 'feg_profil') as $r) {
                [$id, $nom, $sigle] = [$r[0], trim($r[1]), $r[2] ?? null];
                if (in_array((int) $id, [1, 42], true)) {
                    continue; // « Administrateur » et « Personne physique » ne sont pas des structures
                }
                $nom = str_replace(['Word Vision', 'Poin Focal'], ['World Vision', 'Point focal'], $nom);
                Organisation::updateOrCreate(['v1_id' => $id], [
                    'nom' => $nom,
                    'sigle' => ($sigle && $sigle !== 'NULL') ? $sigle : null,
                    'services' => $this->services($nom.' '.$sigle),
                ]);
            }
        });

        foreach (['regions', 'departements', 'communes', 'localites', 'categories_violence', 'types_violence', 'besoins', 'liens_auteur', 'organisations'] as $t) {
            $this->line(str_pad($t, 22).DB::table($t)->count());
        }

        return self::SUCCESS;
    }

    private function remplir(string $table, array $lignes, callable $map): void
    {
        DB::table($table)->delete();
        foreach (array_chunk(array_map($map, $lignes), 500) as $lot) {
            DB::table($table)->insert($lot);
        }
    }

    /** Extrait les n-uplets des INSERT INTO `table` VALUES (...),(...); d'un export mysqldump. */
    private function lignes(string $sql, string $table): array
    {
        $lignes = [];
        $marqueur = "INSERT INTO `$table` VALUES ";
        $pos = 0;
        while (($debut = strpos($sql, $marqueur, $pos)) !== false) {
            $i = $debut + strlen($marqueur);
            $n = strlen($sql);
            $ligne = [];
            $valeur = '';
            $dansChaine = false;
            $estChaine = false;
            for (; $i < $n; $i++) {
                $c = $sql[$i];
                if ($dansChaine) {
                    if ($c === '\\') {
                        $valeur .= $sql[++$i];
                    } elseif ($c === "'") {
                        $dansChaine = false;
                    } else {
                        $valeur .= $c;
                    }
                    continue;
                }
                if ($c === "'") {
                    $dansChaine = true;
                    $estChaine = true;
                } elseif ($c === '(') {
                    $ligne = [];
                    $valeur = '';
                    $estChaine = false;
                } elseif ($c === ',' || $c === ')') {
                    $ligne[] = (! $estChaine && strtoupper(trim($valeur)) === 'NULL') ? null : trim($valeur);
                    $valeur = '';
                    $estChaine = false;
                    if ($c === ')') {
                        $lignes[] = $ligne;
                        $ligne = [];
                    }
                } elseif ($c === ';') {
                    break;
                } else {
                    $valeur .= $c;
                }
            }
            $pos = $i;
        }

        return array_values(array_filter($lignes, fn ($l) => count($l) >= 2 && $l[0] !== '' && $l[0] !== null));
    }

    private function gravite(string $nom): int
    {
        $n = mb_strtolower($nom);
        if (preg_match('/\b(viols?|inceste|meurtre|assassinat|enlèvement|séquestration|brûlures?|traite des|mutilations?|prostitution|mariage d\'enfant|détournements?|agression sexuelle|relation sexuelle précoce)\b/u', $n)) {
            return 3;
        }
        if (preg_match('/\b(coups|agressions?|attouchement|menaces|mariage forcé|mendicité|travail des enfants|correction)\b/u', $n)) {
            return 2;
        }

        return 1;
    }

    /** Domaines d'intervention déduits du nom de l'organisation (modifiables ensuite dans le back-office). */
    private function services(string $texte): array
    {
        $t = mb_strtolower($texte);
        $regles = [
            'juristes' => ['juridique', 'mediation'],
            'ajs' => ['juridique', 'mediation'],
            'district sanitaire' => ['medical'],
            'conseil adolescent' => ['medical', 'psychologique'],
            'aemo' => ['protection_enfant', 'juridique', 'mediation'],
            'milieu ouvert' => ['protection_enfant', 'juridique', 'mediation'],
            'protection de l\'enfant' => ['protection_enfant', 'securite'],
            'violences aux femmes' => ['psychologique', 'mediation', 'hebergement'],
            'clvf' => ['psychologique', 'mediation', 'hebergement'],
            'bureau genre' => ['psychologique', 'mediation'],
            'développement communautaire' => ['economique', 'mediation'],
            'sdas' => ['economique', 'hebergement', 'protection_enfant'],
            'appui et de formation des femmes' => ['economique'],
            'cedaf' => ['economique'],
            'mariage des enfants' => ['protection_enfant', 'mediation'],
            'tostan' => ['mediation'],
            'raddho' => ['juridique'],
            'amnesty' => ['juridique'],
            'droits humains' => ['juridique'],
            'plan international' => ['protection_enfant', 'economique'],
            'world vision' => ['protection_enfant', 'economique'],
            'enda' => ['protection_enfant'],
            'enfance' => ['protection_enfant'],
            'sos equilibre' => ['psychologique'],
            'ime' => ['protection_enfant'],
        ];
        $services = [];
        foreach ($regles as $mot => $domaines) {
            if (preg_match('/\b'.preg_quote($mot, '/').'\b/u', $t)) {
                $services = array_merge($services, $domaines);
            }
        }

        return array_values(array_unique($services ?: ['mediation']));
    }
}
