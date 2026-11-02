import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

// Recherche et filtres d'une liste du back-office : met à jour l'URL (partageable) sans recharger la page.
export function Filtres({ url, filtres, placeholder = 'Rechercher', children }) {
    const [q, setQ] = useState(filtres.q || '');
    const premier = useRef(true);
    useEffect(() => {
        if (premier.current) { premier.current = false; return; }
        const minuteur = setTimeout(() => router.get(url, { ...filtres, q: q || undefined, page: undefined }, { preserveState: true, preserveScroll: true, replace: true }), 350);
        return () => clearTimeout(minuteur);
    }, [q]);

    return (
        <div className="flex flex-wrap items-center gap-3">
            <label className="relative w-full max-w-xs">
                <span className="sr-only">{placeholder}</span>
                <svg viewBox="0 0 24 24" className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gris" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                <input type="search" className="champ py-2 pl-10" placeholder={placeholder} value={q} onChange={(e) => setQ(e.target.value)} />
            </label>
            {children}
        </div>
    );
}

export function filtrer(url, filtres, cle, valeur) {
    router.get(url, { ...filtres, [cle]: valeur || undefined, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

// Pagination à partir d'un paginateur Laravel.
export function Pagination({ page }) {
    if (!page || page.last_page <= 1) {
        return page ? <p className="mt-4 text-sm text-gris">{page.total} résultat{page.total > 1 ? 's' : ''}</p> : null;
    }
    return (
        <nav className="mt-5 flex flex-wrap items-center gap-1" aria-label="Pages">
            {page.links.map((l, i) => {
                const libelle = l.label.replace(/&laquo;\s*Previous|pagination\.previous/, '‹ Précédent').replace(/Next\s*&raquo;|pagination\.next/, 'Suivant ›');
                return l.url ? (
                    <Link key={i} href={l.url} preserveScroll preserveState className={`rounded-lg px-3 py-1.5 text-sm font-bold ${l.active ? 'bg-violet text-white' : 'bg-white text-nuit ring-1 ring-trait hover:bg-lilas'}`}>{libelle}</Link>
                ) : (
                    <span key={i} className="px-2 py-1.5 text-sm text-gris">{libelle}</span>
                );
            })}
            <span className="ml-3 text-sm text-gris">{page.from}–{page.to} sur {page.total}</span>
        </nav>
    );
}
