import { useState } from 'react';
import NumerosUrgence from '../../Components/NumerosUrgence';
import { quitter } from '../../Components/SortieRapide';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { useT } from '../../lib/i18n';

export default function Merci({ reference, critique }) {
    const t = useT();
    const [copie, setCopie] = useState(false);
    const copier = () => navigator.clipboard?.writeText(reference).then(() => setCopie(true)).catch(() => {});

    return (
        <LayoutPublic titre={t('merci_titre')}>
            <div className="mx-auto max-w-2xl px-4 py-14 sm:px-6">
                <span className="grid h-14 w-14 place-items-center rounded-full bg-sur text-white" aria-hidden="true">
                    <svg viewBox="0 0 24 24" className="h-8 w-8" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </span>
                <h1 className="mt-6 text-4xl font-extrabold">{t('merci_titre')}</h1>

                {critique && (
                    <div className="mt-6 rounded-2xl border-2 border-critique/30 bg-critique/5 p-5" role="alert">
                        <p className="font-bold text-critique">{t('merci_critique')}</p>
                        <div className="mt-3"><NumerosUrgence /></div>
                    </div>
                )}

                <div className="mt-8 rounded-3xl bg-white p-6 ring-1 ring-trait sm:p-8">
                    <p className="font-bold text-gris">{t('merci_code')}</p>
                    <p className="mt-2 select-all font-display text-4xl font-extrabold tracking-wide text-nuit sm:text-5xl">{reference}</p>
                    <p className="mt-3 text-encre/80">{t('merci_code_aide')}</p>
                    <button type="button" className="btn-ligne mt-5" onClick={copier}>{copie ? t('copie') : t('copier')}</button>
                </div>

                <p className="mt-8 text-lg">{t('merci_suite')}</p>

                <button type="button" className="btn mt-8 bg-nuit text-white hover:bg-black" onClick={quitter}>{t('merci_quitter')}</button>
            </div>
        </LayoutPublic>
    );
}
