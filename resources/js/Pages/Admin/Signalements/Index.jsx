import { Link, router } from '@inertiajs/react';
import { Filtres, Pagination } from '../../../Components/Liste';
import Urgence, { Statut } from '../../../Components/Urgence';
import LayoutAdmin from '../../../Layouts/LayoutAdmin';

export default function Index({ signalements, filtres, options }) {
    const filtrer = (cle, valeur) => router.get('/espace/signalements', { ...filtres, [cle]: valeur || undefined, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <LayoutAdmin titre="Signalements">
            <Filtres url="/espace/signalements" filtres={filtres} placeholder="Référence, département ou structure">
                <select className="champ w-auto py-2" value={filtres.statut || ''} onChange={(e) => filtrer('statut', e.target.value)} aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    <option value="ouverts">En cours (non clos)</option>
                    {Object.entries(options.statuts).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                </select>
                <select className="champ w-auto py-2" value={filtres.urgence || ''} onChange={(e) => filtrer('urgence', e.target.value)} aria-label="Urgence">
                    <option value="">Toutes urgences</option>
                    {['critique', 'haute', 'moyenne', 'basse'].map((u) => <option key={u} value={u}>{u[0].toUpperCase() + u.slice(1)}</option>)}
                </select>
                <select className="champ w-auto py-2" value={filtres.canal || ''} onChange={(e) => filtrer('canal', e.target.value)} aria-label="Canal">
                    <option value="">Tous les canaux</option>
                    {Object.entries(options.canaux).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                </select>
                <select className="champ w-auto py-2" value={filtres.region || ''} onChange={(e) => filtrer('region', e.target.value)} aria-label="Région">
                    <option value="">Toutes régions</option>
                    {options.regions.map((r) => <option key={r.id} value={r.id}>{r.nom}</option>)}
                </select>
            </Filtres>

            <div className="carte mt-5 overflow-x-auto">
                <table className="w-full min-w-[52rem] text-left">
                    <thead className="border-b border-trait text-sm text-gris">
                        <tr>{['Urgence', 'Référence', 'Violence', 'Lieu', 'Canal', 'Structure', 'Statut', 'Reçu'].map((h) => <th key={h} className="px-4 py-3 font-bold">{h}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-trait">
                        {signalements.data.map((s) => (
                            <tr key={s.id} className="cursor-pointer hover:bg-fond" onClick={() => router.visit(`/espace/signalements/${s.id}`)}>
                                <td className="px-4 py-3"><Urgence niveau={s.urgence} /></td>
                                <td className="px-4 py-3"><Link href={`/espace/signalements/${s.id}`} className="font-bold text-violet">{s.reference}</Link></td>
                                <td className="px-4 py-3">{s.categorie || <span className="text-gris">Non précisé</span>}</td>
                                <td className="px-4 py-3">{s.departement || <span className="text-gris">Inconnu</span>}</td>
                                <td className="px-4 py-3 text-sm">{s.canal}</td>
                                <td className="px-4 py-3 text-sm">{s.structure || <span className="font-bold text-orange-fonce">À orienter</span>}</td>
                                <td className="px-4 py-3"><Statut statut={s.statut} libelle={s.statut_libelle} /></td>
                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gris" title={s.cree_le}>{s.depuis}</td>
                            </tr>
                        ))}
                        {signalements.data.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-gris">Aucun signalement ne correspond à ces filtres.</td></tr>}
                    </tbody>
                </table>
            </div>

            <Pagination page={signalements} />
        </LayoutAdmin>
    );
}
