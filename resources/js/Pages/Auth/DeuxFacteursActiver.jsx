import { useForm } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import Cadre from './Cadre';

export default function DeuxFacteursActiver({ secret, otpauth, obligatoire }) {
    const [qr, setQr] = useState(null);
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    useEffect(() => { QRCode.toDataURL(otpauth, { margin: 1, width: 220, color: { dark: '#241339' } }).then(setQr); }, [otpauth]);

    return (
        <Cadre titre="Activer la double authentification">
            <p className="mt-2 text-gris">{obligatoire ? 'Obligatoire pour votre rôle : ' : ''}Scannez ce code avec une application d'authentification (Google Authenticator, Microsoft Authenticator…), puis saisissez le code à 6 chiffres affiché.</p>
            {qr && <img src={qr} alt="QR code à scanner" className="mx-auto mt-5 h-52 w-52" />}
            <p className="mt-3 text-center text-sm text-gris">Clé manuelle : <code className="font-bold text-nuit">{secret}</code></p>
            <form className="mt-5 space-y-3" onSubmit={(e) => { e.preventDefault(); post('/espace/securite/activer'); }}>
                <input className="champ text-center font-display text-2xl tracking-[0.3em]" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={data.code} onChange={(e) => setData('code', e.target.value)} aria-label="Code à 6 chiffres" />
                {errors.code && <p className="font-bold text-critique">{errors.code}</p>}
                <button className="btn-violet w-full" disabled={processing}>Activer</button>
            </form>
        </Cadre>
    );
}
