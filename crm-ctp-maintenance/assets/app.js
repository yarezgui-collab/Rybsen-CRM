// CTP MAINTENANCE — app.js
// Fonctions globales partagées (namespace CTP)

const CTP = {

  toast(msg, type = 'success', duration = 3000) {
    let t = document.getElementById('toast');
    if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.appendChild(t); }
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => { t.className = ''; }, duration);
  },

  openModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; } },
  closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('open'); document.body.style.overflow = ''; } },

  initModals() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => { if (e.target === overlay) this.closeModal(overlay.id); });
    });
  },

  // Appel API générique
  async api(action, data = {}) {
    try {
      const res = await fetch('/api/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ action, ...data })
      });
      return await res.json();
    } catch (e) {
      console.error('API error:', e);
      return { error: e.message };
    }
  },

  // Montant (Dinar Tunisien par défaut, 3 décimales — cohérent DECIMAL(10,3))
  formatCurrency(amount, devise = 'TND') {
    if (amount === null || amount === undefined || amount === '') return '—';
    return new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(amount) + ' ' + devise;
  },

  formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  },
  formatDateTime(d) {
    if (!d) return '—';
    return new Date(d.replace(' ', 'T')).toLocaleString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  },
  formatNumber(n) {
    if (n === null || n === undefined || n === '') return '—';
    return new Intl.NumberFormat('fr-FR').format(n);
  },

  // Échappement HTML — à utiliser dans tous les template literals
  escape(str) {
    if (str === null || str === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  },

  confirmDelete(msg = 'Supprimer cet élément ?') { return confirm(msg); },

  initSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  },

  initTabs(containerSelector = '.tabs') {
    document.querySelectorAll(containerSelector).forEach(tabs => {
      tabs.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const target = btn.dataset.tab;
          tabs.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b === btn));
          document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === target));
        });
      });
    });
  },

  init() {
    this.initModals();
    this.initTabs();
    document.addEventListener('click', e => {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) && !e.target.closest('.menu-toggle')) {
        sidebar.classList.remove('open');
      }
    });
  }
};

document.addEventListener('DOMContentLoaded', () => CTP.init());
