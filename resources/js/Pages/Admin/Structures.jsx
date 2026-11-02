import { router, useForm } from '@inertiajs/react';
import { Filtres, filtrer, Pagination } from '../../Components/Liste';
import LayoutAdmin from '../../Layouts/LayoutAdmin';

export default function Structures({ structures, filtres, organisations, departements, domaines }) {
    const { data, setData, post, processing, errors, reset } = useForm({ organisation_id: '', departement_id: '', nom: '', telephone: '', services: [] });
    const choisirOrganisation = (id) => {
        const org = organisations.find((o) => String(o.id) === String(id));
        setData((d) => ({ ...d, organisation_id: id, services: org?.services || [] }));
    };
    const basculerService = (s) => setData('services', data.services.includes(s) ? data.services.filter((x) => x !== s) : [...data.services, s]);

    return (
        <LayoutAdmin titre="Structures">
            <p className="max-w-3xl text-gris">Une antenne est une structure partenaire dans un département. Les signalements de ce département lui sont orientés selon ses services, puis ses agents sont prévenus par SMS.</p>
            <div className="mt-5 grid gap-6 xl:grid-cols-[1fr_24rem]">
                <div className="min-w-0">
                <Filtres url="/espace/structures" filtres={filtres} placeholder="Antenne, organisation ou département">
                    <select className="champ w-auto py-2" value={filtres.etat || ''} onChange={(e) => filtrer('/espace/structures', filtres, 'etat', e.target.value)} aria-label="État">
                        <option value="">Toutes les antennes</option>
                        <option value="sans_agent">Sans agent actif</option>
                        <option value="inactives">Désactivées</option>
                    </select>
                </Filtres>
                <div className="carte mt-4 overflow-x-auto">
                    <table className="w-full min-w-[46rem] text-left text-sm">
                        <thead className="border-b border-trait text-gris">
                            <tr>{['Antenne', 'Département', 'Services', 'Agents actifs', ''].map((h) => <th key={h} className="px-4 py-3 font-bold">{h}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-trait">
                            {structures.data.map((s) => (
                                <tr key={s.id} className={s.actif ? '' : 'opacity-50'}>
                                    <td className="px-4 py-3"><b className="text-nuit">{s.nom}</b>{s.telephone && <span className="block text-gris">{s.telephone}</span>}</td>
                                    <td className="px-4 py-3">{s.departement}<span className="block text-gris">{s.region}</span></td>
                                    <td className="px-4 py-3 text-gris">{s.services.map((x) => domaines[x] || x).join(', ')}</td>
                                    <td className="px-4 py-3">{s.agents > 0 ? s.agents : <span className="font-bold text-orange-fonce">Aucun</span>}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button type="button" className="font-bold text-violet" onClick={() => router.post(`/espace/structures/${s.id}/basculer`, {}, { preserveScroll: true })}>{s.actif ? 'Désactiver' : 'Réactiver'}</button>
                                    </td>
                                </tr>
                            ))}
                            {structures.data.length === 0 && <tr><td colSpan={5} className="px-4 py-10 text-center text-gris">{filtres.q || filtres.etat ? 'Aucune antenne ne correspond à cette recherche.' : 'Aucune antenne. Créez la première avec le formulaire.'}</td></tr>}
                        </tbody>
                    </table>
                </div>
                <Pagination page={structures} />
                </div>

                <form className="carte space-y-3 self-start p-6" onSubmit={(e) => { e.preventDefault(); post('/espace/structures', { preserveScroll: true, onSuccess: () => reset() }); }}>
                    <h2 className="text-lg font-bold">Créer une antenne</h2>
                    <label className="block">
                        <span className="etiquette text-sm">Organisation</span>
                        <select className="champ py-2" value={data.organisation_id} onChange={(e) => choisirOrganisation(e.target.value)}>
                            <option value="">Choisir…</option>
                            {organisations.map((o) => <option key={o.id} value={o.id}>{o.sigle ? `${o.sigle} · ` : ''}{o.nom}</option>)}
                        </select>
                        {errors.organisation_id && <span className="mt-1 block text-sm font-bold text-critique">{errors.organisation_id}</span>}
                    </label>
                    <label className="block">
                        <span className="etiquette text-sm">Département</span>
                        <select className="champ py-2" value={data.departement_id} onChange={(e) => setData('departement_id', e.target.value)}>
                            <option value="">Choisir…</option>
                            {departements.map((d) => <option key={d.id} value={d.id}>{d.nom}</option>)}
                        </select>
                        {errors.departement_id && <span className="mt-1 block text-sm font-bold text-critique">{errors.departement_id}</span>}
                    </label>
                    <label className="block">
                        <span className="etiquette text-sm">Nom affiché (facultatif)</span>
                        <input className="champ py-2" placeholder="ex. Boutique de droit AJS Mbour" value={data.nom} onChange={(e) => setData('nom', e.target.value)} />
                    </label>
                    <label className="block">
                        <span className="etiquette text-sm">Téléphone de l'antenne (donné aux victimes rappelées)</span>
                        <input type="tel" className="champ py-2" value={data.telephone} onChange={(e) => setData('telephone', e.target.value)} />
                    </label>
                    <fieldset>
                        <legend className="etiquette text-sm">Services proposés</legend>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(domaines).map(([v, n]) => (
                                <button key={v} type="button" className="puce py-1.5 text-sm" aria-pressed={data.services.includes(v)} onClick={() => basculerService(v)}>{n}</button>
                            ))}
                        </div>
                    </fieldset>
                    <button className="btn-violet w-full py-2.5" disabled={processing}>Créer l'antenne</button>
                </form>
            </div>
        </LayoutAdmin>
    );
}
