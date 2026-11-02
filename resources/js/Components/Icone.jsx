// Pictogrammes simples, pour que chaque choix se reconnaisse sans savoir lire.
const TRACES = {
    sexuelle: <><path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3z" /><path d="M9 12l2 2 4-4" /></>,
    physique: <><path d="M7 11V7a2 2 0 014 0v3" /><path d="M11 9V6a2 2 0 014 0v4" /><path d="M15 9a2 2 0 014 0v4a7 7 0 01-7 7h-1a6 6 0 01-6-6v-2a2 2 0 014 0" /></>,
    psychologique: <><path d="M4 5h16v11H9l-5 4V5z" /><path d="M8 10l2-2 2 3 2-3 2 2" /></>,
    economique: <><path d="M3 11h18a9 9 0 01-18 0z" /><path d="M8 7c0-1 1-2 2-2M13 7c0-1 1-2 2-2" /></>,
    enfant: <><circle cx="12" cy="6" r="3" /><path d="M12 9v6M8 12h8M9 21l3-6 3 6" /></>,
    mgf: <><circle cx="12" cy="7" r="3" /><path d="M7 21v-4a5 5 0 0110 0v4" /><path d="M4 4l16 16" /></>,
    mariage: <><circle cx="9" cy="14" r="5" /><circle cx="15" cy="14" r="5" /><path d="M10 4l2 3 2-3" /></>,
    exploitation: <><rect x="3" y="9" width="8" height="6" rx="3" /><rect x="13" y="9" width="8" height="6" rx="3" /><path d="M9 12h6" /></>,
    autre: <><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.5M12 17h.01" /></>,
    sms: <><path d="M4 5h16v11H9l-5 4V5z" /><path d="M8 10h8M8 13h5" /></>,
    ussd: <><rect x="6" y="2" width="12" height="20" rx="2.5" /><path d="M9 7h6M9 11h.01M12 11h.01M15 11h.01M9 14h.01M12 14h.01M15 14h.01" /></>,
    whatsapp: <><path d="M4 20l1.5-4A8 8 0 1112 20a8 8 0 01-4-1l-4 1z" /><path d="M9 9c0 3 2 5 5 5l1-1.5-2-1-1 1c-1-.4-1.6-1-2-2l1-1-1-2L9 9z" /></>,
    bip: <><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z" /><path d="M16 3l5 5M21 3l-5 5" /></>,
    voix: <><rect x="9" y="3" width="6" height="11" rx="3" /><path d="M5 11a7 7 0 0014 0M12 18v3" /></>,
    web: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" /></>,
    relais: <><circle cx="8" cy="8" r="3" /><circle cx="17" cy="9" r="2.5" /><path d="M3 20a5 5 0 0110 0M14 20a4 4 0 017 0" /></>,
    bouclier: <><path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3z" /></>,
    cadenas: <><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 018 0v3" /></>,
    oeil: <><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" /><path d="M4 20L20 4" /></>,
    tel: <><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z" /></>,
    sortie: <><path d="M14 4h5v16h-5" /><path d="M10 8l-4 4 4 4M6 12h10" /></>,
};

export default function Icone({ nom, className = 'h-6 w-6', titre }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden={titre ? undefined : true} role={titre ? 'img' : undefined}>
            {titre && <title>{titre}</title>}
            {TRACES[nom] ?? TRACES.autre}
        </svg>
    );
}
