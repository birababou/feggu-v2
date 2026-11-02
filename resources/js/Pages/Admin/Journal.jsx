import { Filtres, filtrer, Pagination } from '../../Components/Liste';
import LayoutAdmin from '../../Layouts/LayoutAdmin';

export default function Journal({ entrees, filtres, actions }) {
    return (
        <LayoutAdmin titre="Journal d'accès">
            <p className="text-gris">Chaque connexion, consultation de dossier, orientation et prise de contact est enregistrée.</p>
            <div className="mt-5">
                <Filtres url="/espace/journal" filtres={filtres} placeholder="Utilisateur ou adresse IP">
                    <select className="champ w-auto py-2" value={filtres.action || ''} onChange={(e) => filtrer('/espace/journal', filtres, 'action', e.target.value)} aria-label="Action">
                        <option value="">Toutes les actions</option>
                        {actions.map((a) => <option key={a} value={a}>{a.replaceAll('_', ' ')}</option>)}
                    </select>
                </Filtres>
            </div>
            <div className="carte mt-4 overflow-x-auto">
                <table className="w-full min-w-[40rem] text-left text-sm">
                    <thead className="border-b border-trait text-gris">
                        <tr>{['Date', 'Utilisateur', 'Action', 'Élément', 'Adresse IP'].map((h) => <th key={h} className="px-4 py-3 font-bold">{h}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-trait">
                        {entrees.data.map((e) => (
                            <tr key={e.id}>
                                <td className="whitespace-nowrap px-4 py-2.5 text-gris">{e.date}</td>
                                <td className="px-4 py-2.5">{e.utilisateur}</td>
                                <td className="px-4 py-2.5 font-bold text-nuit">{e.action.replaceAll('_', ' ')}</td>
                                <td className="px-4 py-2.5">{e.cible || '—'}</td>
                                <td className="px-4 py-2.5 text-gris">{e.ip}</td>
                            </tr>
                        ))}
                        {entrees.data.length === 0 && <tr><td colSpan={5} className="px-4 py-10 text-center text-gris">Aucune entrée ne correspond.</td></tr>}
                    </tbody>
                </table>
            </div>
            <Pagination page={entrees} />
        </LayoutAdmin>
    );
}
