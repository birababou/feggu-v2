// Barres horizontales : comparaison de quelques catégories, valeur affichée au bout de chaque barre.
export function BarresH({ donnees, couleur = 'var(--color-violet)' }) {
    const max = Math.max(1, ...donnees.map((d) => d.n));
    return (
        <ul className="space-y-2.5">
            {donnees.map((d) => (
                <li key={d.libelle} className="grid grid-cols-[minmax(7rem,11rem)_1fr] items-center gap-3 text-sm">
                    <span className="truncate text-encre" title={d.libelle}>{d.libelle}</span>
                    <span className="flex items-center gap-2">
                        <span className="h-3 rounded-r-sm" style={{ width: `${(d.n / max) * 100}%`, minWidth: 3, background: couleur }} />
                        <span className="font-bold tabular-nums text-nuit">{d.n}</span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

// Colonnes : évolution mensuelle sur 12 mois.
export function Colonnes({ donnees }) {
    const max = Math.max(1, ...donnees.map((d) => d.total));
    const h = 160;
    const l = 100 / donnees.length;
    return (
        <figure>
            <svg viewBox={`0 0 100 ${h}`} preserveAspectRatio="none" className="h-52 w-full" role="img" aria-label="Signalements par mois">
                {[0.5, 1].map((p) => <line key={p} x1="0" x2="100" y1={h - h * p} y2={h - h * p} stroke="var(--color-trait)" strokeWidth="0.3" vectorEffect="non-scaling-stroke" />)}
                {donnees.map((d, i) => {
                    const bh = (d.total / max) * (h - 14);
                    return (
                        <g key={d.mois}>
                            <rect x={i * l + l * 0.18} y={h - bh} width={l * 0.64} height={bh} rx="0.6" fill={i === donnees.length - 1 ? 'var(--color-orange)' : 'var(--color-violet)'}>
                                <title>{`${d.mois} : ${d.total}`}</title>
                            </rect>
                        </g>
                    );
                })}
            </svg>
            <div className="mt-2 grid text-center text-[11px] text-gris" style={{ gridTemplateColumns: `repeat(${donnees.length}, 1fr)` }}>
                {donnees.map((d) => <span key={d.mois}><b className="block text-sm text-nuit">{d.total}</b>{d.mois}</span>)}
            </div>
        </figure>
    );
}
