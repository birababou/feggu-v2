import { router, useForm } from '@inertiajs/react';
import { Filtres, filtrer, Pagination } from '../../Components/Liste';
import LayoutAdmin from '../../Layouts/LayoutAdmin';

export default function Utilisateurs({ utilisateurs, filtres, roles, structures, regions }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', telephone: '', role: 'agent', structure_id: '', region_id: '', password: '' });
    const champ = (nom, label, props = {}) => (
        <label className="block">
            <span className="etiquette text-sm">{label}</span>
            <input className="champ py-2" value={data[nom]} onChange={(e) => setData(nom, e.target.value)} {...props} />
            {errors[nom] && <span className="mt-1 block text-sm font-bold text-critique">{errors[nom]}</span>}
        </label>
    );

    return (
        <LayoutAdmin titre="Utilisateurs">
            <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                <div className="min-w-0">
                <Filtres url="/espace/utilisateurs" filtres={filtres} placeholder="Nom, e-mail ou structure">
                    <select className="champ w-auto py-2" value={filtres.role || ''} onChange={(e) => filtrer('/espace/utilisateurs', filtres, 'role', e.target.value)} aria-label="Rôle">
                        <option value="">Tous les rôles</option>
                        {Object.entries(roles).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                    </select>
                    <select className="champ w-auto py-2" value={filtres.etat || ''} onChange={(e) => filtrer('/espace/utilisateurs', filtres, 'etat', e.target.value)} aria-label="État">
                        <option value="">Actifs et inactifs</option>
                        <option value="actifs">Actifs</option>
                        <option value="inactifs">Désactivés</option>
                    </select>
                </Filtres>
                <div className="carte mt-4 overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-left text-sm">
                        <thead className="border-b border-trait text-gris">
                            <tr>{['Nom', 'Rôle', 'Structure', 'Double authentification', 'Dernière connexion', ''].map((h) => <th key={h} className="px-4 py-3 font-bold">{h}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-trait">
                            {utilisateurs.data.map((u) => (
                                <tr key={u.id} className={u.actif ? '' : 'opacity-50'}>
                                    <td className="px-4 py-3"><b className="text-nuit">{u.nom}</b><span className="block text-gris">{u.email}</span></td>
                                    <td className="px-4 py-3">{u.role}</td>
                                    <td className="px-4 py-3">{u.structure || '—'}</td>
                                    <td className="px-4 py-3">{u.deux_facteurs ? <span className="font-bold text-sur">Activée</span> : <span className="text-gris">Non</span>}</td>
                                    <td className="px-4 py-3 text-gris">{u.derniere_connexion}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button type="button" className="font-bold text-violet" onClick={() => router.post(`/espace/utilisateurs/${u.id}/basculer`, {}, { preserveScroll: true })}>{u.actif ? 'Désactiver' : 'Réactiver'}</button>
                                    </td>
                                </tr>
                            ))}
                            {utilisateurs.data.length === 0 && <tr><td colSpan={6} className="px-4 py-10 text-center text-gris">Aucun compte ne correspond à cette recherche.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <Pagination page={utilisateurs} />
                </div>

                <form className="carte space-y-3 self-start p-6" onSubmit={(e) => { e.preventDefault(); post('/espace/utilisateurs', { preserveScroll: true, onSuccess: () => reset() }); }}>
                    <h2 className="text-lg font-bold">Créer un compte</h2>
                    {champ('name', 'Nom complet')}
                    {champ('email', 'E-mail', { type: 'email' })}
                    {champ('telephone', 'Téléphone (alertes SMS)', { type: 'tel' })}
                    <label className="block">
                        <span className="etiquette text-sm">Rôle</span>
                        <select className="champ py-2" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            {Object.entries(roles).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                        </select>
                    </label>
                    {data.role === 'agent' && (
                        <label className="block">
                            <span className="etiquette text-sm">Structure</span>
                            <select className="champ py-2" value={data.structure_id} onChange={(e) => setData('structure_id', e.target.value)}>
                                <option value="">Choisir…</option>
                                {structures.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}
                            </select>
                            {errors.structure_id && <span className="mt-1 block text-sm font-bold text-critique">{errors.structure_id}</span>}
                        </label>
                    )}
                    {data.role === 'superviseur' && (
                        <label className="block">
                            <span className="etiquette text-sm">Région supervisée</span>
                            <select className="champ py-2" value={data.region_id} onChange={(e) => setData('region_id', e.target.value)}>
                                <option value="">Choisir…</option>
                                {regions.map((r) => <option key={r.id} value={r.id}>{r.nom}</option>)}
                            </select>
                            {errors.region_id && <span className="mt-1 block text-sm font-bold text-critique">{errors.region_id}</span>}
                        </label>
                    )}
                    {champ('password', 'Mot de passe provisoire', { type: 'password', autoComplete: 'new-password' })}
                    <p className="text-xs text-gris">10 caractères minimum, avec majuscules, minuscules et chiffres.</p>
                    <button className="btn-violet w-full py-2.5" disabled={processing}>Créer le compte</button>
                </form>
            </div>
        </LayoutAdmin>
    );
}
