import { useForm } from '@inertiajs/react';
import Cadre from './Cadre';

export default function Connexion() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });
    return (
        <Cadre titre="Espace des structures">
            <p className="mt-1 text-gris">Réservé aux structures partenaires de FEGGU.</p>
            <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); post('/espace/connexion'); }}>
                <label className="block">
                    <span className="etiquette">E-mail</span>
                    <input type="email" className="champ" autoComplete="username" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                </label>
                <label className="block">
                    <span className="etiquette">Mot de passe</span>
                    <input type="password" className="champ" autoComplete="current-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                </label>
                {errors.email && <p className="font-bold text-critique" role="alert">{errors.email}</p>}
                <button className="btn-violet w-full" disabled={processing}>Se connecter</button>
            </form>
        </Cadre>
    );
}
