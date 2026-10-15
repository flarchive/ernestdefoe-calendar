import Component from 'flarum/common/Component';
import { monthMatrix, weekdayNames, eventOnDay, isToday, shortTime, sameDay, dayTitle, formatRange } from '../../common/dates';
import { type CalEvent } from '../../common/api';
import { measure } from './CalSkeleton';
import app from 'flarum/forum/app';

declare const m: any;
const addLabel = () => app.translator.trans('ernestdefoe-calendar.forum.add_event');
const noneLabel = () => app.translator.trans('ernestdefoe-calendar.forum.no_events_day');

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
  /** The day whose events the phone agenda lists. */
  selected: Date | null = null;

  view() {
    const a = this.attrs as MonthGridAttrs;
    const cells = monthMatrix(a.year, a.month, a.weekStart);

    // Today when it is in this month, otherwise the 1st — kept across redraws
    // until the reader taps another day or changes month.
    if (!this.selected || this.selected.getMonth() !== a.month || this.selected.getFullYear() !== a.year) {
      const today = new Date();
      this.selected = today.getMonth() === a.month && today.getFullYear() === a.year ? today : new Date(a.year, a.month, 1);
    }

    return [
      m('.CalendarGrid', measure('grid'), [
        m(
          '.CalendarGrid-weekdays',
          weekdayNames(a.weekStart).map((d: string) => m('span', d))
        ),
        m(
          '.CalendarGrid-days',
          cells.map((day: Date) => this.dayCell(day))
        ),
      ]),
      this.agenda(),
    ];
  }

  /**
   * Phones only (CSS). A seventh of a phone is ~50px, which fits no title —
   * every chip read "[t…" and the month looked empty. There the chips become
   * dots, and the tapped day's events are listed here, under the grid.
   */
  agenda() {
    const a = this.attrs as MonthGridAttrs;
    const day = this.selected!;
    const events = a.events.filter((e) => eventOnDay(e, day));

    return m('.CalendarGrid-agenda', [
      m('h4.CalendarGrid-agendaTitle', dayTitle(day)),
      events.length
        ? m(
            'ul.CalendarList',
            events.map((ev) =>
              m('li.CalendarList-item', { style: ev.category ? { '--cal-accent': ev.category.color } : undefined, onclick: () => a.onEvent(ev) }, [
                m('span.CalendarGrid-agendaDot'),
                m('.CalendarList-body', [m('span.CalendarList-name', ev.title), m('span.CalendarList-meta', formatRange(ev))]),
              ])
            )
          )
        : m(
            'p.CalendarGrid-agendaEmpty',
            a.canCreate ? m('button.Button.Button--link', { type: 'button', onclick: () => a.onCreate(day) }, addLabel()) : noneLabel()
          ),
    ]);
  }

  dayCell(day: Date) {
    const a = this.attrs as MonthGridAttrs;
    const inMonth = day.getMonth() === a.month;
    const dayEvents = a.events.filter((e) => eventOnDay(e, day)).slice(0, 4);
    return m(
      '.CalendarGrid-cell' + (inMonth ? '' : '.is-out') + (isToday(day) ? '.is-today' : '') + (sameDay(day, this.selected!) ? '.is-selected' : ''),
      {
        onclick: () => (this.selected = day),
        ondblclick: a.canCreate ? () => a.onCreate(day) : undefined,
      },
      [
        m('span.CalendarGrid-num', day.getDate()),
        m(
          '.CalendarGrid-events',
          dayEvents.map((ev) =>
            m(
              'button.CalendarChip',
              {
                type: 'button',
                style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
                onclick: (e: Event) => {
                  e.stopPropagation();
                  a.onEvent(ev);
                },
              },
              [ev.allDay ? null : m('span.CalendarChip-time', shortTime(ev.start)), m('span.CalendarChip-title', ev.title)]
            )
          )
        ),
      ]
    );
  }
}
