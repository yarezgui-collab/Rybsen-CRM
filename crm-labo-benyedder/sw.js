// Service worker — CRM Labo Ben Yedder
// Objectif : permettre l'ouverture de l'application et de la caisse même sans réseau
// (coupures internet fréquentes). Les ventes encaissées hors-ligne sont mises en file
// d'attente côté page (assets/offline.js) puis synchronisées au retour du réseau.
const CACHE = 'benyedder-v1';
const SHELL = [
  '/assets/style.css',
  '/assets/app.js',
  '/assets/offline.js',
  '/assets/icon.svg',
  '/manifest.webmanifest',
  '/index.php',
  '/modules/caisse.php',
  '/modules/mon_stock.php',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // les POST (ventes, API) ne sont jamais mis en cache

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const isApi = url.pathname.endsWith('/api/api.php');
  if (isApi) {
    // API en lecture : réseau d'abord, cache en secours (permet de charger le catalogue hors-ligne)
    e.respondWith(
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Pages & assets : cache d'abord, réseau en secours, et on rafraîchit le cache en tâche de fond
  e.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res && res.status === 200) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});
