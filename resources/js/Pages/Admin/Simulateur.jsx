import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import LayoutAdmin from '../../Layouts/LayoutAdmin';

function jetonXsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

const CANAUX = {
    sms: { nom: 'SMS', aide: 'Comme un SMS envoyé au numéro court. Essayez un message libre : « MBOUR ma voisine est frappée », ou « SOS ».' },
    ussd: { nom: 'USSD', aide: 'Comme un menu USSD sur un téléphone simple : on répond par des chiffres. Rien ne reste dans les messages du téléphone.' },
    whatsapp: { nom: 'WhatsApp', aide: 'Comme le chatbot WhatsApp. En production, les notes vocales sont transcrites par l\'IA locale.' },
};

export default function Simulateur({ numero_court, code_ussd }) {
    const [canal, setCanal] = useState('sms');
    const [fil, setFil] = useState([]);
    const [texte, setTexte] = useState('');
    const [session, setSession] = useState(null);
    const [ussdFini, setUssdFini] = useState(false);
    const [signalement, setSignalement] = useState(null);
    const numero = useRef(`77${Math.floor(1000000 + Math.random() * 8999999)}`);
    const bas = useRef(null);

    const envoyer = async (contenu, sess = session) => {
        if (contenu !== '') setFil((f) => [...f, { de: 'moi', texte: contenu }]);
        setTexte('');
        const r = await fetch('/espace/simulateur', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': jetonXsrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ canal, numero: numero.current, texte: contenu, session: sess }),
        });
        const d = await r.json();
        setFil((f) => [...f, { de: 'feggu', texte: d.reponse }]);
        if (d.signalement_id) setSignalement({ id: d.signalement_id, reference: d.reference });
        if (canal === 'ussd' && d.fin) setUssdFini(true);
    };

    const recommencer = (c = canal) => {
        setCanal(c);
        setFil([]);
        setSignalement(null);
        setUssdFini(false);
        numero.current = `77${Math.floor(1000000 + Math.random() * 8999999)}`;
        if (c === 'ussd') {
            const s = `sim-${Date.now()}`;
            setSession(s);
            setFil([{ de: 'moi', texte: `Composer ${code_ussd || 'le code USSD'}` }]);
            // L'ouverture d'une session USSD n'envoie aucun texte.
            setTimeout(() => fetch('/espace/simulateur', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': jetonXsrf() },
                credentials: 'same-origin',
                body: JSON.stringify({ canal: 'ussd', numero: numero.current, texte: '', session: s }),
            }).then((r) => r.json()).then((d) => setFil((f) => [...f, { de: 'feggu', texte: d.reponse }])), 0);
        } else {
            setSession(null);
        }
    };

    useEffect(() => { bas.current?.scrollIntoView({ block: 'end' }); }, [fil]);

    const dernier = [...fil].reverse().find((m) => m.de === 'feggu');
    const bulleMoi = canal === 'whatsapp' ? 'bg-[#d9fdd3] text-encre' : 'bg-violet text-white';

    return (
        <LayoutAdmin titre="Simulateur des canaux">
            <p className="max-w-3xl text-gris">Testez les canaux sans téléphone. Les messages passent par le même moteur que l'agrégateur SMS, l'USSD et WhatsApp. Les signalements créés ici sont marqués « démo ».</p>

            <div className="mt-6 grid gap-8 lg:grid-cols-[22rem_1fr]">
                <div>
                    <div className="mb-4 inline-flex rounded-full bg-white p-1 ring-1 ring-trait" role="tablist">
                        {Object.entries(CANAUX).map(([c, { nom }]) => (
                            <button key={c} type="button" role="tab" aria-selected={canal === c} onClick={() => recommencer(c)}
                                className={`rounded-full px-4 py-2 font-bold ${canal === c ? 'bg-nuit text-white' : 'text-gris hover:text-nuit'}`}>{nom}</button>
                        ))}
                    </div>

                    <div className="rounded-[2.5rem] bg-nuit p-3 shadow-xl">
                        <div className={`flex h-[34rem] flex-col overflow-hidden rounded-[2rem] ${canal === 'ussd' ? 'bg-[#1d2a22]' : canal === 'whatsapp' ? 'bg-[#efeae2]' : 'bg-white'}`}>
                            <div className={`px-5 py-3 text-sm font-bold ${canal === 'whatsapp' ? 'bg-[#075e54] text-white' : canal === 'ussd' ? 'text-[#9fe3b4]' : 'border-b border-trait text-nuit'}`}>
                                {canal === 'sms' && `Messages · ${numero_court}`}
                                {canal === 'whatsapp' && 'FEGGU'}
                                {canal === 'ussd' && (code_ussd || 'USSD')}
                            </div>

                            {canal === 'ussd' ? (
                                <div className="flex flex-1 flex-col justify-center p-5">
                                    <div className="rounded-lg bg-[#2b3b31] p-4 font-mono text-sm text-[#d6f5df]">
                                        <p className="whitespace-pre-line">{dernier?.texte || '…'}</p>
                                        {!ussdFini && dernier && (
                                            <form className="mt-3 flex gap-2" onSubmit={(e) => { e.preventDefault(); envoyer(texte); }}>
                                                <input className="w-full rounded bg-[#1d2a22] px-2 py-1 text-[#d6f5df] outline-none ring-1 ring-[#4d6b57]" value={texte} onChange={(e) => setTexte(e.target.value)} autoFocus aria-label="Réponse USSD" />
                                                <button className="rounded bg-[#9fe3b4] px-3 font-bold text-[#1d2a22]">OK</button>
                                            </form>
                                        )}
                                    </div>
                                    {ussdFini && <button type="button" className="mt-4 text-sm font-bold text-[#9fe3b4]" onClick={() => recommencer('ussd')}>Nouvelle session</button>}
                                </div>
                            ) : (
                                <>
                                    <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4 text-[0.95rem]">
                                        {fil.map((m, i) => (
                                            <p key={i} className={`max-w-[85%] whitespace-pre-line rounded-2xl px-3.5 py-2 ${m.de === 'moi' ? `self-end ${bulleMoi}` : 'self-start bg-white text-encre ring-1 ring-trait'}`}>{m.texte}</p>
                                        ))}
                                        <div ref={bas} />
                                    </div>
                                    <form className="flex gap-2 border-t border-trait bg-white p-3" onSubmit={(e) => { e.preventDefault(); if (texte.trim()) envoyer(texte.trim()); }}>
                                        <input className="w-full rounded-full bg-fond px-4 py-2 outline-none ring-1 ring-trait focus:ring-violet" value={texte} onChange={(e) => setTexte(e.target.value)} placeholder="Message" aria-label="Message" />
                                        <button className="rounded-full bg-violet px-4 font-bold text-white">Envoyer</button>
                                    </form>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="space-y-5">
                    <section className="carte p-6">
                        <h2 className="text-lg font-bold">{CANAUX[canal].nom}</h2>
                        <p className="mt-1 text-gris">{CANAUX[canal].aide}</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {canal !== 'ussd' && ['MBOUR ma voisine est frappée par son mari', 'SOS', 'MENU'].map((ex) => (
                                <button key={ex} type="button" className="puce text-sm" onClick={() => envoyer(ex)}>{ex}</button>
                            ))}
                            <button type="button" className="btn-discret py-2 text-sm" onClick={() => recommencer()}>Recommencer</button>
                        </div>
                    </section>

                    {signalement && (
                        <section className="carte border-sur/40 p-6">
                            <p className="font-bold text-sur">Signalement créé</p>
                            <p className="mt-1 font-display text-2xl font-extrabold text-nuit">{signalement.reference}</p>
                            <Link href={`/espace/signalements/${signalement.id}`} className="btn-ligne mt-3 py-2">Voir le dossier, le tri et l'orientation</Link>
                        </section>
                    )}

                    <section className="carte p-6 text-sm text-gris">
                        <h2 className="text-base font-bold text-nuit">Ce que montre le simulateur</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            <li>Un message libre crée directement un signalement, avec le lieu détecté dans le texte (format historique du 21211).</li>
                            <li>« SOS » à n'importe quelle étape crée une alerte critique et donne les numéros d'urgence.</li>
                            <li>Le parcours guidé fonctionne en français et en wolof, avec des chiffres seulement.</li>
                            <li>Si la personne répond « danger », l'alerte est créée tout de suite, avant la fin des questions.</li>
                        </ul>
                    </section>
                </div>
            </div>
        </LayoutAdmin>
    );
}
