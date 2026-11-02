const STYLES = {
    critique: 'bg-critique text-white',
    haute: 'bg-haute/15 text-[#a8440b]',
    moyenne: 'bg-moyenne/15 text-[#7d5f00]',
    basse: 'bg-basse/15 text-[#4d5862]',
};
const NOMS = { critique: 'Critique', haute: 'Haute', moyenne: 'Moyenne', basse: 'Basse' };

export default function Urgence({ niveau }) {
    return <span className={`inline-flex rounded-full px-2.5 py-0.5 text-sm font-bold ${STYLES[niveau] || STYLES.basse}`}>{NOMS[niveau] || niveau}</span>;
}

export function Statut({ statut, libelle }) {
    const couleurs = { nouveau: 'text-orange-fonce', oriente: 'text-violet', en_cours: 'text-violet', pris_en_charge: 'text-sur', clos: 'text-gris' };
    return <span className={`font-bold ${couleurs[statut] || 'text-gris'}`}>{libelle}</span>;
}
