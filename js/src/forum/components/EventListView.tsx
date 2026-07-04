import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { formatRange } from '../../common/dates';
import { type CalEvent } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

/**
 * Upcoming-events list view. Pure presentation: receives the already-loaded
 * events + an onEvent callback; holds no state of its own.
 *
 * attrs: { events: CalEvent[], onEvent: (ev: CalEvent) => void }
 */
export default class EventListView extends Component {
  view() {
    const { events, onEvent } = this.attrs as { events: CalEvent[]; onEvent: (ev: CalEvent) => void };
    const now = Date.now();
    const upcoming = events.filter((e) => new Date(e.end || e.start).getTime() >= now);
    if (!upcoming.length) return m('.CalendarPage-empty', t('no_events'));

    return m('ul.CalendarList', upcoming.map((ev) => m('li.CalendarList-item', { onclick: () => onEvent(ev) }, [
      m('.CalendarList-date', { style: ev.category ? { '--cal-accent': ev.category.color } : undefined }, [
        m('span.CalendarList-mon', new Date(ev.start).toLocaleDateString(undefined, { month: 'short' })),
        m('span.CalendarList-day', new Date(ev.start).getDate()),
      ]),
      m('.CalendarList-body', [
        m('span.CalendarList-name', ev.title),
        m('span.CalendarList-meta', formatRange(ev)),
        ev.location ? m('span.CalendarList-loc', [m('i.icon.fas.fa-location-dot'), ' ', ev.location]) : null,
      ]),
    ])));
  }
}
