import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import EventFormModal from './EventFormModal';
import EventDetailModal from './EventDetailModal';
import MonthGrid from './MonthGrid';
import TimeGrid from './TimeGrid';
import EventListView from './EventListView';
import { listEvents, feedUrl, type CalEvent, type CalCategory } from '../../common/api';
import {
  monthMatrix, monthLabel, weekDays, weekRangeLabel, dayTitle, startOfDay, addDays,
} from '../../common/dates';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

type Mode = 'month' | 'week' | 'day' | 'list';

/**
 * Calendar page — the coordinator. Owns state (mode, cursor, events, category)
 * and data-fetch; delegates rendering of each view to a dedicated POJO
 * component (MonthGrid / TimeGrid / EventListView).
 */
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
        ? m('.CalendarPage-loading', m(LoadingIndicator, { size: 'large' }))
        : this.body(),
    ]));
  }

  /** Render the active view via its dedicated component. */
  body() {
    const canCreate = !!app.forum.attribute('canCreateEvent');
    const onEvent = (ev: CalEvent) => this.openEvent(ev);
    const onCreate = (d: Date) => this.openCreate(d);

    if (this.mode === 'month') {
      return m(MonthGrid, {
        year: this.cursor.getFullYear(), month: this.cursor.getMonth(), weekStart: this.weekStart(),
        events: this.events, canCreate, onEvent, onCreate,
      });
    }
    if (this.mode === 'week') {
      return m(TimeGrid, { days: weekDays(this.cursor, this.weekStart()), events: this.events, canCreate, onEvent, onCreate });
    }
    if (this.mode === 'day') {
      return m(TimeGrid, { days: [startOfDay(this.cursor)], events: this.events, canCreate, onEvent, onCreate });
    }
    return m(EventListView, { events: this.events, onEvent });
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
}

function tab(page: CalendarPage, mode: Mode, label: string) {
  return m('button.Button.CalendarPage-view' + (page.mode === mode ? '.is-active' : ''), {
    type: 'button',
    onclick: () => page.setMode(mode),
  }, label);
}
