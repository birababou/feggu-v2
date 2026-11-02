// Service worker FEGGU : permet d'ouvrir l'accueil et le formulaire de signalement sans réseau.
// Les signalements faits hors ligne sont gardés par l'application puis envoyés au retour du réseau.
const CACHE = 'feggu-v2';
const PAGES = ['/', '/signaler'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PAGES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((cles) => Promise.all(cles.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;

    // Fichiers de l'application (JS, CSS) : cache d'abord.
    if (url.pathname.startsWith('/build/')) {
        e.respondWith(caches.match(e.request).then((r) => r || fetch(e.request).then((rep) => {
            const copie = rep.clone();
            caches.open(CACHE).then((c) => c.put(e.request, copie));
            return rep;
        })));
        return;
    }

    // Seules les pages publiques sans donnée personnelle sont mises en cache.
    if (e.request.mode === 'navigate' && PAGES.includes(url.pathname)) {
        e.respondWith(fetch(e.request).then((rep) => {
            const copie = rep.clone();
            caches.open(CACHE).then((c) => c.put(e.request, copie));
            return rep;
        }).catch(() => caches.match(e.request)));
    }
});
