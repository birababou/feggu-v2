import LayoutPublic from '../../Layouts/LayoutPublic';
import { useT } from '../../lib/i18n';

const SECTIONS = [
    ['Ce que nous recueillons', 'Uniquement ce qui sert à vous aider : le type de violence, le lieu (commune ou département), l\'urgence, vos besoins et, si vous le voulez, un numéro pour vous rappeler. Votre nom n\'est jamais demandé. Nous ne demandons plus l\'ethnie ni la nationalité.'],
    ['Qui peut lire votre signalement', 'Seule la structure d\'aide chargée de votre situation, et les responsables FEGGU de votre région. Chaque consultation est enregistrée dans un journal. Les statistiques publiques ne contiennent aucune information permettant de vous reconnaître.'],
    ['Comment c\'est protégé', 'Les récits, les numéros de téléphone et les échanges sont chiffrés dans la base de données. Ils sont hébergés sur un serveur dédié, sans service extérieur d\'intelligence artificielle : le modèle d\'IA tourne sur le serveur FEGGU. Les comptes des structures sont protégés par un mot de passe fort et, pour les responsables, par un code à usage unique.'],
    ['Sur votre téléphone', 'Nos SMS ne mentionnent jamais la violence : ils sont signés d\'un nom neutre. Le bouton « Quitter vite » ferme le site et ouvre la météo. Les pages de signalement ne restent pas dans le cache du navigateur. Si l\'application est installée, elle s\'appelle simplement « Infos ».'],
    ['Votre accord', 'Votre signalement n\'est transmis qu\'avec votre accord. Personne ne vous contacte si vous ne l\'avez pas demandé. Si un enfant est en danger immédiat, les services de protection de l\'enfance peuvent être prévenus, comme la loi le prévoit.'],
    ['Combien de temps', 'Les dossiers clos sont anonymisés après la durée fixée avec le laboratoire GESTES et l\'autorité de protection des données (CDP). Vous pouvez demander l\'effacement de votre signalement avec votre code de suivi.'],
];

export default function Confidentialite() {
    const t = useT();
    return (
        <LayoutPublic titre={t('pied_confidentialite')}>
            <div className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
                <h1 className="text-4xl font-extrabold">Vos données et votre sécurité</h1>
                <p className="mt-4 text-lg text-gris">FEGGU est conçu pour que signaler une violence ne vous mette jamais en danger.</p>
                <div className="mt-10 space-y-10">
                    {SECTIONS.map(([titre, texte]) => (
                        <section key={titre}>
                            <h2 className="text-2xl font-bold">{titre}</h2>
                            <p className="mt-2 max-w-prose text-encre/85">{texte}</p>
                        </section>
                    ))}
                </div>
            </div>
        </LayoutPublic>
    );
}
