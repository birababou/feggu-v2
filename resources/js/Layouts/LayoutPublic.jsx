import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import BulleChat from '../Components/BulleChat';
import Icone from '../Components/Icone';
import Logo from '../Components/Logo';
import SortieRapide from '../Components/SortieRapide';
import { useLangue, useT } from '../lib/i18n';

function ChoixLangue() {
    const langue = useLangue();
    const choisir = (l) => router.post(`/langue/${l}`, {}, { preserveScroll: true, preserveState: true });
    return (
        <div className="inline-flex rounded-full bg-lilas p-1 text-sm font-bold" role="group" aria-label="Langue / Làkk">
            {[['fr', 'Français'], ['wo', 'Wolof']].map(([code, nom]) => (
                <button key={code} type="button" onClick={() => choisir(code)} aria-pressed={langue === code}
                    className={`rounded-full px-3 py-1.5 ${langue === code ? 'bg-white text-nuit shadow-sm' : 'text-gris hover:text-nuit'}`}>
                    {nom}
                </button>
            ))}
        </div>
    );
}

export default function LayoutPublic({ titre, children, sansBulle = false }) {
    const t = useT();
    const { url } = usePage();
    const [menu, setMenu] = useState(false);
    const liens = [
        ['/signaler', t('nav_signaler')],
        ['/parler', t('nav_parler')],
        ['/suivi', t('nav_suivi')],
        ['/aide', t('nav_aide')],
    ];

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={titre} />
            <SortieRapide />
            {usePage().props.feggu.demo && (
                <p className="bg-peche px-4 py-2 pr-40 text-sm font-bold text-orange-fonce sm:px-6">Site de démonstration : les chiffres et les signalements sont fictifs. Pour un vrai signalement, utilisez feggu.org.</p>
            )}
            <header className="border-b border-trait/70 bg-fond/90 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 pr-40 sm:px-6 sm:pr-48">
                    <Link href="/" aria-label="FEGGU, accueil"><Logo /></Link>
                    <nav className="ml-6 hidden items-center gap-1 md:flex" aria-label="Navigation principale">
                        {liens.map(([href, nom]) => (
                            <Link key={href} href={href} className={`rounded-full px-3.5 py-2 font-bold hover:bg-lilas ${url.startsWith(href) ? 'text-violet' : 'text-nuit'}`}>{nom}</Link>
                        ))}
                    </nav>
                    <div className="ml-auto hidden items-center gap-3 md:flex">
                        <ChoixLangue />
                        <Link href="/espace/connexion" className="inline-flex items-center gap-1.5 rounded-full border-2 border-trait px-3.5 py-1.5 text-sm font-bold text-nuit hover:border-violet hover:text-violet">
                            <Icone nom="cadenas" className="h-4 w-4" />{t('se_connecter')}
                        </Link>
                    </div>
                    <button type="button" className="ml-auto rounded-full p-2 text-nuit md:hidden" onClick={() => setMenu(!menu)} aria-expanded={menu} aria-label="Menu">
                        <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d={menu ? 'M6 6l12 12M18 6L6 18' : 'M4 7h16M4 12h16M4 17h16'} /></svg>
                    </button>
                </div>
                {menu && (
                    <nav className="border-t border-trait px-4 pb-4 md:hidden" aria-label="Navigation">
                        {liens.map(([href, nom]) => (
                            <Link key={href} href={href} className="block border-b border-trait/60 py-3 text-lg font-bold text-nuit">{nom}</Link>
                        ))}
                        <Link href="/espace/connexion" className="flex items-center gap-2 border-b border-trait/60 py-3 text-lg font-bold text-violet"><Icone nom="cadenas" className="h-5 w-5" />{t('espace')}</Link>
                        <div className="pt-4"><ChoixLangue /></div>
                    </nav>
                )}
            </header>

            <main className="flex-1">{children}</main>
            {!sansBulle && <BulleChat />}

            <footer className="mt-20 bg-nuit text-white/80">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr]">
                    <div>
                        <Logo clair />
                        <p className="mt-4 max-w-md">{t('pied_porte')}</p>
                    </div>
                    <ul className="space-y-2 md:justify-self-end">
                        <li><Link href="/aide" className="hover:text-white">{t('nav_aide')}</Link></li>
                        <li><Link href="/confidentialite" className="hover:text-white">{t('pied_confidentialite')}</Link></li>
                        <li><Link href="/espace/connexion" className="hover:text-white">{t('espace')}</Link></li>
                        {usePage().props.feggu.code_source && <li><a href={usePage().props.feggu.code_source} className="hover:text-white" rel="noreferrer">{t('code_source')}</a></li>}
                    </ul>
                </div>
            </footer>
        </div>
    );
}
