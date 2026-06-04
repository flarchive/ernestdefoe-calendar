import app from 'flarum/forum/app';
import EventDetailModal from './EventDetailModal';
import { listEvents, type CalEvent } from '../../common/api';
import { shortTime } from '../../common/dates';

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
    const from = new Date();
    const to = new Date();
    to.setFullYear(to.getFullYear() + 1);
    listEvents(from, to, attrs.category || undefined)
      .then((res) => {
        const now = Date.now();
        this.events = (res.data || [])
          .filter((e: CalEvent) => new Date(e.end || e.start).getTime() >= now)
          .slice(0, Math.max(1, Number(attrs.count) || 5));
        m.redraw();
      })
      .catch(() => { this.events = []; m.redraw(); });
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('widget_title');
    const events: CalEvent[] | null = this.events;

    return m('.CalendarWidget', [
      m('.CalendarWidget-head', [
        m('h4.CalendarWidget-title', title),
        m('a.CalendarWidget-all', { href: app.route('calendar'), oncreate: m.route.Link }, t('nav')),
      ]),
      events === null
        ? m('.CalendarWidget-loading', [m('span.CalendarWidget-skel'), m('span.CalendarWidget-skel')])
        : events.length === 0
        ? m('p.CalendarWidget-empty', t('no_events'))
        : m('ul.CalendarWidget-list', events.map((ev) => eventRow(ev))),
    ]);
  },
};

function eventRow(ev: CalEvent) {
  const start = new Date(ev.start);
  return m('li.CalendarWidget-event', {
    onclick: () => app.modal.show(EventDetailModal, { event: ev }),
    role: 'button',
    tabindex: 0,
  }, [
    m('.CalendarWidget-date', { style: ev.category ? { '--cal-accent': ev.category.color } : undefined }, [
      m('span.CalendarWidget-mon', start.toLocaleDateString(undefined, { month: 'short' })),
      m('span.CalendarWidget-day', start.getDate()),
    ]),
    m('.CalendarWidget-body', [
      m('span.CalendarWidget-name', ev.title),
      m('span.CalendarWidget-meta', [
        ev.allDay ? t('all_day') : shortTime(ev.start),
        ev.location ? m('span.CalendarWidget-loc', [' · ', m('i.icon.fas.fa-location-dot'), ' ', ev.location]) : null,
      ]),
    ]),
  ]);
}

export default UpcomingEvents;
