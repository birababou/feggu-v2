import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { envoyerFileHorsLigne } from './lib/horsLigne';

createInertiaApp({
    title: (titre) => (titre ? `${titre} · FEGGU` : 'FEGGU'),
    resolve: (nom) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${nom}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#f0641e' },
});

// Signalements saisis sans réseau : envoyés dès que la connexion revient.
window.addEventListener('online', envoyerFileHorsLigne);
envoyerFileHorsLigne();

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
