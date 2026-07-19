// BEN YEDDER CRM — app.js
// Fonctions globales partagées

const LABO = {

  // Toast notification
  toast(msg, type = 'success', duration = 3000) {
    let t = document.getElementById('toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => { t.className = ''; }, duration);
  },

  // Open/close modal
  openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
  },

  // Close modal on overlay click
  initModals() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => {
        if (e.target === overlay) this.closeModal(overlay.id);
      });
    });
  },

  // Generic API call
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

  // Format currency (Dinar Tunisien par défaut)
  formatCurrency(amount, devise = 'TND') {
    if (amount === null || amount === undefined || amount === '') return '—';
    return new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(amount) + ' ' + devise;
  },

  // Format date
  formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  },

  // HTML escape — à utiliser dans tous les template literals
  escape(str) {
    if (str === null || str === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  },

  // Confirm delete
  confirmDelete(msg = 'Supprimer cet élément ?') {
    return confirm(msg);
  },

  // Live table search
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

  // Tabs (ex: catalogue produits / matières / recettes)
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

    // Sidebar overlay close on mobile
    document.addEventListener('click', e => {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) && !e.target.closest('.menu-toggle')) {
        sidebar.classList.remove('open');
      }
    });
  }
};

document.addEventListener('DOMContentLoaded', () => LABO.init());
