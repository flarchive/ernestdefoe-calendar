import app from 'flarum/forum/app';
import { forumPulse, type ForumPulse, type PulseLeader } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

const DAY_MS = 86400000;

function ymd(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}
function level(count: number, max: number): number {
  if (!count) return 0;
  if (max <= 1) return 4;
  const q = count / max;
  return q > 0.75 ? 4 : q > 0.5 ? 3 : q > 0.25 ? 2 : 1;
}

/** Compact recent-activity mini grid (last `weeks` weeks). */
function miniGrid(days: Record<string, number>, max: number, weeks: number) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  let cur = new Date(today.getTime() - (weeks * 7 - 1) * DAY_MS);
  cur = new Date(cur.getTime() - (cur.getDay() * DAY_MS)); // align to Sunday for the strip
  const cols: any[] = [];
  while (cur <= today) {
    const col: any[] = [];
    for (let i = 0; i < 7; i++) {
      if (cur > today) col.push(null);
      else { const k = ymd(cur); col.push({ lvl: level(days[k] || 0, max) }); }
      cur = new Date(cur.getTime() + DAY_MS);
    }
    cols.push(col);
  }
  return cols;
}

/**
 * "Forum Pulse" — the community heartbeat: a compact recent-activity heatmap plus
 * a most-active leaderboard. Self-contained POJO so it works on the stock index,
 * a Bespoke widget, or a Page Builder block. attrs: { title, weeks, count }.
 */
const PulseWidget = {
  oninit(this: any, vnode: any) {
    this.data = null as ForumPulse | null;
    const a = vnode.attrs || {};
    forumPulse({ days: 120, leaderDays: 30, limit: Math.max(3, Number(a.count) || 5) })
      .then((d) => { this.data = d; m.redraw(); })
      .catch(() => { this.data = { days: {}, total: 0, max: 0, leaders: [], leaderDays: 30 } as any; m.redraw(); });
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('pulse_title');
    const d: ForumPulse | null = this.data;
    const weeks = Math.max(6, Math.min(20, Number(a.weeks) || 14));

    return m('.CalPulse', [
      m('.CalPulse-head', [
        m('h4.CalPulse-title', title),
        d ? m('span.CalPulse-total', t('pulse_total', { count: d.total })) : null,
      ]),

      !d
        ? m('.CalPulse-loading', [m('span.CalPulse-skel'), m('span.CalPulse-skel')])
        : [
            m('.CalPulse-grid', miniGrid(d.days, d.max, weeks).map((col: any) =>
              m('.CalPulse-col', col.map((cell: any) =>
                cell ? m('span.CalHeat-cell', { 'data-lvl': cell.lvl }) : m('span.CalHeat-cell.is-future')
              ))
            )),

            d.leaders.length
              ? m('ol.CalPulse-board', d.leaders.map((l: PulseLeader, i: number) => leaderRow(l, i)))
              : m('p.CalPulse-empty', t('pulse_empty')),
          ],
    ]);
  },
};

function leaderRow(l: PulseLeader, i: number) {
  return m('li.CalPulse-leader', [
    m('span.CalPulse-rank', '#' + (i + 1)),
    m('a.CalPulse-user', { href: app.route('user', { username: l.username }), oncreate: m.route.Link }, [
      avatar(l),
      m('span.CalPulse-name', l.displayName || l.username),
    ]),
    m('span.CalPulse-count', l.count),
  ]);
}

function avatar(l: PulseLeader) {
  if (l.avatarUrl) {
    return m('span.Avatar.CalPulse-avatar', { style: { backgroundImage: `url("${l.avatarUrl}")` } });
  }
  const ch = (l.displayName || l.username || '?').charAt(0).toUpperCase();
  return m('span.Avatar.CalPulse-avatar.CalPulse-avatar--text', ch);
}

export default PulseWidget;
