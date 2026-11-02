export default function Logo({ clair = false, className = '' }) {
    return (
        <span className={`inline-flex items-center gap-2.5 ${className}`}>
            <svg viewBox="0 0 40 40" className="h-9 w-9 shrink-0" aria-hidden="true">
                <circle cx="20" cy="20" r="19" fill={clair ? '#fff' : '#241339'} />
                <circle cx="20" cy="20" r="11" fill="#f0641e" />
                <circle cx="20" cy="20" r="4" fill={clair ? '#241339' : '#fff'} />
            </svg>
            <span className={`font-display text-2xl font-extrabold tracking-tight ${clair ? 'text-white' : 'text-nuit'}`}>FEGGU</span>
        </span>
    );
}
