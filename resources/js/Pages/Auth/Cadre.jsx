import { Head, Link } from '@inertiajs/react';
import Logo from '../../Components/Logo';

export default function Cadre({ titre, children }) {
    return (
        <div className="grid min-h-screen place-items-center bg-nuit px-4 py-10">
            <Head title={titre} />
            <div className="w-full max-w-md">
                <div className="mb-6 flex items-center justify-between">
                    <Link href="/" aria-label="FEGGU, accueil"><Logo clair /></Link>
                    <Link href="/" className="rounded-full px-3 py-2 text-sm font-bold text-white/80 hover:bg-white/10 hover:text-white">← Retour à l'accueil</Link>
                </div>
                <div className="rounded-3xl bg-white p-7 sm:p-9">
                    <h1 className="text-2xl font-extrabold">{titre}</h1>
                    {children}
                </div>
            </div>
        </div>
    );
}
