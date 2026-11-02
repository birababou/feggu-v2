import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icone from '../../Components/Icone';
import NumerosUrgence from '../../Components/NumerosUrgence';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { mettreEnFile } from '../../lib/horsLigne';
import { useLangue, useT } from '../../lib/i18n';

function Choix({ options, valeur, onChange, multiple = false, label }) {
    const actif = (v) => (multiple ? (valeur || []).includes(v) : valeur === v);
    const basculer = (v) => {
        if (!multiple) return onChange(valeur === v ? null : v);
        const liste = valeur || [];
        onChange(liste.includes(v) ? liste.filter((x) => x !== v) : [...liste, v]);
    };
    return (
        <div className="flex flex-wrap gap-2" role="group" aria-label={label}>
            {options.map(([v, nom]) => (
                <button key={String(v)} type="button" className="puce" aria-pressed={actif(v)} onClick={() => basculer(v)}>
                    {actif(v) && <svg viewBox="0 0 20 20" className="h-4 w-4 text-violet" fill="currentColor" aria-hidden="true"><path d="M7.6 13.4L4.2 10l-1.4 1.4 4.8 4.8 10-10-1.4-1.4z" /></svg>}
                    {nom}
                </button>
            ))}
        </div>
    );
}

function Question({ titre, aide, children, erreur }) {
    return (
        <fieldset className="border-b border-trait py-7 last:border-0">
            <legend className="float-left w-full font-display text-xl font-bold text-nuit">{titre}</legend>
            {aide && <p className="clear-both pt-1 text-gris">{aide}</p>}
            <div className="clear-both pt-4">{children}</div>
            {erreur && <p className="mt-2 font-bold text-critique">{erreur}</p>}
        </fieldset>
    );
}

function Liste({ label, valeur, options, onChange, desactive }) {
    const t = useT();
    return (
        <label className="block">
            <span className="etiquette">{label}</span>
            <select className="champ" value={valeur ?? ''} disabled={desactive} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}>
                <option value="">{t('choisir')}</option>
                {options.map((o) => <option key={o.id} value={o.id}>{o.nom}</option>)}
            </select>
        </label>
    );
}

export default function Signaler({ regions, categories, besoins }) {
    const t = useT();
    const langue = useLangue();
    const [etape, setEtape] = useState(1);
    const [departements, setDepartements] = useState([]);
    const [communes, setCommunes] = useState([]);
    const [gardeHorsLigne, setGardeHorsLigne] = useState(false);
    const [manque, setManque] = useState(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        declarant: null, danger_immediat: null, categorie_id: null, types: [],
        region_id: null, departement_id: null, commune_id: null, lieu: null, periode_faits: null,
        victime_sexe: null, victime_tranche_age: null, victime_handicap: false,
        besoins: [], recit: '', consentement_contact: false, contact_telephone: '', consentement_partage: false, langue, _autre: false,
    });

    const charger = (url, set) => fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then(set).catch(() => set([]));
    useEffect(() => { setDepartements([]); if (data.region_id) charger(`/api/departements?region=${data.region_id}`, setDepartements); }, [data.region_id]);
    useEffect(() => { setCommunes([]); if (data.departement_id) charger(`/api/communes?departement=${data.departement_id}`, setCommunes); }, [data.departement_id]);
    useEffect(() => { window.scrollTo({ top: 0 }); }, [etape]);

    const categorie = categories.find((c) => c.id === data.categorie_id);

    const continuer = () => {
        if (etape === 1 && (!data.declarant || data.danger_immediat === null)) {
            setManque(t('qui') + ' / ' + t('danger'));
            return;
        }
        setManque(null);
        setEtape(etape + 1);
    };

    const envoyer = (e) => {
        e.preventDefault();
        if (!data.consentement_partage) {
            setManque(t('consentement'));
            return;
        }
        if (!navigator.onLine) {
            const { _autre, ...d } = data;
            mettreEnFile({ ...d, danger_immediat: !!d.danger_immediat });
            setGardeHorsLigne(true);
            return;
        }
        transform(({ _autre, ...d }) => ({ ...d, danger_immediat: !!d.danger_immediat }));
        post('/signaler');
    };

    if (gardeHorsLigne) {
        return (
            <LayoutPublic titre={t('nav_signaler')}>
                <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
                    <div className="rounded-3xl bg-peche p-8">
                        <h1 className="text-3xl font-extrabold">{t('hors_ligne_ok')}</h1>
                        <p className="mt-4">{t('hors_ligne')}</p>
                        <div className="mt-6"><NumerosUrgence /></div>
                    </div>
                </div>
            </LayoutPublic>
        );
    }

    const titres = [t('e1_titre'), t('e2_titre'), t('e3_titre')];

    return (
        <LayoutPublic titre={t('nav_signaler')}>
            <form onSubmit={envoyer} className="mx-auto max-w-2xl px-4 py-10 sm:px-6" noValidate>
                <p className="font-bold text-violet">{t('etape')} {etape} {t('sur')} 3</p>
                <h1 className="mt-1 text-4xl font-extrabold">{titres[etape - 1]}</h1>
                <div className="mt-5 flex gap-2" aria-hidden="true">
                    {[1, 2, 3].map((n) => <span key={n} className={`h-2 flex-1 rounded-full ${n <= etape ? 'bg-orange' : 'bg-trait'}`} />)}
                </div>

                {etape === 1 && (
                    <div className="mt-4">
                        <Question titre={t('qui')}>
                            <Choix label={t('qui')} valeur={data.declarant} onChange={(v) => setData('declarant', v)}
                                options={[['victime', t('qui_moi')], ['temoin', t('qui_autre')], ['relais', t('qui_relais')]]} />
                        </Question>

                        <Question titre={t('danger')}>
                            <div className="grid grid-cols-2 gap-3" role="group" aria-label={t('danger')}>
                                {[[true, t('oui')], [false, t('non')]].map(([v, nom]) => (
                                    <button key={nom} type="button" aria-pressed={data.danger_immediat === v} onClick={() => setData('danger_immediat', v)}
                                        className={`rounded-2xl border-2 px-4 py-4 text-xl font-bold ${data.danger_immediat === v ? (v ? 'border-critique bg-critique text-white' : 'border-violet bg-lilas text-nuit') : 'border-trait bg-white text-nuit hover:border-violet-clair'}`}>
                                        {nom}
                                    </button>
                                ))}
                            </div>
                            {data.danger_immediat === true && (
                                <div className="mt-5 rounded-2xl border-2 border-critique/30 bg-critique/5 p-5" role="alert">
                                    <p className="font-bold text-critique">{t('danger_alerte')}</p>
                                    <div className="mt-3"><NumerosUrgence /></div>
                                    <p className="mt-3 text-encre/80">{t('danger_suite')}</p>
                                </div>
                            )}
                        </Question>

                        <Question titre={t('categorie')} aide={t('categorie_aide')}>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {[...categories, { id: null, nom: t('autre_ne_sait'), nom_wo: t('autre_ne_sait'), icone: 'autre' }].map((c) => {
                                    const choisi = c.id === null ? data.categorie_id === null && data.types.length === 0 && data._autre : data.categorie_id === c.id;
                                    return (
                                        <button key={String(c.id)} type="button" aria-pressed={!!choisi}
                                            onClick={() => { setData((d) => ({ ...d, categorie_id: c.id, types: [], _autre: c.id === null })); }}
                                            className={`flex flex-col items-start gap-3 rounded-2xl border-2 p-4 text-left font-bold leading-snug ${choisi ? 'border-violet bg-lilas text-nuit' : 'border-trait bg-white hover:border-violet-clair'}`}>
                                            <Icone nom={c.icone} className={`h-8 w-8 ${choisi ? 'text-violet' : 'text-violet-clair'}`} />
                                            <span>{langue === 'wo' && c.nom_wo ? c.nom_wo : c.nom}</span>
                                        </button>
                                    );
                                })}
                            </div>
                            {categorie && categorie.types.length > 0 && (
                                <div className="mt-5">
                                    <p className="etiquette">{t('preciser')}</p>
                                    <Choix multiple label={t('preciser')} valeur={data.types} onChange={(v) => setData('types', v)}
                                        options={[...new Map(categorie.types.map((ty) => [ty.nom, ty])).values()].map((ty) => [ty.id, ty.nom])} />
                                </div>
                            )}
                        </Question>
                    </div>
                )}

                {etape === 2 && (
                    <div className="mt-4">
                        <Question titre={t('e2_titre')}>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Liste label={t('region')} valeur={data.region_id} options={regions}
                                    onChange={(v) => setData((d) => ({ ...d, region_id: v, departement_id: null, commune_id: null }))} />
                                <Liste label={t('departement')} valeur={data.departement_id} options={departements} desactive={!departements.length}
                                    onChange={(v) => setData((d) => ({ ...d, departement_id: v, commune_id: null }))} />
                                <Liste label={t('commune')} valeur={data.commune_id} options={communes} desactive={!communes.length}
                                    onChange={(v) => setData('commune_id', v)} />
                            </div>
                        </Question>
                        <Question titre={t('type_lieu')}>
                            <Choix label={t('type_lieu')} valeur={data.lieu} onChange={(v) => setData('lieu', v)}
                                options={[['maison', t('lieu_maison')], ['ecole', t('lieu_ecole')], ['travail', t('lieu_travail')], ['rue', t('lieu_rue')], ['autre', t('lieu_autre')]]} />
                        </Question>
                        <Question titre={t('quand')}>
                            <Choix label={t('quand')} valeur={data.periode_faits} onChange={(v) => setData('periode_faits', v)}
                                options={[['en_cours', t('quand_en_cours')], ['aujourdhui', t('quand_aujourdhui')], ['semaine', t('quand_semaine')], ['mois', t('quand_mois')], ['plus_ancien', t('quand_ancien')]]} />
                        </Question>
                        <Question titre={t('victime')}>
                            <div className="space-y-4">
                                <Choix label={t('victime')} valeur={data.victime_sexe} onChange={(v) => setData('victime_sexe', v)} options={[['F', t('sexe_f')], ['M', t('sexe_m')]]} />
                                <div>
                                    <p className="etiquette">{t('age')}</p>
                                    <Choix label={t('age')} valeur={data.victime_tranche_age} onChange={(v) => setData('victime_tranche_age', v)}
                                        options={['0-11', '12-17', '18-24', '25-49', '50+'].map((a) => [a, `${a} ${t('ans')}`])} />
                                </div>
                                <label className="flex items-center gap-3 font-bold">
                                    <input type="checkbox" className="h-5 w-5 accent-violet" checked={data.victime_handicap} onChange={(e) => setData('victime_handicap', e.target.checked)} />
                                    {t('handicap')}
                                </label>
                            </div>
                        </Question>
                    </div>
                )}

                {etape === 3 && (
                    <div className="mt-4">
                        <Question titre={t('besoins')} aide={t('besoins_aide')}>
                            <Choix multiple label={t('besoins')} valeur={data.besoins} onChange={(v) => setData('besoins', v)}
                                options={besoins.map((b) => [b.code, langue === 'wo' && b.nom_wo ? b.nom_wo : b.nom])} />
                        </Question>
                        <Question titre={t('recit')} aide={t('recit_aide')} erreur={errors.recit}>
                            <textarea className="champ min-h-32" maxLength={3000} value={data.recit} onChange={(e) => setData('recit', e.target.value)} aria-label={t('recit')} />
                        </Question>
                        <Question titre={t('rappel')} erreur={errors.contact_telephone}>
                            <label className="flex items-center gap-3 font-bold">
                                <input type="checkbox" className="h-5 w-5 accent-violet" checked={data.consentement_contact} onChange={(e) => setData('consentement_contact', e.target.checked)} />
                                {t('rappel')}
                            </label>
                            {data.consentement_contact && (
                                <label className="mt-4 block">
                                    <span className="etiquette">{t('telephone')}</span>
                                    <input type="tel" inputMode="tel" autoComplete="off" className="champ max-w-xs" value={data.contact_telephone} onChange={(e) => setData('contact_telephone', e.target.value)} />
                                    <span className="mt-1 block text-sm text-gris">{t('telephone_aide')}</span>
                                </label>
                            )}
                        </Question>
                        <div className="rounded-2xl bg-lilas p-5">
                            <label className="flex items-start gap-3 font-bold text-nuit">
                                <input type="checkbox" className="mt-1 h-5 w-5 shrink-0 accent-violet" checked={data.consentement_partage} onChange={(e) => setData('consentement_partage', e.target.checked)} />
                                {t('consentement')}
                            </label>
                            {errors.consentement_partage && <p className="mt-2 font-bold text-critique">{errors.consentement_partage}</p>}
                        </div>
                    </div>
                )}

                {manque && <p className="mt-6 rounded-xl bg-critique/10 p-4 font-bold text-critique" role="alert">{manque}</p>}

                <div className="mt-8 flex items-center justify-between gap-3">
                    {etape > 1 ? <button type="button" className="btn-discret -ml-5" onClick={() => setEtape(etape - 1)}>{t('precedent')}</button> : <span />}
                    {etape < 3
                        ? <button type="button" className="btn-violet px-8" onClick={continuer}>{t('suivant')}</button>
                        : <button type="submit" className="btn-alerte px-8 text-lg" disabled={processing}>{processing ? t('envoi') : t('envoyer')}</button>}
                </div>
            </form>
        </LayoutPublic>
    );
}
