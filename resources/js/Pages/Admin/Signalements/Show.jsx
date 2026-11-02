import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Urgence from '../../../Components/Urgence';
import LayoutAdmin from '../../../Layouts/LayoutAdmin';

const LIBELLES = {
    declarant: { victime: 'La victime elle-même', temoin: 'Un témoin', relais: 'Un relais communautaire', structure: 'Une structure' },
    lieu: { maison: 'Domicile', ecole: 'École', travail: 'Lieu de travail', rue: 'Rue', autre: 'Autre lieu' },
    periode: { en_cours: 'En cours', aujourdhui: 'Aujourd\'hui', semaine: 'Cette semaine', mois: 'Ce mois-ci', plus_ancien: 'Plus ancien' },
    besoins: { hebergement: 'Hébergement', juridique: 'Juridique', psychologique: 'Psychologique', medical: 'Médical', retour_famille: 'Retour en famille', mediation: 'Médiation', reinsertion_scolaire: 'Retour à l\'école', securite: 'Sécurité', economique: 'Socio-économique', retour_pays: 'Retour au pays' },
    suivi: { creation: 'Signalement reçu', triage: 'Tri automatique', orientation: 'Orientation', contact: 'Contact', prise_en_charge: 'Prise en charge', statut: 'Statut', note: 'Note interne', cloture: 'Clôture' },
};

function Ligne({ titre, children }) {
    if (!children || (Array.isArray(children) && children.length === 0)) return null;
    return (
        <div className="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
            <dt className="text-sm text-gris">{titre}</dt>
            <dd className="text-encre">{children}</dd>
        </div>
    );
}

function Bloc({ titre, children, className = '' }) {
    return (
        <section className={`carte p-6 ${className}`}>
            <h2 className="mb-3 text-lg font-bold">{titre}</h2>
            {children}
        </section>
    );
}

function BlocIa({ s, suivis, active }) {
    const [enCours, setEnCours] = useState(false);
    const analyses = suivis.filter((x) => x.meta?.ia);
    const derniere = analyses[analyses.length - 1];
    const relancer = () => router.post(`/espace/signalements/${s.id}/analyser`, {}, { preserveScroll: true, onStart: () => setEnCours(true), onFinish: () => setEnCours(false) });
    if (!s.recit) return null;
    return (
        <section className="rounded-2xl bg-nuit p-6 text-white">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="flex items-center gap-3 text-lg font-bold text-white">
                    <span className="grid size-9 place-items-center rounded-full bg-orange text-sm font-extrabold" aria-hidden="true">IA</span>
                    Lecture du récit par l'IA
                </h2>
                {active && (
                    <button type="button" onClick={relancer} disabled={enCours} className="rounded-full bg-white/10 px-4 py-1.5 text-sm font-bold hover:bg-white/20 disabled:opacity-60">
                        {enCours ? 'Analyse en cours…' : derniere ? 'Relancer l\'analyse' : 'Analyser maintenant'}
                    </button>
                )}
            </div>
            {derniere ? (
                <div className="mt-4 space-y-2">
                    <p className="text-lg">« {derniere.meta.raisons?.[0]} »</p>
                    <p className="flex flex-wrap gap-2 text-sm">
                        <span className={`rounded-full px-3 py-0.5 font-bold ${derniere.meta.danger_immediat ? 'bg-critique text-white' : 'bg-white/10'}`}>{derniere.meta.danger_immediat ? 'Danger immédiat probable' : 'Pas de danger immédiat détecté'}</span>
                        {derniere.meta.urgence_relevee && <span className="rounded-full bg-orange px-3 py-0.5 font-bold">Urgence relevée à « {derniere.meta.urgence} »</span>}
                    </p>
                    <p className="text-xs text-white/60">{derniere.date}{derniere.meta.modele ? ` · modèle ${derniere.meta.modele}` : ''} · l'IA peut relever l'urgence, jamais la baisser. Vérifiez toujours avec la personne.</p>
                </div>
            ) : (
                <p className="mt-3 text-white/80">{active ? 'Analyse en attente : elle se fait quelques secondes après la réception. Rechargez la page ou lancez-la maintenant.' : 'L\'IA n\'est pas installée sur ce serveur : seul le tri par règles a été appliqué.'}</p>
            )}
        </section>
    );
}

export default function Show({ signalement: s, suivis, suggestions, structures, domaines, ia_active }) {
    const [voirConversation, setVoirConversation] = useState(false);
    const orientation = useForm({ structure_id: s.structure?.id || '' });
    const suivi = useForm({ type: 'contact', domaine: '', contenu: '' });

    const orienter = (id) => router.post(`/espace/signalements/${s.id}/orienter`, { structure_id: id }, { preserveScroll: true });
    const ajouterSuivi = (e) => {
        e.preventDefault();
        suivi.post(`/espace/signalements/${s.id}/suivi`, { preserveScroll: true, onSuccess: () => suivi.reset('contenu', 'domaine') });
    };

    return (
        <LayoutAdmin titre={s.reference} actions={<Link href="/espace/signalements" className="btn-discret">Retour à la liste</Link>}>
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <Urgence niveau={s.urgence} />
                {s.danger_immediat && <span className="rounded-full bg-critique/10 px-3 py-0.5 text-sm font-bold text-critique">Danger immédiat signalé</span>}
                <span className="text-gris">Reçu le {s.cree_le} par {s.canal}</span>
                {s.est_demo && <span className="rounded-full bg-peche px-3 py-0.5 text-sm font-bold text-orange-fonce">Démo</span>}
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.25fr_1fr]">
                <div className="space-y-6">
                    <BlocIa s={s} suivis={suivis} active={ia_active} />
                    {s.raisons_urgence.length > 0 && (
                        <Bloc titre={`Pourquoi cette urgence (score ${s.score_urgence}/100)`}>
                            <ul className="list-disc space-y-1 pl-5 text-encre">{s.raisons_urgence.map((r) => <li key={r}>{r}</li>)}</ul>
                        </Bloc>
                    )}

                    <Bloc titre="Ce qui a été signalé">
                        <dl className="divide-y divide-trait">
                            <Ligne titre="Violence">{s.categorie || 'Non précisé'}{s.types.length > 0 && <span className="block text-sm text-gris">{s.types.join(', ')}</span>}</Ligne>
                            <Ligne titre="Signalé par">{LIBELLES.declarant[s.declarant]}</Ligne>
                            <Ligne titre="Lieu">{s.lieu}{s.type_lieu && ` · ${LIBELLES.lieu[s.type_lieu]}`}</Ligne>
                            <Ligne titre="Quand">{LIBELLES.periode[s.periode_faits]}</Ligne>
                            <Ligne titre="Victime">{Object.values(s.victime).join(' · ')}</Ligne>
                            <Ligne titre="Auteur présumé">{s.auteur}</Ligne>
                            <Ligne titre="Besoins">{s.besoins.length > 0 && s.besoins.map((b) => LIBELLES.besoins[b] || b).join(', ')}</Ligne>
                            <Ligne titre="Langue">{s.langue}</Ligne>
                        </dl>
                    </Bloc>

                    <Bloc titre="Récit">
                        {s.recit ? <p className="whitespace-pre-line">{s.recit}</p> : <p className="text-gris">Aucun récit.</p>}
                        {s.transcription && <p className="mt-3 rounded-xl bg-lilas p-3 text-sm"><b>Transcription automatique :</b> {s.transcription}</p>}
                        {s.messages.length > 0 && (
                            <div className="mt-4">
                                <button type="button" className="text-sm font-bold text-violet" onClick={() => setVoirConversation(!voirConversation)} aria-expanded={voirConversation}>
                                    {voirConversation ? 'Masquer' : 'Afficher'} la conversation ({s.messages.length} messages)
                                </button>
                                {voirConversation && (
                                    <div className="mt-3 flex flex-col gap-2 rounded-xl bg-fond p-3 text-sm">
                                        {s.messages.map((m, i) => (
                                            <p key={i} className={`max-w-[85%] whitespace-pre-line rounded-xl px-3 py-2 ${m.sens === 'in' ? 'self-start bg-white ring-1 ring-trait' : 'self-end bg-lilas'}`}>
                                                {m.audio && <b>[Note vocale] </b>}{m.contenu || <i className="text-gris">vide</i>}
                                            </p>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </Bloc>
                </div>

                <div className="space-y-6">
                    <Bloc titre="Orientation">
                        {s.structure ? <p>Confié à <b>{s.structure.nom}</b></p> : <p className="font-bold text-orange-fonce">Aucune structure pour l'instant.</p>}
                        {suggestions.length > 0 && (
                            <>
                                <p className="mt-4 text-sm text-gris">Structures suggérées (proximité et services)</p>
                                <ul className="mt-2 space-y-2">
                                    {suggestions.map((x) => (
                                        <li key={x.id} className="flex items-center justify-between gap-3 rounded-xl bg-fond px-3 py-2">
                                            <span><b>{x.nom}</b><span className="block text-xs text-gris">{x.services.map((d) => domaines[d] || d).join(', ')}</span></span>
                                            {s.structure?.id !== x.id && <button type="button" className="text-sm font-bold text-violet" onClick={() => orienter(x.id)}>Confier</button>}
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}
                        <form className="mt-4 flex gap-2" onSubmit={(e) => { e.preventDefault(); orientation.post(`/espace/signalements/${s.id}/orienter`, { preserveScroll: true }); }}>
                            <select className="champ py-2" value={orientation.data.structure_id} onChange={(e) => orientation.setData('structure_id', e.target.value)} aria-label="Autre structure">
                                <option value="">Autre structure…</option>
                                {structures.map((st) => <option key={st.id} value={st.id}>{st.nom}</option>)}
                            </select>
                            <button className="btn-ligne py-2" disabled={!orientation.data.structure_id}>Confier</button>
                        </form>
                    </Bloc>

                    <Bloc titre="Contacter la personne">
                        {s.consentement_contact && s.contact_telephone ? (
                            <>
                                <p>A accepté d'être rappelée au <a href={`tel:${s.contact_telephone}`} className="font-bold text-violet">{s.contact_telephone}</a>.</p>
                                <p className="mt-1 text-sm text-gris">Le SMS envoyé reste neutre : il ne parle pas de violence.</p>
                                <button type="button" className="btn-ligne mt-3 py-2" onClick={() => router.post(`/espace/signalements/${s.id}/contacter`, {}, { preserveScroll: true })}>Envoyer un SMS de prise de contact</button>
                            </>
                        ) : <p className="text-gris">La personne n'a pas demandé à être contactée. Ne pas chercher à la joindre.</p>}
                    </Bloc>

                    <Bloc titre="Ajouter un suivi">
                        <form onSubmit={ajouterSuivi} className="space-y-3">
                            <select className="champ py-2" value={suivi.data.type} onChange={(e) => suivi.setData('type', e.target.value)} aria-label="Type de suivi">
                                <option value="contact">Contact établi</option>
                                <option value="prise_en_charge">Prise en charge</option>
                                <option value="note">Note interne</option>
                                <option value="cloture">Clore le dossier</option>
                            </select>
                            {suivi.data.type === 'prise_en_charge' && (
                                <select className="champ py-2" value={suivi.data.domaine} onChange={(e) => suivi.setData('domaine', e.target.value)} aria-label="Domaine">
                                    <option value="">Domaine de la prise en charge…</option>
                                    {Object.entries(domaines).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                                </select>
                            )}
                            {suivi.errors.domaine && <p className="text-sm font-bold text-critique">Choisissez le domaine.</p>}
                            <textarea className="champ min-h-24" placeholder="Ce qui a été fait (visible uniquement par les structures)" value={suivi.data.contenu} onChange={(e) => suivi.setData('contenu', e.target.value)} />
                            <button className="btn-violet py-2" disabled={suivi.processing}>Enregistrer le suivi</button>
                        </form>
                    </Bloc>

                    <Bloc titre="Historique">
                        <ol className="space-y-4">
                            {suivis.map((x) => (
                                <li key={x.id} className="border-l-2 border-lilas pl-4">
                                    <p className="font-bold text-nuit">{x.meta?.ia ? 'Analyse du récit par l\'IA' : (LIBELLES.suivi[x.type] || x.type)}{x.domaine && ` · ${domaines[x.domaine]}`}</p>
                                    {x.meta?.ia && x.meta.raisons?.[0] && <p className="text-sm">{x.meta.raisons[0]}</p>}
                                    {x.contenu && <p className="text-sm">{x.contenu}</p>}
                                    {x.type === 'triage' && x.meta && <p className="text-sm">Urgence {x.meta.urgence} (score {x.meta.score})</p>}
                                    <p className="text-xs text-gris">{x.date} · {x.auteur}</p>
                                </li>
                            ))}
                        </ol>
                    </Bloc>
                </div>
            </div>
        </LayoutAdmin>
    );
}
