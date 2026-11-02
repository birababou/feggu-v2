import { useEffect, useRef } from 'react';
import { useT } from '../lib/i18n';
import { viderTraces } from '../lib/horsLigne';
import Icone from './Icone';

// Remplace la page par un site neutre, sans laisser cette page dans l'historique de retour.
export function quitter() {
    viderTraces();
    window.location.replace('https://www.google.com/search?q=m%C3%A9t%C3%A9o+s%C3%A9n%C3%A9gal');
}

export default function SortieRapide() {
    const t = useT();
    const dernierEchap = useRef(0);

    useEffect(() => {
        const surTouche = (e) => {
            if (e.key !== 'Escape') return;
            const maintenant = Date.now();
            if (maintenant - dernierEchap.current < 800) quitter();
            dernierEchap.current = maintenant;
        };
        window.addEventListener('keydown', surTouche);
        return () => window.removeEventListener('keydown', surTouche);
    }, []);

    return (
        <button type="button" onClick={quitter} title={t('quitter_aide')} className="fixed right-3 top-3 z-50 inline-flex items-center gap-2 rounded-full bg-nuit px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-nuit/20 hover:bg-black sm:right-5 sm:top-4">
            <Icone nom="sortie" className="h-5 w-5" />
            {t('quitter')}
        </button>
    );
}
