import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useT } from '../lib/i18n';

function jetonXsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

// Les lignes « 1 Français » d'une réponse deviennent des boutons : plus simple sur un téléphone.
function options(texte) {
    return texte.split('\n').map((l) => l.match(/^(\d)\s+(.+)$/)).filter(Boolean).map((m) => ({ valeur: m[1], libelle: m[2] }));
}

/** Fil de discussion avec FEGGU (page « Discuter » et bulle flottante). Rien n'est gardé dans le navigateur. */
export default function FenetreChat({ compact = false }) {
    const t = useT();
    const { feggu } = usePage().props;
    const assistant = feggu.assistant;
    const [messages, setMessages] = useState([]);
    const [texte, setTexte] = useState('');
    const [attente, setAttente] = useState(false);
    const fil = useRef(null);
    const champ = useRef(null);

    const envoyer = async (contenu) => {
        if (contenu) setMessages((m) => [...m, { de: 'moi', texte: contenu }]);
        setTexte('');
        setAttente(true);
        try {
            const r = await fetch('/parler/message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': jetonXsrf() },
                credentials: 'same-origin',
                body: JSON.stringify({ texte: contenu }),
            });
            const d = await r.json();
            setMessages((m) => [...m, { de: 'feggu', texte: d.reponse }]);
        } catch {
            setMessages((m) => [...m, { de: 'feggu', texte: `${t('hors_ligne')} SMS : ${feggu.numero_court}` }]);
        }
        setAttente(false);
        champ.current?.focus({ preventScroll: true });
    };

    useEffect(() => { envoyer(''); }, []);
    useEffect(() => { if (fil.current) fil.current.scrollTop = fil.current.scrollHeight; }, [messages, attente]);

    const dernier = [...messages].reverse().find((m) => m.de === 'feggu');
    const choix = dernier ? options(dernier.texte) : [];

    return (
        <div className={`flex min-h-0 flex-col ${compact ? 'h-full' : ''}`}>
            <div ref={fil} className={`flex flex-col gap-3 overflow-y-auto bg-white p-4 ${compact ? 'min-h-0 flex-1' : 'max-h-[34rem] min-h-[26rem] rounded-3xl ring-1 ring-trait sm:p-6'}`} aria-live="polite">
                {messages.map((m, i) => (
                    <p key={i} className={`max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-3 ${compact ? 'text-[0.95rem]' : ''} ${m.de === 'moi' ? 'self-end rounded-br-md bg-violet text-white' : 'self-start rounded-bl-md bg-lilas text-nuit'}`}>{m.texte}</p>
                ))}
                {attente && <p className="self-start rounded-2xl bg-lilas px-4 py-3 text-gris">{assistant ? t('chat_ecrit') : '•••'}</p>}
            </div>

            <div className={compact ? 'border-t border-trait bg-fond p-3' : 'mt-4'}>
                {choix.length > 0 && !attente && (
                    <div className="mb-3 flex flex-wrap gap-2">
                        {choix.map((c) => <button key={c.valeur} type="button" className="puce" onClick={() => envoyer(c.valeur)}><b className="text-violet">{c.valeur}</b> {c.libelle}</button>)}
                    </div>
                )}
                <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); if (texte.trim()) envoyer(texte.trim()); }}>
                    <input ref={champ} className="champ" value={texte} onChange={(e) => setTexte(e.target.value)} placeholder={t(assistant ? 'chat_ecrire_ia' : 'chat_ecrire')} aria-label={t('chat_ecrire')} autoComplete="off" />
                    <button className="btn-violet shrink-0" disabled={attente || !texte.trim()}>{t('chat_envoyer')}</button>
                </form>
            </div>
        </div>
    );
}
