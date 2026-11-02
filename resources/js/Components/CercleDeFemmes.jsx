// Illustration de l'accueil : des femmes et une enfant en cercle autour du bouton d'alerte.
// L'idée : la communauté qui entoure et protège (FEGGU, « se protéger »). Visages sans traits, pour que chacune puisse s'y reconnaître.

const PEAUX = ['#5b3423', '#7a4a30', '#4a2a1c', '#93593a', '#6a3d28'];

function Buste({ x, y, peau, habit, coiffe, coiffeType = 'musoor', echelle = 1, motif }) {
    return (
        <g transform={`translate(${x} ${y}) scale(${echelle})`}>
            {/* Épaules et habit */}
            <path d="M-46 58 C-46 20 -26 10 0 10 C26 10 46 20 46 58 Z" fill={habit} />
            {motif && <path d="M-30 30 L-18 46 M-8 28 L4 44 M14 28 L26 44" stroke={motif} strokeWidth="3.2" strokeLinecap="round" opacity="0.7" />}
            {/* Cou et visage */}
            <rect x="-7" y="-2" width="14" height="16" rx="6" fill={peau} />
            <ellipse cx="0" cy="-16" rx="16.5" ry="19.5" fill={peau} />
            {/* Coiffe */}
            {coiffeType === 'musoor' && (
                <>
                    <path d="M-19 -18 C-25 -52 25 -58 21 -20 C13 -31 -9 -33 -19 -18 Z" fill={coiffe} />
                    <path d="M8 -44 C24 -56 32 -40 20 -34 C26 -44 16 -48 8 -44 Z" fill={coiffe} />
                </>
            )}
            {coiffeType === 'chignon' && (
                <>
                    <path d="M-17 -16 C-20 -42 20 -42 17 -16 C12 -28 -12 -28 -17 -16 Z" fill={coiffe} />
                    <circle cx="0" cy="-40" r="9" fill={coiffe} />
                </>
            )}
            {coiffeType === 'tresses' && (
                <>
                    <path d="M-17 -14 C-21 -42 21 -42 17 -14 C12 -27 -12 -27 -17 -14 Z" fill={coiffe} />
                    <path d="M-15 -14 L-19 8 M15 -14 L19 8" stroke={coiffe} strokeWidth="5" strokeLinecap="round" />
                </>
            )}
        </g>
    );
}

export default function CercleDeFemmes({ className = '' }) {
    const c = 240;
    const r = 182;
    const figures = [
        { a: -90, habit: '#5a2d82', coiffe: '#f0641e', peau: PEAUX[0], motif: '#ffd2b8' },
        { a: -40, habit: '#2f7d5b', coiffe: '#241339', peau: PEAUX[1], coiffeType: 'chignon' },
        { a: 8, habit: '#f0641e', coiffe: '#5a2d82', peau: PEAUX[2], motif: '#ffffff' },
        { a: 52, habit: '#8a5cb8', coiffe: '#2f7d5b', peau: PEAUX[3] },
        { a: 94, habit: '#241339', coiffe: '#f0641e', peau: PEAUX[4], coiffeType: 'tresses', echelle: 0.8 },
        { a: 136, habit: '#c94c0c', coiffe: '#8a5cb8', peau: PEAUX[1], motif: '#ffd2b8' },
        { a: 180, habit: '#5a2d82', coiffe: '#ffd2b8', peau: PEAUX[0], coiffeType: 'chignon' },
        { a: 226, habit: '#2f7d5b', coiffe: '#f0641e', peau: PEAUX[3], motif: '#ffffff' },
    ];

    return (
        <svg viewBox="0 0 480 480" className={className} aria-hidden="true">
            <circle cx={c} cy={c} r="236" fill="#efe7f8" />
            <circle cx={c} cy={c} r="214" fill="none" stroke="#d9c8ee" strokeWidth="2" strokeDasharray="2 10" strokeLinecap="round" />
            <circle cx={c} cy={c} r="128" fill="#ffe8da" />
            {/* Bras qui se tiennent : un anneau qui relie les figures */}
            <circle cx={c} cy={c} r={r - 34} fill="none" stroke="#f6b896" strokeWidth="5" opacity="0.6" />
            {figures.map((f, i) => {
                const rad = (f.a * Math.PI) / 180;
                return <Buste key={i} {...f} x={c + r * Math.cos(rad)} y={c + r * Math.sin(rad)} echelle={(f.echelle ?? 1) * 0.92} />;
            })}
        </svg>
    );
}
