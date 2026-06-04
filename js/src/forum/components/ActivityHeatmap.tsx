import app from 'flarum/forum/app';
import { userActivity, type UserActivity } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

const DAY_MS = 86400000;

/** Streak / volume achievement tiers, awarded off longest streak + total posts. */
const STREAK_BADGES = [
  { n: 3, icon: '🔥', key: 'badge_roll' },
  { n: 7, icon: '⚡', key: 'badge_week' },
  { n: 30, icon: '🚀', key: 'badge_month' },
  { n: 100, icon: '💯', key: 'badge_100' },
  { n: 365, icon: '👑', key: 'badge_year' },
];
const TOTAL_BADGES = [
  { n: 100, icon: '✍️', key: 'badge_100posts' },
  { n: 500, icon: '📚', key: 'badge_500posts' },
  { n: 1000, icon: '🏆', key: 'badge_1kposts' },
];

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

/** Build GitHub-style week columns for the last ~year, aligned to the first weekday. */
function buildWeeks(days: Record<string, number>, weekStart: number, max: number) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  let cur = new Date(today.getTime() - 364 * DAY_MS);
  cur = new Date(cur.getTime() - ((cur.getDay() - weekStart + 7) % 7) * DAY_MS); // back to week start

  const weeks: any[] = [];
  const months: any[] = [];
  let lastMonth = -1;
  while (cur <= today) {
    const week: any[] = [];
    let weekMonth = -1;
    for (let i = 0; i < 7; i++) {
      if (cur > today) {
        week.push(null);
      } else {
        const key = ymd(cur);
        const count = days[key] || 0;
        if (weekMonth < 0) weekMonth = cur.getMonth();
        week.push({ date: key, count, lvl: level(count, max) });
      }
      cur = new Date(cur.getTime() + DAY_MS);
    }
    // Month label appears above the first week of each new month.
    if (weekMonth >= 0 && weekMonth !== lastMonth) {
      months.push({ idx: weeks.length, label: new Date(2000, weekMonth, 1).toLocaleDateString(undefined, { month: 'short' }) });
      lastMonth = weekMonth;
    }
    weeks.push(week);
  }
  return { weeks, months };
}

function earnedBadges(d: UserActivity) {
  const out: any[] = [];
  STREAK_BADGES.forEach((b) => { if (d.streak.longest >= b.n) out.push({ icon: b.icon, label: t(b.key) }); });
  TOTAL_BADGES.forEach((b) => { if (d.total >= b.n) out.push({ icon: b.icon, label: t(b.key) }); });
  return out;
}

/**
 * The marquee engagement widget: a year-long contribution heatmap with streaks
 * and earned badges. Self-fetches by `attrs.userId`. POJO component so it drops
 * into a profile, a modal, or anywhere. Renders nothing if the data errors, so
 * it can never break its host page.
 */
const ActivityHeatmap = {
  oninit(this: any, vnode: any) {
    this.data = null as UserActivity | null;
    this.error = false;
    const uid = Number(vnode.attrs.userId);
    if (uid) {
      userActivity(uid)
        .then((d) => { this.data = d; m.redraw(); })
        .catch(() => { this.error = true; m.redraw(); });
    } else {
      this.error = true;
    }
  },

  view(this: any) {
    if (this.error) return null;
    const d: UserActivity | null = this.data;

    if (!d) {
      return m('.CalHeat.CalHeat--loading', [m('.CalHeat-bar'), m('.CalHeat-bar')]);
    }

    const weekStart = (app.forum.attribute('ernestdefoe-calendar.weekStartsOn') as number) || 0;
    const { weeks, months } = buildWeeks(d.days, weekStart, d.max);
    const badges = earnedBadges(d);

    return m('.CalHeat', [
      m('.CalHeat-head', [
        stat(d.total, t('heat_contributions')),
        stat([d.streak.current, m('span.CalHeat-fire', ' 🔥')], t('heat_current_streak')),
        stat(d.streak.longest, t('heat_longest_streak')),
        stat(d.activeDays, t('heat_active_days')),
      ]),

      m('.CalHeat-scroll', m('.CalHeat-cal', [
        m('.CalHeat-months', months.map((mo: any) => m('span.CalHeat-month', { style: { '--col': mo.idx } }, mo.label))),
        m('.CalHeat-grid', weeks.map((week: any) =>
          m('.CalHeat-week', week.map((cell: any) =>
            cell
              ? m('span.CalHeat-cell', { 'data-lvl': cell.lvl, title: t('heat_cell', { count: cell.count, date: cell.date }) })
              : m('span.CalHeat-cell.is-future')
          ))
        )),
      ])),

      m('.CalHeat-foot', [
        badges.length
          ? m('.CalHeat-badges', badges.map((b: any) => m('span.CalHeat-badge', { title: b.label }, [b.icon, ' ', m('span.CalHeat-badgeLbl', b.label)])))
          : m('span'),
        m('.CalHeat-legend', [
          m('span.CalHeat-legendLbl', t('heat_less')),
          [0, 1, 2, 3, 4].map((l) => m('span.CalHeat-cell', { 'data-lvl': l })),
          m('span.CalHeat-legendLbl', t('heat_more')),
        ]),
      ]),
    ]);
  },
};

function stat(value: any, label: string) {
  return m('.CalHeat-stat', [m('span.CalHeat-statNum', value), m('span.CalHeat-statLbl', label)]);
}

export default ActivityHeatmap;
