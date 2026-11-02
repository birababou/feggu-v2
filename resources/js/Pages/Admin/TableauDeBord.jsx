import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { BarresH, Colonnes } from '../../Components/Barres';
import Urgence from '../../Components/Urgence';
import LayoutAdmin from '../../Layouts/LayoutAdmin';

function Indicateur({ valeur, libelle, alerte, lien }) {
    const contenu = (
        <>
            <p className={`font-display text-4xl font-extrabold ${alerte ? 'text-critique' : 'text-nuit'}`}>{valeur ?? '—'}</p>
            <p className="mt-1 text-sm text-gris">{libelle}</p>
        </>
    );
    return lien ? <Link href={lien} className="carte block p-5 hover:border-violet-clair">{contenu}</Link> : <div className="carte p-5">{contenu}</div>;
}

function CarteIa({ ia, peutTester }) {
    const [enCours, setEnCours] = useState(false);
    const tester = () => router.post('/espace/ia/tester', {}, { preserveScroll: true, onStart: () => setEnCours(true), onFinish: () => setEnCours(false) });
    return (
        <section className={`mt-6 flex flex-wrap items-center gap-5 rounded-2xl p-5 ${ia.actif ? 'bg-nuit text-white' : 'border border-dashed border-trait bg-white'}`}>
            <span className={`grid size-12 shrink-0 place-items-center rounded-full font-display text-lg font-extrabold ${ia.actif ? 'bg-orange text-white' : 'bg-lilas text-violet'}`} aria-hidden="true">IA</span>
            <div className="min-w-0 flex-1">
                <p className={`font-display text-lg font-bold ${ia.actif ? 'text-white' : 'text-nuit'}`}>
                    {ia.actif ? <>Intelligence artificielle active <span className="font-sans text-sm font-normal opacity-80">· modèle {ia.modele}, hébergé sur le serveur FEGGU</span></> : 'Intelligence artificielle non installée'}
                </p>
                <p className={`text-sm ${ia.actif ? 'text-white/80' : 'text-gris'}`}>
                    {ia.actif
                        ? <>Elle relit chaque récit (français ou wolof) après la réponse à la victime et relève l'urgence si elle détecte un danger immédiat. {ia.analyses_30j} récit(s) analysé(s) en 30 jours, {ia.urgences_relevees_30j} urgence(s) relevée(s){ia.derniere ? `, dernière analyse ${ia.derniere}` : ''}.
                            {ia.assistant.length > 0 && <> L'assistante conversationnelle répond aux messages libres sur {ia.assistant.join(', ')} (testez-la dans le simulateur).</>}</>
                        : <>Le tri fonctionne avec les règles intégrées. Pour activer l'IA locale (les récits ne quittent jamais le serveur) : <code className="rounded bg-fond px-1">./deploy.sh ia</code> depuis le Mac.</>}
                </p>
            </div>
            {peutTester && ia.actif && (
                <button type="button" onClick={tester} disabled={enCours} className="rounded-full bg-white px-5 py-2 font-bold text-nuit hover:bg-peche disabled:opacity-60">
                    {enCours ? 'Essai en cours…' : 'Tester l\'IA'}
                </button>
            )}
        </section>
    );
}

export default function TableauDeBord({ kpi, ia, peut_tester_ia, par_mois, par_categorie, par_canal, par_region, a_traiter }) {
    return (
        <LayoutAdmin titre="Tableau de bord">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <Indicateur valeur={kpi.critiques_ouverts} libelle="critiques en attente" alerte={kpi.critiques_ouverts > 0} lien="/espace/signalements?urgence=critique&statut=ouverts" />
                <Indicateur valeur={kpi.non_orientes} libelle="sans structure" alerte={kpi.non_orientes > 0} lien="/espace/signalements?statut=nouveau" />
                <Indicateur valeur={kpi.trente_jours} libelle="reçus en 30 jours" />
                <Indicateur valeur={kpi.total} libelle="signalements au total" />
                <Indicateur valeur={kpi.delai_median_heures != null ? `${kpi.delai_median_heures} h` : null} libelle="délai médian avant le 1er contact" />
                <Indicateur valeur={kpi.taux_prise_en_charge != null ? `${kpi.taux_prise_en_charge} %` : null} libelle="des cas orientés pris en charge" />
            </div>

            <CarteIa ia={ia} peutTester={peut_tester_ia} />

            <div className="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <section className="carte p-6">
                    <h2 className="text-xl font-bold">Signalements par mois</h2>
                    <p className="text-sm text-gris">12 derniers mois, mois en cours en orange</p>
                    <div className="mt-4"><Colonnes donnees={par_mois} /></div>
                </section>
                <section className="carte p-6">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-xl font-bold">À traiter en priorité</h2>
                        <Link href="/espace/signalements?statut=ouverts" className="text-sm font-bold text-violet">Tout voir</Link>
                    </div>
                    {a_traiter.length === 0 ? <p className="mt-4 text-gris">Aucun cas urgent en attente.</p> : (
                        <ul className="mt-3 divide-y divide-trait">
                            {a_traiter.map((s) => (
                                <li key={s.id}>
                                    <Link href={`/espace/signalements/${s.id}`} className="flex items-center gap-3 py-3 hover:bg-fond">
                                        <Urgence niveau={s.urgence} />
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-bold text-nuit">{s.categorie || 'Non précisé'}</span>
                                            <span className="block truncate text-sm text-gris">{s.departement || 'Lieu inconnu'} · {s.canal} · {s.depuis}</span>
                                        </span>
                                        <span className="text-sm font-bold text-violet">{s.statut}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <section className="carte p-6"><h2 className="mb-4 text-xl font-bold">Par type de violence</h2><BarresH donnees={par_categorie} /></section>
                <section className="carte p-6"><h2 className="mb-4 text-xl font-bold">Par canal d'alerte</h2><BarresH donnees={par_canal} couleur="var(--color-orange)" /></section>
                <section className="carte p-6"><h2 className="mb-4 text-xl font-bold">Par région</h2><BarresH donnees={par_region} couleur="var(--color-violet-clair)" /></section>
            </div>
        </LayoutAdmin>
    );
}
