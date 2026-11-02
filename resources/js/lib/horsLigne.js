// File d'attente des signalements faits sans réseau.
// Les données restent dans ce téléphone le moins longtemps possible : elles sont effacées dès l'envoi réussi.

const CLE = 'feggu-file';

function lire() {
    try {
        return JSON.parse(localStorage.getItem(CLE) || '[]');
    } catch {
        return [];
    }
}

function ecrire(file) {
    try {
        if (file.length) localStorage.setItem(CLE, JSON.stringify(file));
        else localStorage.removeItem(CLE);
    } catch {
        /* stockage indisponible : rien à faire */
    }
}

export function mettreEnFile(donnees) {
    ecrire([...lire(), { ...donnees, hors_ligne: true }]);
}

export function nombreEnAttente() {
    return lire().length;
}

function jetonXsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

let enCours = false;

export async function envoyerFileHorsLigne() {
    if (enCours || !navigator.onLine) return;
    const file = lire();
    if (!file.length) return;
    enCours = true;
    const restants = [];
    for (const donnees of file) {
        try {
            const r = await fetch('/signaler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': jetonXsrf(), 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(donnees),
            });
            // 422 : données refusées (inutile de réessayer). Autre erreur (419, 500, réseau) : on réessaiera.
            if (!r.ok && r.status !== 422) restants.push(donnees);
        } catch {
            restants.push(donnees);
        }
    }
    ecrire(restants);
    enCours = false;
}

// Efface ce qui est en cours de saisie. La file hors ligne est conservée : un signalement non envoyé ne doit pas être perdu.
export function viderTraces() {
    try {
        sessionStorage.clear();
    } catch {
        /* rien */
    }
}
