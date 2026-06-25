// RYBSEN CRM — app.js
// Fonctions globales partagées

const RYBSEN = {

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
    } catch(e) {
      console.error('API error:', e);
      return { error: e.message };
    }
  },

  // Format currency
  formatCurrency(amount, devise = 'EUR') {
    if (!amount) return '—';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: devise, maximumFractionDigits: 0 }).format(amount);
  },

  // Format date
  formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  },

  // Days until deadline
  daysUntil(dateStr) {
    if (!dateStr) return null;
    const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000);
    return diff;
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

  // Render horizontal stage pipeline (Creatio-style)
  renderPipeline(stages, currentStage, isLost = false) {
    const idx = stages.indexOf(currentStage);
    return '<div class="stage-pipeline">' + stages.map((s, i) => {
      let cls = '';
      if (isLost && i === idx) cls = 'lost';
      else if (i < idx) cls = 'done';
      else if (i === idx) cls = 'current';
      return `<div class="stage-step ${cls}">${s}</div>`;
    }).join('') + '</div>';
  },

  init() {
    this.initModals();

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

document.addEventListener('DOMContentLoaded', () => RYBSEN.init());
