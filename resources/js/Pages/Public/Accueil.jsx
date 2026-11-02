import { Link, usePage } from '@inertiajs/react';
import CercleDeFemmes from '../../Components/CercleDeFemmes';
import Icone from '../../Components/Icone';
import NumerosUrgence from '../../Components/NumerosUrgence';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { useT } from '../../lib/i18n';

function BoutonAlerte() {
    const t = useT();
    return (
        <Link href="/signaler" className="group relative grid aspect-square w-[52%] place-items-center rounded-full bg-orange text-center text-white shadow-[0_24px_60px_-18px_rgba(240,100,30,0.75)] transition-transform hover:scale-[1.02] focus-visible:outline-offset-8">
            <span className="absolute inset-3 rounded-full border-2 border-white/35" aria-hidden="true" />
            <span className="absolute inset-0 animate-[pulse-anneau_2.8s_ease-out_infinite] rounded-full border-4 border-orange motion-reduce:hidden" aria-hidden="true" />
            <span className="px-5 sm:px-7">
                <span className="block font-display text-[1.45rem] font-extrabold leading-tight sm:text-[1.9rem]">{t('alerter')}</span>
                <span className="mt-1.5 block text-xs font-bold leading-snug text-white/85 sm:text-sm">{t('alerter_sous')}</span>
            </span>
        </Link>
    );
}

function LigneCanal({ icone, nom, detail, cle, actif = true }) {
    const t = useT();
    return (
        <li className="grid grid-cols-[auto_1fr] items-start gap-x-4 gap-y-1 border-b border-trait py-5 sm:grid-cols-[auto_1fr_auto] sm:items-center">
            <span className="row-span-2 grid h-12 w-12 place-items-center rounded-2xl bg-lilas text-violet sm:row-span-1"><Icone nom={icone} /></span>
            <div>
                <h3 className="font-sans text-lg font-bold">{nom}</h3>
                <p className="text-gris">{detail}</p>
            </div>
            <p className={`col-start-2 font-display text-2xl font-extrabold sm:col-start-3 sm:text-right sm:text-3xl ${actif ? 'text-nuit' : 'text-gris/60'}`}>
                {actif ? cle : <span className="text-base font-bold">{t('bientot')}</span>}
            </p>
        </li>
    );
}

export default function Accueil({ chiffres }) {
    const t = useT();
    const { feggu } = usePage().props;
    const ussdActif = !!feggu.code_ussd;

    return (
        <LayoutPublic>
            <style>{'@keyframes pulse-anneau{0%{transform:scale(1);opacity:.55}100%{transform:scale(1.22);opacity:0}}'}</style>

            <section className="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 pt-12 sm:px-6 md:grid-cols-[1.1fr_1fr] md:pt-16">
                <div>
                    <h1 className="text-[3.1rem] font-extrabold leading-[0.98] sm:text-7xl lg:text-[5.5rem]" lang="wo">{t('hero_wo')}</h1>
                    <p className="mt-4 font-display text-2xl font-medium text-violet sm:text-3xl" lang="fr">{t('hero_fr')}</p>
                    <p className="mt-6 max-w-xl text-lg text-encre/85">{t('hero_texte')}</p>
                </div>
                <div className="flex flex-col items-center gap-6">
                    <div className="relative grid aspect-square w-full max-w-[23rem] place-items-center sm:max-w-[27rem]">
                        <CercleDeFemmes className="absolute inset-0 h-full w-full" />
                        <BoutonAlerte />
                    </div>
                    <p className="max-w-xs text-center text-gris">
                        {t('ou_sms')} <a href={`sms:${feggu.numero_court}`} className="font-display text-xl font-extrabold text-nuit underline decoration-orange decoration-2 underline-offset-4">{feggu.numero_court}</a>
                        <br />{t('sos_aide')}
                    </p>
                </div>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto max-w-6xl px-4 sm:px-6">
                    <div className="max-w-2xl">
                        <h2 className="text-3xl font-extrabold sm:text-4xl">{t('canaux_titre')}</h2>
                        <p className="mt-3 text-lg text-gris">{t('canaux_texte')}</p>
                    </div>
                    <ul className="mt-8 border-t border-trait">
                        <LigneCanal icone="sms" nom={t('canal_sms')} detail={t('canal_sms_detail')} cle={feggu.numero_court} />
                        <LigneCanal icone="ussd" nom={t('canal_ussd')} detail={t('canal_ussd_detail')} cle={feggu.ussd_choix ? <>{feggu.code_ussd} <span className="font-sans text-base font-bold text-gris">{t('puis')}</span> {feggu.ussd_choix}</> : feggu.code_ussd} actif={ussdActif} />
                        <LigneCanal icone="whatsapp" nom={t('canal_whatsapp')} detail={t('canal_whatsapp_detail')} cle={feggu.numero_whatsapp} actif={!!feggu.numero_whatsapp} />
                        <LigneCanal icone="bip" nom={t('canal_bip')} detail={t('canal_bip_detail')} cle={feggu.numero_vocal} actif={!!feggu.numero_vocal} />
                        <LigneCanal icone="voix" nom={t('canal_voix')} detail={t('canal_voix_detail')} cle={feggu.numero_vocal} actif={!!feggu.numero_vocal} />
                        <LigneCanal icone="web" nom={t('canal_web')} detail={t('canal_web_detail')} cle={<Link href="/parler" className="text-violet underline decoration-2 underline-offset-4">{t('nav_parler')}</Link>} />
                        <LigneCanal icone="relais" nom={t('canal_relais')} detail={t('canal_relais_detail')} cle="" />
                    </ul>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <h2 className="text-3xl font-extrabold sm:text-4xl">{t('ensuite_titre')}</h2>
                <ol className="mt-10 grid gap-10 md:grid-cols-3">
                    {[1, 2, 3].map((n) => (
                        <li key={n} className="relative">
                            <span className="font-display text-6xl font-extrabold text-orange/90">{n}</span>
                            <h3 className="mt-2 text-xl font-bold">{t(`ensuite_${n}`)}</h3>
                            <p className="mt-2 text-gris">{t(`ensuite_${n}_t`)}</p>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="mx-auto max-w-6xl px-4 sm:px-6">
                <div className="rounded-[2rem] bg-lilas px-6 py-10 sm:px-10">
                    <h2 className="text-3xl font-extrabold">{t('securite_titre')}</h2>
                    <div className="mt-8 grid gap-8 md:grid-cols-3">
                        {[['bouclier', 1], ['cadenas', 2], ['oeil', 3]].map(([icone, n]) => (
                            <div key={n} className="flex gap-4">
                                <Icone nom={icone} className="h-8 w-8 shrink-0 text-violet" />
                                <div>
                                    <h3 className="font-sans text-lg font-bold">{t(`securite_${n}`)}</h3>
                                    <p className="mt-1 text-encre/80">{t(`securite_${n}_t`)}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                    <Link href="/confidentialite" className="btn-discret -ml-5 mt-6">{t('en_savoir')}</Link>
                </div>
            </section>

            {chiffres.signalements > 0 && (
                <section className="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
                    <h2 className="sr-only">{t('chiffres_titre')}</h2>
                    <dl className="grid gap-6 sm:grid-cols-3">
                        {[['signalements', 'chiffres_signalements'], ['pris_en_charge', 'chiffres_pec'], ['departements', 'chiffres_dep']].map(([cle, lib]) => (
                            <div key={cle} className="flex flex-col-reverse border-l-4 border-orange pl-5">
                                <dt className="text-gris">{t(lib)}</dt>
                                <dd className="font-display text-5xl font-extrabold text-nuit">{chiffres[cle].toLocaleString('fr-FR')}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}

            <section className="mx-auto mt-16 max-w-6xl px-4 sm:px-6">
                <div className="rounded-[2rem] bg-nuit px-6 py-8 text-white sm:px-10">
                    <h2 className="text-2xl font-extrabold text-white">{t('urgence_titre')}</h2>
                    <p className="mb-4 mt-1 text-white/80">{t('urgence_texte')}</p>
                    <NumerosUrgence clair />
                </div>
            </section>
        </LayoutPublic>
    );
}
