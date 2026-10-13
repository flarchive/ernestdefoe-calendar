import app from 'flarum/forum/app';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { listEvents, type CalEvent } from '../../common/api';
import { load as loadShared, peek } from '../../common/widgetData';
import { shortTime, loc, sameDay, dateRange, eventDate, endsAt } from '../../common/dates';

declare const m: any;
const t = (k: string, p?: any) => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

/**
 * Self-contained "Upcoming events" list. A plain Mithril component so it can be
 * dropped anywhere — the stock-theme index mount, a Bespoke widget, or a Page
 * Builder block — with no host-specific dependencies. attrs: { count, title,
 * category, showCover }.
 */
const UpcomingEvents = {
  oninit(this: any, vnode: any) {
    this.events = null as CalEvent[] | null;
    this.load(vnode.attrs);
  },

  load(this: any, attrs: any) {
    const category = attrs.category || '';
    const pick = (all: CalEvent[]) => {
      const now = Date.now();
      return all.filter((e: CalEvent) => endsAt(e) >= now).slice(0, Math.max(1, Number(attrs.count) || 5));
    };
    const fetch = () => {
      // A day back, so an all-day event on its last day (stored as that
      // day's UTC midnight) is still in the window; pick() trims the rest.
      const from = new Date(Date.now() - 86400000);
      const to = new Date();
      to.setFullYear(to.getFullYear() + 1);
      return listEvents(from, to, category || undefined).then((res) => res.data || []);
    };

    const key = 'events:upcoming:' + category;
    const fresh = peek<CalEvent[]>(key);
    if (fresh !== undefined) {
      this.events = pick(fresh);
      return;
    }
    loadShared<CalEvent[]>(key, fetch, []).then((all) => {
      this.events = pick(all);
      m.redraw();
    });
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('widget_title');
    const events: CalEvent[] | null = this.events;

    /*
     * Events already under way get their own "Happening now" group. Listed
     * under "Upcoming" a festival that began three weeks ago read as a
     * mistake, and with only its start date on the tile nobody could tell
     * when it ends. The second heading only appears when there is a first.
     */
    const now = Date.now();
    const live = (events || []).filter((e) => eventDate(e, e.start).getTime() <= now);
    const next = (events || []).filter((e) => eventDate(e, e.start).getTime() > now);

    return m('.CalendarWidget', [
      m('.CalendarWidget-head', [
        m('h4.CalendarWidget-title', title),
        m('a.CalendarWidget-all', { href: app.route('calendar'), oncreate: m.route.Link }, t('nav')),
      ]),
      events === null
        ? m('.CalendarWidget-loading', m(LoadingIndicator))
        : events.length === 0
          ? m('p.CalendarWidget-empty', t('no_events'))
          : [
              live.length ? [m('h5.CalendarWidget-group', t('happening_now')), list(live)] : null,
              next.length ? [live.length ? m('h5.CalendarWidget-group', t('coming_up')) : null, list(next)] : null,
            ],
    ]);
  },
};

function list(events: CalEvent[]) {
  return m(
    'ul.CalendarWidget-list',
    events.map((ev) => eventRow(ev))
  );
}

/**
 * The line under an event's title. A multi-day event shows its date span
 * (an ongoing one, when it ends) — the date tile only carries the start.
 */
function when(ev: CalEvent): any {
  const start = eventDate(ev, ev.start);
  const end = ev.end ? eventDate(ev, ev.end) : null;
  const day: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };

  if (start.getTime() <= Date.now() && end) {
    return t('ends_at', { date: !ev.allDay && sameDay(end, new Date()) ? shortTime(ev.end!) : end.toLocaleDateString(loc(), day) });
  }
  if (end && !sameDay(start, end)) return dateRange(start, end, day);

  return ev.allDay ? t('all_day') : shortTime(ev.start);
}

function eventRow(ev: CalEvent) {
  const start = eventDate(ev, ev.start);
  return m(
    'li.CalendarWidget-event',
    {
      onclick: () => app.modal.show(() => import('./EventDetailModal'), { event: ev }),
      role: 'button',
      tabindex: 0,
    },
    [
      m('.CalendarWidget-date', { style: ev.category ? { '--cal-accent': ev.category.color } : undefined }, [
        m('span.CalendarWidget-mon', start.toLocaleDateString(loc(), { month: 'short' })),
        m('span.CalendarWidget-day', start.getDate()),
      ]),
      m('.CalendarWidget-body', [
        m('span.CalendarWidget-name', ev.title),
        m('span.CalendarWidget-meta', [
          when(ev),
          ev.location ? m('span.CalendarWidget-loc', [' · ', m('i.icon.fas.fa-location-dot'), ' ', ev.location]) : null,
        ]),
      ]),
    ]
  );
}

export default UpcomingEvents;
