/* ClinicCare - Main JavaScript */

// ── Sidebar Mobile Toggle
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const overlay = document.getElementById('sidebarOverlay');

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    sidebar?.classList.toggle('mobile-open');
    overlay?.classList.toggle('show');
  });
}
if (overlay) {
  overlay.addEventListener('click', () => {
    sidebar?.classList.remove('mobile-open');
    overlay.classList.remove('show');
  });
}

// ── Notification Dropdown
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
if (notifBtn && notifDropdown) {
  notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDropdown.classList.toggle('show');
  });
  document.addEventListener('click', () => {
    notifDropdown.classList.remove('show');
  });
}

// ── Mark notifications read
document.querySelectorAll('.notif-item').forEach(item => {
  item.addEventListener('click', function () {
    const id = this.dataset.id;
    if (id) {
      fetch(`/cliniccares/api/notifications.php?action=mark_read&id=${id}`);
      this.classList.remove('unread');
    }
    const link = this.dataset.link;
    if (link) window.location.href = link;
  });
});

// ── Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const target = this.dataset.tab;
    const tabGroup = this.closest('.tab-group');
    if (!tabGroup) return;

    // Deactivate all buttons in this group
    tabGroup.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

    // Panels may be children of tabGroup OR siblings in the parent element
    let panels = tabGroup.querySelectorAll('.tab-panel');
    if (!panels.length) {
      const parent = tabGroup.parentElement;
      panels = parent ? parent.querySelectorAll('.tab-panel') : [];
    }
    panels.forEach(p => p.classList.remove('active'));

    const activePanel = document.getElementById(target);
    if (activePanel) activePanel.classList.add('active');
  });
});

// ── Modal helpers
function openModal(id) {
  document.getElementById(id)?.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('show');
  document.body.style.overflow = '';
}
document.querySelectorAll('[data-modal]').forEach(btn => {
  btn.addEventListener('click', () => openModal(btn.dataset.modal));
});
document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay')?.classList.remove('show');
    document.body.style.overflow = '';
  });
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function (e) {
    if (e.target === this) {
      this.classList.remove('show');
      document.body.style.overflow = '';
    }
  });
});

// ── Toast notifications
function showToast(message, type = 'info', duration = 4000) {
  const colors = {
    success: '#16a34a', danger: '#dc2626',
    warning: '#d97706', info: '#1d4ed8'
  };
  const icons = { success: '✓', danger: '✕', warning: '⚠', info: 'ℹ' };
  const toast = document.createElement('div');
  toast.style.cssText = `
    position:fixed; bottom:24px; right:24px; z-index:9999;
    background:${colors[type]}; color:#fff;
    padding:14px 20px; border-radius:10px;
    font-size:14px; font-weight:500;
    box-shadow:0 8px 24px rgba(0,0,0,0.15);
    display:flex; align-items:center; gap:10px;
    max-width:360px; word-wrap:break-word;
    transform:translateY(20px); opacity:0;
    transition:all 0.3s ease;
  `;
  toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
  document.body.appendChild(toast);
  requestAnimationFrame(() => {
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
  });
  setTimeout(() => {
    toast.style.transform = 'translateY(20px)';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ── Form validation
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return true;
  let valid = true;
  form.querySelectorAll('[required]').forEach(field => {
    if (!field.value.trim()) {
      field.classList.add('is-invalid');
      valid = false;
    } else {
      field.classList.remove('is-invalid');
    }
  });
  // Email validation
  form.querySelectorAll('input[type=email]').forEach(field => {
    if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
      field.classList.add('is-invalid');
      valid = false;
    }
  });
  // Password match
  const pw = form.querySelector('[name=password]');
  const pw2 = form.querySelector('[name=confirm_password]');
  if (pw && pw2 && pw.value !== pw2.value) {
    pw2.classList.add('is-invalid');
    valid = false;
  }
  return valid;
}

// Remove invalid on input
document.addEventListener('input', (e) => {
  if (e.target.classList.contains('is-invalid')) {
    if (e.target.value.trim()) e.target.classList.remove('is-invalid');
  }
});

// ── Confirm dialog
function confirmAction(message, callback) {
  if (confirm(message)) callback();
}

// ── AJAX delete with confirmation
document.querySelectorAll('[data-delete]').forEach(btn => {
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    const url = this.dataset.delete;
    const label = this.dataset.label || 'this item';
    confirmAction(`Are you sure you want to delete ${label}? This cannot be undone.`, () => {
      fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showToast('Deleted successfully', 'success');
            this.closest('tr')?.remove() || location.reload();
          } else {
            showToast(data.error || 'Delete failed', 'danger');
          }
        })
        .catch(() => showToast('Network error', 'danger'));
    });
  });
});

// ── Live search
const searchInput = document.getElementById('liveSearch');
if (searchInput) {
  let debounce;
  searchInput.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      const q = this.value.toLowerCase();
      document.querySelectorAll('[data-searchable]').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
      });
    }, 200);
  });
}

// ── Date range defaults
const today = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type=date][data-today]').forEach(el => {
  el.setAttribute('min', today);
  if (!el.value) el.value = today;
});

// ── Print receipt/prescription
function printSection(id) {
  const content = document.getElementById(id)?.innerHTML;
  if (!content) return;
  const win = window.open('', '_blank');
  win.document.write(`
    <html><head><title>ClinicCare Print</title>
    <link rel="stylesheet" href="/cliniccares/assets/css/main.css">
    <style>body{padding:40px;}.no-print{display:none;}</style>
    </head><body>${content}</body></html>
  `);
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); win.close(); }, 500);
}

// ── Chart.js default config
if (typeof Chart !== 'undefined') {
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#64748b';
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.padding = 16;
  Chart.defaults.elements.line.tension = 0.4;
}

// ── Auto-dismiss alerts
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
  setTimeout(() => {
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
  }, parseInt(alert.dataset.autoDismiss) || 5000);
});

// ── Show page loading indicator
document.querySelectorAll('a:not([target=_blank]):not([data-no-loading])').forEach(link => {
  link.addEventListener('click', function () {
    if (this.href && !this.href.startsWith('#') && !this.href.startsWith('javascript')) {
      // Could show a progress bar here
    }
  });
});

console.log('%c🏥 ClinicCare', 'font-size:18px;font-weight:bold;color:#1d4ed8;');
console.log('%cOnline Health Records & Appointment System', 'color:#64748b;');