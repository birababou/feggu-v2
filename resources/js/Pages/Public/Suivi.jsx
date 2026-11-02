import { useForm } from '@inertiajs/react';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { useT } from '../../lib/i18n';

const ORDRE = ['nouveau', 'oriente', 'en_cours', 'pris_en_charge', 'clos'];

export default function Suivi({ resultat }) {
    const t = useT();
    const { data, setData, post, processing, errors } = useForm({ reference: '' });
    const atteint = resultat ? ORDRE.indexOf(resultat.statut) : -1;

    return (
        <LayoutPublic titre={t('suivi_titre')}>
            <div className="mx-auto max-w-2xl px-4 py-14 sm:px-6">
                <h1 className="text-4xl font-extrabold">{t('suivi_titre')}</h1>
                <p className="mt-3 text-lg text-gris">{t('suivi_texte')}</p>

                <form className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(e) => { e.preventDefault(); post('/suivi'); }}>
                    <label className="block flex-1">
                        <span className="etiquette">{t('suivi_code')}</span>
                        <input className="champ font-display text-xl uppercase tracking-wider" placeholder="FG-ABCD-2345" autoComplete="off" autoCapitalize="characters"
                            value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                    </label>
                    <button className="btn-violet" disabled={processing || !data.reference}>{t('suivi_voir')}</button>
                </form>
                {errors.reference && <p className="mt-2 font-bold text-critique">{errors.reference}</p>}

                {resultat === false && <p className="mt-8 rounded-xl bg-critique/10 p-4 font-bold text-critique" role="alert">{t('suivi_inconnu')}</p>}

                {resultat && (
                    <section className="mt-10 rounded-3xl bg-white p-6 ring-1 ring-trait sm:p-8" aria-live="polite">
                        <p className="font-bold text-gris">{resultat.reference}</p>
                        <p className="mt-1 font-display text-2xl font-bold text-nuit">{resultat.statut_libelle}</p>
                        <ol className="mt-6 space-y-0">
                            {resultat.etapes.map((e, i) => {
                                const fait = i <= atteint;
                                return (
                                    <li key={e.cle} className="relative flex gap-4 pb-6 last:pb-0">
                                        {i < resultat.etapes.length - 1 && <span className={`absolute left-[11px] top-7 h-[calc(100%-1.25rem)] w-0.5 ${i < atteint ? 'bg-sur' : 'bg-trait'}`} aria-hidden="true" />}
                                        <span className={`relative mt-1 grid h-6 w-6 shrink-0 place-items-center rounded-full ${fait ? 'bg-sur text-white' : 'border-2 border-trait bg-white'}`} aria-hidden="true">
                                            {fait && <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="3"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>}
                                        </span>
                                        <div>
                                            <p className={`font-bold ${fait ? 'text-nuit' : 'text-gris'}`}>{t(`st_${e.cle}`)}</p>
                                            {e.date && <p className="text-sm text-gris">{new Date(e.date).toLocaleDateString('fr-FR')}</p>}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                )}
            </div>
        </LayoutPublic>
    );
}
