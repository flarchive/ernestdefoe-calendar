import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import EventFormModal from './EventFormModal';
import EventDetailModal from './EventDetailModal';
import { listEvents, feedUrl, type CalEvent, type CalCategory } from '../../common/api';
import {
  monthMatrix, weekdayNames, monthLabel, eventOnDay, isToday, shortTime, formatRange,
  weekDays, weekRangeLabel, dayTitle, hourLabel, layoutDay, startOfDay, addDays,
} from '../../common/dates';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

type Mode = 'month' | 'week' | 'day' | 'list';
const HOURS = Array.from({ length: 24 }, (_, h) => h);

export default class CalendarPage extends Page {
  mode: Mode = 'month';
  cursor!: Date; // the focused reference date for all views
  events: CalEvent[] = [];
  categories: CalCategory[] = [];
  category = '';
  loading = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.cursor = new Date();
    this.mode = (app.forum.attribute('ernestdefoe-calendar.defaultView') as Mode) || 'month';
    app.setTitle(t('page_title'));
    this.load();
  }

  weekStart(): number { return (app.forum.attribute('ernestdefoe-calendar.weekStartsOn') as number) || 0; }

  range(): { from: Date; to: Date } {
    if (this.mode === 'month') {
      const cells = monthMatrix(this.cursor.getFullYear(), this.cursor.getMonth(), this.weekStart());
      const to = new Date(cells[41]);
      to.setHours(23, 59, 59);
      return { from: cells[0], to };
    }
    if (this.mode === 'week') {
      const days = weekDays(this.cursor, this.weekStart());
      return { from: days[0], to: addDays(days[6], 1) };
    }
    if (this.mode === 'day') {
      const from = startOfDay(this.cursor);
      return { from, to: addDays(from, 1) };
    }
    // list
    const from = startOfDay(new Date());
    const to = new Date(from);
    to.setMonth(to.getMonth() + 12);
    return { from, to };
  }

  load() {
    this.loading = true;
    const { from, to } = this.range();
    listEvents(from, to, this.category || undefined)
      .then((res) => { this.events = res.data; this.categories = res.categories; this.loading = false; m.redraw(); })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  step(delta: number) {
    if (this.mode === 'month') this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + delta, 1);
    else if (this.mode === 'week') this.cursor = addDays(this.cursor, 7 * delta);
    else if (this.mode === 'day') this.cursor = addDays(this.cursor, delta);
    this.load();
  }

  goToday() { this.cursor = new Date(); this.load(); }

  setMode(mode: Mode) { this.mode = mode; this.load(); }

  openCreate(day?: Date) { app.modal.show(EventFormModal, { day, onsave: () => this.load() }); }
  openEvent(ev: CalEvent) { app.modal.show(EventDetailModal, { event: ev, onchange: () => this.load() }); }

  view() {
    return m('.CalendarPage', m('.container', [
      this.header(),
      this.loading
        ? m('.CalendarPage-loading', m('div.LoadingIndicator'))
        : this.mode === 'month' ? this.monthView()
        : this.mode === 'week' ? this.timeGrid(weekDays(this.cursor, this.weekStart()))
        : this.mode === 'day' ? this.timeGrid([startOfDay(this.cursor)])
        : this.listView(),
    ]));
  }

  title(): string {
    if (this.mode === 'month') return monthLabel(this.cursor.getFullYear(), this.cursor.getMonth());
    if (this.mode === 'week') return weekRangeLabel(weekDays(this.cursor, this.weekStart()));
    if (this.mode === 'day') return dayTitle(this.cursor);
    return t('upcoming');
  }

  header() {
    const canCreate = app.forum.attribute('canCreateEvent');
    const navable = this.mode !== 'list';
    return m('.CalendarPage-header', [
      m('.CalendarPage-titlebar', [
        m('h1.CalendarPage-title', this.title()),
        navable
          ? m('.CalendarPage-nav', [
              Button.component({ className: 'Button Button--icon', icon: 'fas fa-chevron-left', onclick: () => this.step(-1) }),
              Button.component({ className: 'Button', onclick: () => this.goToday() }, t('today')),
              Button.component({ className: 'Button Button--icon', icon: 'fas fa-chevron-right', onclick: () => this.step(1) }),
            ])
          : null,
      ]),
      m('.CalendarPage-tools', [
        m('.CalendarPage-views', [
          tab(this, 'month', t('view_month')),
          tab(this, 'week', t('view_week')),
          tab(this, 'day', t('view_day')),
          tab(this, 'list', t('view_list')),
        ]),
        this.categories.length
          ? m('select.FormControl.CalendarPage-filter', { value: this.category, onchange: (e: any) => { this.category = e.target.value; this.load(); } }, [
              m('option', { value: '' }, t('field_category')),
              ...this.categories.map((c) => m('option', { value: c.slug }, c.name)),
            ])
          : null,
        m('a.Button.Button--icon.CalendarPage-subscribe', { href: feedUrl(), title: t('subscribe') as string }, [m('i.Button-icon.fas.fa-rss')]),
        canCreate ? Button.component({ className: 'Button Button--primary', icon: 'fas fa-plus', onclick: () => this.openCreate() }, t('add_event')) : null,
      ]),
    ]);
  }

  // ---- month ----
  monthView() {
    const cells = monthMatrix(this.cursor.getFullYear(), this.cursor.getMonth(), this.weekStart());
    return m('.CalendarGrid', [
      m('.CalendarGrid-weekdays', weekdayNames(this.weekStart()).map((d) => m('span', d))),
      m('.CalendarGrid-days', cells.map((day) => this.dayCell(day))),
    ]);
  }

  dayCell(day: Date) {
    const inMonth = day.getMonth() === this.cursor.getMonth();
    const dayEvents = this.events.filter((e) => eventOnDay(e, day)).slice(0, 4);
    const canCreate = app.forum.attribute('canCreateEvent');
    return m('.CalendarGrid-cell' + (inMonth ? '' : '.is-out') + (isToday(day) ? '.is-today' : ''), {
      ondblclick: canCreate ? () => this.openCreate(day) : undefined,
    }, [
      m('span.CalendarGrid-num', day.getDate()),
      m('.CalendarGrid-events', dayEvents.map((ev) => m('button.CalendarChip', {
        type: 'button',
        style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
        onclick: (e: Event) => { e.stopPropagation(); this.openEvent(ev); },
      }, [
        ev.allDay ? null : m('span.CalendarChip-time', shortTime(ev.start)),
        m('span.CalendarChip-title', ev.title),
      ]))),
    ]);
  }

  // ---- week / day (shared time grid) ----
  timeGrid(days: Date[]) {
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
    const has = this.events.some((e) => e.allDay && days.some((d) => eventOnDay(e, d)));
    if (!has) return null;
    return m('.CalendarTime-allday', [
      m('.CalendarTime-gutter.CalendarTime-alldayLabel', t('all_day')),
      m('.CalendarTime-alldayCells', days.map((d) =>
        m('.CalendarTime-alldayCell', this.events.filter((e) => e.allDay && eventOnDay(e, d)).map((ev) =>
          m('button.CalendarChip', {
            type: 'button',
            style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
            onclick: () => this.openEvent(ev),
          }, m('span.CalendarChip-title', ev.title))
        ))
      )),
    ]);
  }

  timeColumn(day: Date) {
    const segs = layoutDay(this.events, day);
    const canCreate = app.forum.attribute('canCreateEvent');
    return m('.CalendarTime-col' + (isToday(day) ? '.is-today' : ''), {
      ondblclick: canCreate ? (e: any) => this.createAt(e, day) : undefined,
    }, [
      ...HOURS.map((h) => m('.CalendarTime-line', { style: { top: (h / 24) * 100 + '%' } })),
      isToday(day) ? this.nowLine() : null,
      ...segs.map((s) => m('button.CalendarTime-event', {
        type: 'button',
        style: {
          top: s.top + '%',
          height: s.height + '%',
          left: (s.col / s.cols) * 100 + '%',
          width: 100 / s.cols + '%',
          ...(s.ev.category ? { '--cal-accent': s.ev.category.color } : {}),
        },
        title: s.ev.title,
        onclick: (e: Event) => { e.stopPropagation(); this.openEvent(s.ev); },
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
    this.openCreate(start);
  }

  // ---- list ----
  listView() {
    const now = Date.now();
    const upcoming = this.events.filter((e) => new Date(e.end || e.start).getTime() >= now);
    if (!upcoming.length) return m('.CalendarPage-empty', t('no_events'));
    return m('ul.CalendarList', upcoming.map((ev) => m('li.CalendarList-item', { onclick: () => this.openEvent(ev) }, [
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

function tab(page: CalendarPage, mode: Mode, label: string) {
  return m('button.Button.CalendarPage-view' + (page.mode === mode ? '.is-active' : ''), {
    type: 'button',
    onclick: () => page.setMode(mode),
  }, label);
}
