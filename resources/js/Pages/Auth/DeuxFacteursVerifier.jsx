import { useForm } from '@inertiajs/react';
import Cadre from './Cadre';

export default function DeuxFacteursVerifier() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    return (
        <Cadre titre="Code de sécurité">
            <p className="mt-2 text-gris">Saisissez le code à 6 chiffres de votre application d'authentification.</p>
            <form className="mt-5 space-y-3" onSubmit={(e) => { e.preventDefault(); post('/espace/securite/verifier'); }}>
                <input className="champ text-center font-display text-2xl tracking-[0.3em]" inputMode="numeric" autoComplete="one-time-code" maxLength={6} autoFocus value={data.code} onChange={(e) => setData('code', e.target.value)} aria-label="Code à 6 chiffres" />
                {errors.code && <p className="font-bold text-critique">{errors.code}</p>}
                <button className="btn-violet w-full" disabled={processing}>Valider</button>
            </form>
        </Cadre>
    );
}
