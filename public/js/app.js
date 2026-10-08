'use strict';
/* Shared helpers: toast, preview/confirm modal, fetch wrapper, drawer, topbar filters. */

const E = sel => document.querySelector(sel);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const csrfToken = () => E('meta[name="csrf-token"]')?.content || '';
const PLANT = window.PLANT || { dayStart: '06:00', dayEnd: '18:00' };

/* ---------- Shift logic (mirrors App\Support\ShiftHelper) ---------- */
function deriveShift(time) {
  const t = String(time || '').slice(0, 5);
  return (t >= PLANT.dayStart && t < PLANT.dayEnd) ? 'Day' : 'Night';
}
function deriveOperatingDate(date, time) {
  if (String(time).slice(0, 5) < PLANT.dayStart) {
    const d = new Date(date + 'T12:00:00Z');
    d.setUTCDate(d.getUTCDate() - 1);
    return d.toISOString().slice(0, 10);
  }
  return date;
}
function fmtDate(d) {
  if (!d) return '';
  const [y, m, day] = d.split('-');
  return `${day} ${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][Number(m) - 1]} ${y}`;
}

/* ---------- Toast ---------- */
function toast(message) {
  const el = E('#toast');
  if (!el) return;
  el.textContent = message;
  el.classList.add('show');
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => el.classList.remove('show'), 3500);
}
function flashToast(message) { try { sessionStorage.setItem('smzl-flash', message); } catch (e) { /* ignore */ } }
function showFlash() {
  try {
    const m = sessionStorage.getItem('smzl-flash');
    if (m) { sessionStorage.removeItem('smzl-flash'); toast(m); }
  } catch (e) { /* ignore */ }
}

/* ---------- Modal (preview before saving, view, reject, edit) ---------- */
let modalHandler = null;
let returnFocus = null;

function openModal(title, meta, body, confirmText, onConfirm, backText) {
  E('#pv-title').textContent = title;
  E('#pv-meta').innerHTML = meta || '';
  E('#pv-meta').hidden = !meta;
  E('#pv-body').innerHTML = body;
  E('#pv-error').textContent = '';
  const confirm = E('#preview-confirm');
  confirm.hidden = !confirmText;
  confirm.textContent = confirmText || '';
  confirm.disabled = false;
  E('#preview-back').textContent = backText || 'Cancel';
  modalHandler = onConfirm || null;
  E('#overlay').classList.add('active');
  document.body.style.overflow = 'hidden';
  E('#preview-back').focus();
}
function closeModal() {
  E('#overlay').classList.remove('active');
  document.body.style.overflow = '';
  modalHandler = null;
  returnFocus?.focus();
}
function setModalError(message) { E('#pv-error').textContent = message; }

function initModal() {
  E('#preview-back')?.addEventListener('click', closeModal);
  E('#preview-confirm')?.addEventListener('click', () => { if (modalHandler) modalHandler(); });
  E('#overlay')?.addEventListener('click', e => { if (e.target.id === 'overlay') closeModal(); });
}

/* ---------- JSON requests with the CSRF token ---------- */
async function api(method, url, payload) {
  try {
    const res = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: payload === undefined ? undefined : JSON.stringify(payload),
      credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
    if (res.status === 419) data.message = 'Your session expired. Reload the page and sign in again.';
    if (res.status === 403 && !data.message) data.message = 'Your role is not allowed to do that.';
    return { ok: res.ok, status: res.status, data };
  } catch (e) {
    return { ok: false, status: 0, data: { message: 'Network problem. Check the connection and try again.' } };
  }
}

/* ---------- Mobile drawer ---------- */
function initDrawer() {
  const toggle = E('#mobile-menu'), more = E('#mobile-more'), sidebar = E('#sidebar'), back = E('#drawer-backdrop');
  if (!sidebar) return;
  const set = open => {
    sidebar.classList.toggle('mobile-open', open);
    back.classList.toggle('open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    if (open) sidebar.querySelector('a')?.focus();
  };
  toggle?.addEventListener('click', () => set(!sidebar.classList.contains('mobile-open')));
  more?.addEventListener('click', () => set(true));
  back?.addEventListener('click', () => set(false));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (E('#overlay')?.classList.contains('active')) closeModal(); else set(false);
    }
  });
}

/* ---------- Topbar filters: reload the page with ?date=&shift= ---------- */
function initFilters() {
  const d = E('#global-date'), s = E('#global-shift');
  if (!d || !s) return;
  const go = () => {
    const p = new URLSearchParams();
    if (d.value) p.set('date', d.value);
    if (s.value !== 'all') p.set('shift', s.value);
    window.location.search = p.toString();
  };
  d.addEventListener('change', go);
  s.addEventListener('change', go);
}

document.addEventListener('DOMContentLoaded', () => {
  initDrawer();
  initModal();
  initFilters();
  showFlash();
});
