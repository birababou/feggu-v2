import { Head, Link, router, usePage } from '@inertiajs/react';
import Logo from '../Components/Logo';

const LIENS = [
    ['/espace', 'Tableau de bord', null],
    ['/espace/signalements', 'Signalements', null],
    ['/espace/simulateur', 'Simulateur des canaux', ['admin', 'superviseur']],
    ['/espace/structures', 'Structures', ['admin']],
    ['/espace/utilisateurs', 'Utilisateurs', ['admin']],
    ['/espace/journal', 'Journal d\'accès', ['admin']],
];

export default function LayoutAdmin({ titre, actions, children }) {
    const { auth, feggu, flash, url } = usePage().props;
    const chemin = usePage().url.split('?')[0];
    const actif = (href) => (href === '/espace' ? chemin === '/espace' : chemin.startsWith(href));

    return (
        <div className="min-h-screen bg-[#f6f4f9] lg:grid lg:grid-cols-[15rem_1fr]">
            <Head title={titre} />
            <aside className="bg-nuit text-white lg:sticky lg:top-0 lg:h-screen">
                <div className="flex items-center justify-between px-5 py-5 lg:block">
                    <Link href="/espace"><Logo clair /></Link>
                    <p className="text-sm text-white/60 lg:mt-1">Espace structures</p>
                </div>
                <nav className="flex gap-1 overflow-x-auto px-3 pb-3 lg:block lg:space-y-1 lg:overflow-visible" aria-label="Espace structures">
                    {LIENS.filter(([, , roles]) => !roles || roles.includes(auth.role)).map(([href, nom]) => (
                        <Link key={href} href={href} className={`block shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2.5 font-bold ${actif(href) ? 'bg-white/12 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white'}`}>{nom}</Link>
                    ))}
                </nav>
                <div className="hidden border-t border-white/10 px-5 py-4 text-sm lg:absolute lg:bottom-0 lg:block lg:w-full">
                    <p className="font-bold">{auth.nom}</p>
                    <p className="text-white/60">{auth.role_libelle}{auth.structure ? ` · ${auth.structure}` : ''}</p>
                    <button type="button" onClick={() => router.post('/espace/deconnexion')} className="mt-3 font-bold text-peche hover:text-white">Se déconnecter</button>
                </div>
            </aside>

            <div className="min-w-0">
                {feggu.demo && (
                    <p className="bg-peche px-6 py-2 text-sm font-bold text-orange-fonce">Mode démonstration : les signalements affichés sont fictifs.</p>
                )}
                <header className="flex flex-wrap items-center justify-between gap-4 px-6 pb-2 pt-7 lg:px-10">
                    <h1 className="text-3xl font-extrabold">{titre}</h1>
                    {actions}
                </header>
                {flash?.succes && <p className="mx-6 mt-3 rounded-xl bg-sur/10 px-4 py-3 font-bold text-sur lg:mx-10" role="status">{flash.succes}</p>}
                {flash?.erreur && <p className="mx-6 mt-3 rounded-xl bg-critique/10 px-4 py-3 font-bold text-critique lg:mx-10" role="alert">{flash.erreur}</p>}
                <main className="px-6 pb-16 pt-4 lg:px-10">{children}</main>
            </div>
        </div>
    );
}
