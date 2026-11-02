import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useT } from '../lib/i18n';
import FenetreChat from './FenetreChat';

/** Bulle de discussion présente sur toutes les pages publiques (sauf la page « Discuter » elle-même). */
export default function BulleChat() {
    const t = useT();
    const { feggu } = usePage().props;
    const [ouvert, setOuvert] = useState(false);

    useEffect(() => {
        if (!ouvert) return undefined;
        const echap = (e) => { if (e.key === 'Escape') setOuvert(false); };
        window.addEventListener('keydown', echap);
        return () => window.removeEventListener('keydown', echap);
    }, [ouvert]);

    return (
        <>
            {ouvert && (
                <section role="dialog" aria-label={t('chat_titre')}
                    className="fixed inset-x-2 bottom-2 top-16 z-40 flex flex-col overflow-hidden rounded-3xl bg-white shadow-2xl shadow-nuit/30 ring-1 ring-trait sm:inset-x-auto sm:bottom-24 sm:right-5 sm:top-auto sm:h-[36rem] sm:w-[25rem]">
                    <header className="flex items-center gap-3 bg-nuit px-4 py-3 text-white">
                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-orange text-sm font-extrabold" aria-hidden="true">{feggu.assistant ? 'IA' : 'F'}</span>
                        <div className="min-w-0 flex-1">
                            <p className="font-display font-bold leading-tight">{feggu.assistant ? t('chat_assistante') : t('chat_titre')}</p>
                            <p className="truncate text-xs text-white/70">{t('bulle_sous_titre')}</p>
                        </div>
                        <Link href="/parler" className="rounded-full px-2 py-1 text-xs font-bold text-white/80 hover:bg-white/10" title={t('bulle_agrandir')}>⤢</Link>
                        <button type="button" onClick={() => setOuvert(false)} className="rounded-full p-1.5 hover:bg-white/10" aria-label={t('bulle_fermer')}>
                            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </header>
                    <div className="min-h-0 flex-1"><FenetreChat compact /></div>
                </section>
            )}
            <button type="button" onClick={() => setOuvert(!ouvert)} aria-expanded={ouvert}
                className={`fixed bottom-4 right-4 z-40 items-center ${ouvert ? 'hidden sm:inline-flex' : 'inline-flex'} gap-2.5 rounded-full bg-orange py-3 pl-3 pr-5 font-bold text-white shadow-xl shadow-orange/30 transition hover:bg-orange-fonce sm:bottom-5 sm:right-5`}>
                <span className="relative grid size-9 place-items-center rounded-full bg-white/20" aria-hidden="true">
                    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinejoin="round"><path d="M4 5h16v11H9l-5 4z" /></svg>
                    {!ouvert && <span className="absolute -right-0.5 -top-0.5 size-3 animate-pulse rounded-full bg-white ring-2 ring-orange" />}
                </span>
                {ouvert ? t('bulle_fermer') : t('bulle_ouvrir')}
            </button>
        </>
    );
}
