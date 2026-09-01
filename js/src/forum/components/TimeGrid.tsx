import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { isToday, hourLabel, layoutDay, startOfDay, shortTime, eventOnDay } from '../../common/dates';
import { type CalEvent } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);
const HOURS = Array.from({ length: 24 }, (_, h) => h);

interface TimeGridAttrs {
  days: Date[];
  events: CalEvent[];
  canCreate: boolean;
  onEvent: (ev: CalEvent) => void;
  onCreate: (start: Date) => void;
}

/**
 * Shared week/day time grid (1 day = day view, 7 days = week view). Pure
 * presentation; double-click on a column asks CalendarPage to open the create
 * form pre-filled at the clicked time slot.
 */
export default class TimeGrid extends Component {
  view() {
    const { days } = this.attrs as TimeGridAttrs;
    return m('.CalendarTime' + (days.length === 1 ? '.CalendarTime--day' : '.CalendarTime--week'), [
      m('.CalendarTime-head', [
        m('.CalendarTime-gutter'),
        m('.CalendarTime-heads', days.map((d) => m('.CalendarTime-dayhead' + (isToday(d) ? '.is-today' : ''), [
          m('span.CalendarTime-dow', d.toLocaleDateString(undefined, { weekday: 'short' })),
          m('span.CalendarTime-dnum', d.getDate()),
        ]))),
      ]),
      this.allDayRow(days),
      m('.CalendarTime-body', [
        m('.CalendarTime-hours', HOURS.map((h) => m('.CalendarTime-hourlabel', h === 0 ? null : m('span', hourLabel(h))))),
        m('.CalendarTime-grid', days.map((d) => this.timeColumn(d))),
      ]),
    ]);
  }

  allDayRow(days: Date[]) {
    const a = this.attrs as TimeGridAttrs;
    const has = a.events.some((e) => e.allDay && days.some((d) => eventOnDay(e, d)));
    if (!has) return null;
    return m('.CalendarTime-allday', [
      m('.CalendarTime-gutter.CalendarTime-alldayLabel', t('all_day')),
      m('.CalendarTime-alldayCells', days.map((d) =>
        m('.CalendarTime-alldayCell', a.events.filter((e) => e.allDay && eventOnDay(e, d)).map((ev) =>
          m('button.CalendarChip', {
            type: 'button',
            style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
            onclick: () => a.onEvent(ev),
          }, m('span.CalendarChip-title', ev.title))
        ))
      )),
    ]);
  }

  timeColumn(day: Date) {
    const a = this.attrs as TimeGridAttrs;
    const segs = layoutDay(a.events, day);
    return m('.CalendarTime-col' + (isToday(day) ? '.is-today' : ''), {
      ondblclick: a.canCreate ? (e: any) => this.createAt(e, day) : undefined,
    }, [
      ...HOURS.map((h) => m('.CalendarTime-line', { style: { top: (h / 24) * 100 + '%' } })),
      isToday(day) ? this.nowLine() : null,
      ...segs.map((s: any) => m('button.CalendarTime-event', {
        type: 'button',
        style: {
          top: s.top + '%',
          height: s.height + '%',
          left: (s.col / s.cols) * 100 + '%',
          width: 100 / s.cols + '%',
          ...(s.ev.category ? { '--cal-accent': s.ev.category.color } : {}),
        },
        title: s.ev.title,
        onclick: (e: Event) => { e.stopPropagation(); a.onEvent(s.ev); },
      }, [
        m('span.CalendarTime-eventTime', shortTime(s.ev.start)),
        m('span.CalendarTime-eventTitle', s.ev.title),
      ])),
    ]);
  }

  nowLine() {
    const now = new Date();
    const pct = ((now.getHours() * 60 + now.getMinutes()) / 1440) * 100;
    return m('.CalendarTime-now', { style: { top: pct + '%' } });
  }

  /** Double-click a time column → open the create form pre-filled at that slot. */
  createAt(e: any, day: Date) {
    const rect = e.currentTarget.getBoundingClientRect();
    const pct = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
    const slot = Math.round((pct * 1440) / 30) * 30; // snap to 30 min
    const start = startOfDay(day);
    start.setMinutes(Math.min(slot, 23 * 60 + 30));
    (this.attrs as TimeGridAttrs).onCreate(start);
  }
}
