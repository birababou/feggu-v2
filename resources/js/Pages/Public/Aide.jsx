import NumerosUrgence from '../../Components/NumerosUrgence';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { useLangue, useT } from '../../lib/i18n';

const DOMAINES = {
    juridique: 'Aide juridique', medical: 'Soins', psychologique: 'Écoute', hebergement: 'Hébergement',
    economique: 'Insertion', securite: 'Protection', mediation: 'Médiation', protection_enfant: 'Enfance',
};

const DROITS = {
    fr: [
        ['Le viol est un crime', 'Depuis la loi de 2020, le viol et la pédophilie sont des crimes au Sénégal, punis de lourdes peines de prison.'],
        ['L\'excision est interdite', 'Les mutilations génitales féminines sont punies par la loi depuis 1999, y compris pour celles et ceux qui les organisent.'],
        ['Les coups sont punis, aussi dans le couple', 'Les violences entre époux ne sont pas une affaire privée : elles sont réprimées par le Code pénal.'],
        ['Vous avez droit à une aide gratuite', 'Les boutiques de droit de l\'AJS, les services sociaux et les structures de santé vous accueillent gratuitement.'],
        ['Un certificat médical aide à faire valoir vos droits', 'Après une agression, consultez si possible dans les 72 heures, même sans plainte.'],
    ],
    wo: [
        ['Sàcc jëmm ab bàkkaar bu mag la', 'Ci yoonu 2020, sàcc jëmm ak fitnaal xale ab bàkkaar bu mag la ci Senegaal, te kaso bu yàgg lay jur.'],
        ['Xaraf tere nañu ko', 'Xarafu janq yi, yoon tere na ko dale ko ci 1999, ba ci ñiy waajal.'],
        ['Dóor dañu koy mbugal, ba ci séy', 'Fitna ci diggante jëkkër ak jabar du mbiru kër rekk: yoon dina ko mbugal.'],
        ['Am nga sañ-sañ ci ndimbal bu amul pay', 'Boutique de droit yu AJS, sarwiis sosiyaal ak wér-gi-yaram dañu lay dalal te doo fey dara.'],
        ['Kaayitu doktoor mën na la dimbali', 'Ginnaaw ab fitna, su la neexee seetal doktoor ci biir 72 waxtu, te bul xaar a tëral plent.'],
    ],
};

export default function Aide({ organisations }) {
    const t = useT();
    const langue = useLangue();
    return (
        <LayoutPublic titre={t('nav_aide')}>
            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6">
                <h1 className="text-4xl font-extrabold">{t('nav_aide')}</h1>

                <section className="mt-8 rounded-3xl bg-nuit p-6 text-white sm:p-8">
                    <h2 className="text-2xl font-extrabold text-white">{t('urgence_titre')}</h2>
                    <p className="mb-4 mt-1 text-white/80">{t('urgence_texte')}</p>
                    <NumerosUrgence clair />
                </section>

                <section className="mt-12">
                    <h2 className="text-3xl font-extrabold">{langue === 'wo' ? 'Say yelleef' : 'Vos droits'}</h2>
                    <dl className="mt-6 divide-y divide-trait border-y border-trait">
                        {DROITS[langue === 'wo' ? 'wo' : 'fr'].map(([titre, texte]) => (
                            <div key={titre} className="grid gap-1 py-5 sm:grid-cols-[1fr_1.6fr] sm:gap-8">
                                <dt className="font-bold text-nuit">{titre}</dt>
                                <dd className="text-encre/85">{texte}</dd>
                            </div>
                        ))}
                    </dl>
                    <p className="mt-3 text-sm text-gris">{langue === 'wo' ? 'Xibaar yii, GESTES dina leen xoolaat.' : 'Informations générales, à faire valider par GESTES. Elles ne remplacent pas un conseil juridique.'}</p>
                </section>

                <section className="mt-12">
                    <h2 className="text-3xl font-extrabold">{langue === 'wo' ? 'Mbootaay yiy dimbali' : 'Les structures d\'aide partenaires'}</h2>
                    <ul className="mt-6 grid gap-x-8 sm:grid-cols-2">
                        {organisations.map((o) => (
                            <li key={o.nom} className="border-b border-trait py-4">
                                <p className="font-bold text-nuit">{o.nom}{o.sigle && o.sigle !== o.nom ? ` (${o.sigle})` : ''}</p>
                                <p className="mt-1 text-sm text-gris">{(o.services || []).map((s) => DOMAINES[s] || s).join(' · ')}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </LayoutPublic>
    );
}
