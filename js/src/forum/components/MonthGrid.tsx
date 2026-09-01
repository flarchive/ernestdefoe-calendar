import Component from 'flarum/common/Component';
import { monthMatrix, weekdayNames, eventOnDay, isToday, shortTime } from '../../common/dates';
import { type CalEvent } from '../../common/api';

declare const m: any;

interface MonthGridAttrs {
  year: number;
  month: number;
  weekStart: number;
  events: CalEvent[];
  canCreate: boolean;
  onEvent: (ev: CalEvent) => void;
  onCreate: (day: Date) => void;
}

/**
 * Month-grid view. Pure presentation driven by attrs; CalendarPage owns the
 * state (cursor/events) and the modal callbacks.
 */
export default class MonthGrid extends Component {
  view() {
    const a = this.attrs as MonthGridAttrs;
    const cells = monthMatrix(a.year, a.month, a.weekStart);
    return m('.CalendarGrid', [
      m('.CalendarGrid-weekdays', weekdayNames(a.weekStart).map((d: string) => m('span', d))),
      m('.CalendarGrid-days', cells.map((day: Date) => this.dayCell(day))),
    ]);
  }

  dayCell(day: Date) {
    const a = this.attrs as MonthGridAttrs;
    const inMonth = day.getMonth() === a.month;
    const dayEvents = a.events.filter((e) => eventOnDay(e, day)).slice(0, 4);
    return m('.CalendarGrid-cell' + (inMonth ? '' : '.is-out') + (isToday(day) ? '.is-today' : ''), {
      ondblclick: a.canCreate ? () => a.onCreate(day) : undefined,
    }, [
      m('span.CalendarGrid-num', day.getDate()),
      m('.CalendarGrid-events', dayEvents.map((ev) => m('button.CalendarChip', {
        type: 'button',
        style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
        onclick: (e: Event) => { e.stopPropagation(); a.onEvent(ev); },
      }, [
        ev.allDay ? null : m('span.CalendarChip-time', shortTime(ev.start)),
        m('span.CalendarChip-title', ev.title),
      ]))),
    ]);
  }
}
