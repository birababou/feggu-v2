import { usePage } from '@inertiajs/react';
import { useLangue } from '../lib/i18n';
import Icone from './Icone';

export default function NumerosUrgence({ clair = false }) {
    const { feggu } = usePage().props;
    const langue = useLangue();
    return (
        <ul className="flex flex-wrap gap-2">
            {feggu.numeros_urgence.map((n) => (
                <li key={n.numero}>
                    <a href={`tel:${n.numero.replace(/\s/g, '')}`} className={`inline-flex items-center gap-2 rounded-full px-4 py-2 font-bold ${clair ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-white text-nuit ring-2 ring-critique/20 hover:ring-critique'}`}>
                        <Icone nom="tel" className="h-4 w-4" />
                        <span>{langue === 'wo' ? n.nom_wo : n.nom}</span>
                        <span className={`whitespace-nowrap ${clair ? "text-peche" : "text-critique"}`}>{n.numero}</span>
                    </a>
                </li>
            ))}
        </ul>
    );
}
