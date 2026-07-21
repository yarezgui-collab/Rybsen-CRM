// Service worker — CRM Labo Ben Yedder
// Objectif : permettre l'ouverture de l'application et de la caisse même sans réseau
// (coupures internet fréquentes). Les ventes encaissées hors-ligne sont mises en file
// d'attente côté page (assets/offline.js) puis synchronisées au retour du réseau.
const CACHE = 'benyedder-v2';
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

  // RÉSEAU D'ABORD partout (pages, assets, API en lecture) : l'utilisateur en ligne voit
  // toujours la dernière version. Le cache ne sert QUE de secours en cas de coupure réseau
  // (l'application et la caisse restent alors utilisables). On met à jour le cache à chaque
  // réponse réussie pour garder une copie hors-ligne fraîche.
  e.respondWith(
    fetch(req).then((res) => {
      if (res && res.status === 200) {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
      }
      return res;
    }).catch(() => caches.match(req))
  );
});
