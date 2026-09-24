import app from 'flarum/forum/app';

/**
 * Live countdowns. The formatter renders `[countdown=…]` as an inert
 * `<span class="CalCountdown" data-deadline="…">label</span>` placeholder (server
 * output is cached, so the clock can't run there). One global 1s ticker scans the
 * DOM and turns every placeholder into a ticking timer — which also covers
 * countdowns rendered anywhere else (e.g. the event detail modal). New posts /
 * modals are picked up on the next tick, so there are no per-component hooks.
 */

let started = false;

/** Plain-string translation (trans() can return an array; we build innerHTML). */
function tr(key: string): string {
  const r = app.translator.trans('ernestdefoe-calendar.forum.' + key) as any;
  return Array.isArray(r) ? r.join('') : String(r);
}

export function startCountdowns(): void {
  if (started || typeof document === 'undefined') return;
  started = true;
  const tick = () => {
    const nodes = document.querySelectorAll<HTMLElement>('.CalCountdown[data-deadline]');
    for (let i = 0; i < nodes.length; i++) render(nodes[i]);
  };
  tick();
  window.setInterval(tick, 1000);
}

function parseDeadline(raw: string): number | null {
  let ts = Date.parse(raw);
  if (isNaN(ts)) ts = Date.parse(raw.trim().replace(' ', 'T')); // tolerate "Y-M-D H:M"
  return isNaN(ts) ? null : ts;
}

function render(el: HTMLElement): void {
  const deadline = parseDeadline(el.getAttribute('data-deadline') || '');
  if (deadline === null) { el.setAttribute('data-state', 'invalid'); return; }

  if (el.getAttribute('data-init') !== '1') build(el);

  const diff = deadline - Date.now();
  if (diff <= 0) { setDone(el); return; }

  el.setAttribute('data-state', 'live');
  const secs = Math.floor(diff / 1000);
  setNum(el, 'd', Math.floor(secs / 86400));
  setNum(el, 'h', Math.floor((secs % 86400) / 3600));
  setNum(el, 'm', Math.floor((secs % 3600) / 60));
  setNum(el, 's', secs % 60);
}

function build(el: HTMLElement): void {
  const label = (el.textContent || '').trim();
  el.setAttribute('data-init', '1');
  if (label) el.setAttribute('data-label', label);

  const unit = (u: string, lbl: string) =>
    `<span class="CalCountdown-unit"><b class="CalCountdown-num" data-u="${u}">0</b><i class="CalCountdown-lbl">${escapeHtml(lbl)}</i></span>`;

  el.innerHTML =
    (label ? `<span class="CalCountdown-caption">${escapeHtml(label)}</span>` : '') +
    '<span class="CalCountdown-clock">' +
    unit('d', tr('cd_days')) + unit('h', tr('cd_hours')) +
    unit('m', tr('cd_mins')) + unit('s', tr('cd_secs')) +
    '</span>';
}

function setNum(el: HTMLElement, u: string, v: number): void {
  const n = el.querySelector('.CalCountdown-num[data-u="' + u + '"]');
  if (n) n.textContent = u === 'd' ? String(v) : String(v).padStart(2, '0');
}

function setDone(el: HTMLElement): void {
  if (el.getAttribute('data-state') === 'done') return;
  el.setAttribute('data-state', 'done');
  const label = el.getAttribute('data-label') || '';
  const msg = label ? escapeHtml(label) + ' · ' + tr('cd_here') : tr('cd_live');
  el.innerHTML = '<span class="CalCountdown-done">🎉 ' + msg + '</span>';
}

function escapeHtml(s: string): string {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
