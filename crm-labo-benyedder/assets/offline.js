// offline.js — file d'attente des ventes de caisse + synchronisation automatique.
// Conçu pour les coupures internet : toute vente est d'abord stockée localement, puis
// envoyée au serveur. Chaque vente porte une référence unique (client_ref) qui rend la
// synchronisation idempotente côté serveur — jamais de double encaissement.
(function () {
  const KEY = 'benyedder_ventes_queue';
  const listeners = [];

  function uuid() {
    if (crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = Math.random() * 16 | 0; const v = c === 'x' ? r : (r & 0x3 | 0x8); return v.toString(16);
    });
  }
  function readQueue() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function writeQueue(q) { localStorage.setItem(KEY, JSON.stringify(q)); notify(); }
  function notify() { const n = readQueue().length; listeners.forEach((f) => { try { f(n, navigator.onLine); } catch (e) {} }); }

  // Ajoute une vente à la file puis tente une synchro immédiate. Renvoie la référence locale.
  function enqueueSale(sale) {
    const q = readQueue();
    const item = Object.assign({ client_ref: uuid(), _ts: Date.now() }, sale);
    q.push(item);
    writeQueue(q);
    flush();
    return item.client_ref;
  }

  let flushing = false;
  async function flush() {
    if (flushing || !navigator.onLine) { notify(); return; }
    flushing = true;
    try {
      let q = readQueue();
      while (q.length) {
        const item = q[0];
        let res;
        try {
          var payload = { action: 'caisse_vente_save', client_ref: item.client_ref };
          if (item.lignes) { payload.lignes = item.lignes; payload.client_id = item.client_id || null; }
          else { payload.produit_id = item.produit_id; payload.quantite = item.quantite; } // compat ancienne file
          res = await fetch('/api/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
          });
        } catch (netErr) { break; } // réseau retombé : on garde la file pour plus tard
        let data = null; try { data = await res.json(); } catch (e) {}
        if (res.ok && data && data.ok) {
          q = readQueue(); q.shift(); writeQueue(q); // succès (ou déjà enregistré) → on retire
        } else if (res.status === 401 || res.status === 403) {
          break; // session expirée : on ne perd pas la vente, on réessaiera après reconnexion
        } else {
          // erreur métier non récupérable (ex: produit supprimé) : on retire pour ne pas bloquer la file
          q = readQueue(); q.shift(); writeQueue(q);
        }
        q = readQueue();
      }
    } finally { flushing = false; notify(); }
  }

  function onChange(fn) { listeners.push(fn); fn(readQueue().length, navigator.onLine); }
  function pendingCount() { return readQueue().length; }

  window.addEventListener('online', flush);
  window.addEventListener('offline', notify);
  setInterval(function () { if (navigator.onLine && pendingCount()) flush(); }, 15000);

  // Enregistre le service worker (ouverture de l'app même hors-ligne)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }

  window.OfflineCaisse = { enqueueSale, flush, onChange, pendingCount };
})();
