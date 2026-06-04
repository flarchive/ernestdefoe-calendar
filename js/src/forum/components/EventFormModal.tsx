import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import { saveEvent, listCategories, type CalEvent, type CalCategory } from '../../common/api';
import { toLocalInput } from '../../common/dates';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

interface FormAttrs extends IInternalModalAttrs {
  event?: CalEvent;
  day?: Date;
  onsave?: (ev: CalEvent) => void;
}

/** Create / edit an event. Pass `attrs.event` to edit; `attrs.onsave(ev)` fires on success. */
export default class EventFormModal extends Modal<FormAttrs> {
  data!: Record<string, any>;
  categories: CalCategory[] = [];
  uploading = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    const ev: CalEvent | undefined = this.attrs.event;
    // Default start: a clicked day/slot if provided, else the next whole hour.
    const now = this.attrs.day ? new Date(this.attrs.day) : new Date();
    if (!this.attrs.day) { now.setMinutes(0, 0, 0); now.setHours(now.getHours() + 1); }
    const later = new Date(now.getTime() + 60 * 60 * 1000);

    this.data = ev
      ? {
          title: ev.title,
          description: ev.description || '',
          start: toLocalInput(new Date(ev.start)),
          end: ev.end ? toLocalInput(new Date(ev.end)) : '',
          allDay: ev.allDay,
          timezone: ev.timezone || guessTz(),
          location: ev.location || '',
          url: ev.url || '',
          coverUrl: ev.coverUrl || '',
          categoryId: ev.category?.id || '',
          repeat: freqOf(ev.rrule),
        }
      : {
          title: '',
          description: '',
          start: toLocalInput(now),
          end: toLocalInput(later),
          allDay: false,
          timezone: guessTz(),
          location: '',
          url: '',
          coverUrl: '',
          categoryId: '',
          repeat: '',
        };

    listCategories().then((c) => { this.categories = c; m.redraw(); }).catch(() => {});
  }

  className() { return 'CalendarEventModal Modal--medium'; }
  title() { return this.attrs.event ? t('edit_event') : t('add_event'); }

  content() {
    const d = this.data;
    const field = (label: string, input: any) => m('.CalendarForm-field', [m('label.CalendarForm-label', label), input]);

    // Flarum 2's Modal does NOT wrap content() in a form (unlike 1.x), so we must
    // render our own <form> — otherwise the type="submit" button submits nothing
    // and "create" appears to do nothing.
    return m('.Modal-body.CalendarForm', m('form.CalendarForm-form', { onsubmit: (e: Event) => this.onsubmit(e) }, [
      m('input.FormControl.CalendarForm-titleInput', {
        value: d.title, placeholder: t('field_title'), required: true, autofocus: true,
        oninput: (e: any) => (d.title = e.target.value),
      }),

      // ---- When ----
      m('.CalendarForm-section', [
        m('.CalendarForm-sectionTitle', [m('i.icon.fas.fa-clock'), ' ', t('section_when')]),
        m('label.CalendarForm-toggle', [
          m('input', { type: 'checkbox', checked: d.allDay, onchange: (e: any) => (d.allDay = e.target.checked) }),
          m('span.CalendarForm-toggleTrack'),
          m('span.CalendarForm-toggleLabel', t('field_all_day')),
        ]),
        m('.CalendarForm-grid', [
          field(t('field_start'), m('input.FormControl', { type: d.allDay ? 'date' : 'datetime-local', value: d.allDay ? d.start.slice(0, 10) : d.start, oninput: (e: any) => (d.start = d.allDay ? e.target.value + 'T00:00' : e.target.value) })),
          field(t('field_end'), m('input.FormControl', { type: d.allDay ? 'date' : 'datetime-local', value: d.allDay ? (d.end || '').slice(0, 10) : d.end, oninput: (e: any) => (d.end = d.allDay ? (e.target.value ? e.target.value + 'T00:00' : '') : e.target.value) })),
        ]),
        field(t('field_repeat'), m('select.FormControl', { value: d.repeat, onchange: (e: any) => (d.repeat = e.target.value) }, [
          m('option', { value: '' }, t('repeat_none')),
          m('option', { value: 'DAILY' }, t('repeat_daily')),
          m('option', { value: 'WEEKLY' }, t('repeat_weekly')),
          m('option', { value: 'MONTHLY' }, t('repeat_monthly')),
          m('option', { value: 'YEARLY' }, t('repeat_yearly')),
        ])),
      ]),

      // ---- Details ----
      m('.CalendarForm-section', [
        m('.CalendarForm-sectionTitle', [m('i.icon.fas.fa-circle-info'), ' ', t('section_details')]),
        field(t('field_location'), m('input.FormControl', { value: d.location, placeholder: '123 Main St, City', oninput: (e: any) => (d.location = e.target.value) })),
        field(t('field_category'), m('select.FormControl', { value: d.categoryId, onchange: (e: any) => (d.categoryId = e.target.value) }, [
          m('option', { value: '' }, '—'),
          ...this.categories.map((c) => m('option', { value: c.id }, c.name)),
        ])),
        field(t('field_url'), m('input.FormControl', { value: d.url, placeholder: 'https://…', oninput: (e: any) => (d.url = e.target.value) })),
        this.coverField(),
        field(t('field_description'), m('textarea.FormControl', { rows: 4, value: d.description, placeholder: t('field_description'), oninput: (e: any) => (d.description = e.target.value) })),
      ]),

      // ---- Actions ----
      m('.CalendarForm-actions', [
        Button.component({ className: 'Button', type: 'button', onclick: () => app.modal.close() }, t('cancel')),
        Button.component({ type: 'submit', className: 'Button Button--primary', icon: 'fas fa-check', loading: this.loading }, t('save')),
      ]),
    ]));
  }

  /**
   * Cover image control. With FoF Upload enabled (and the actor permitted), this
   * shows a real file picker; either way a plain URL field remains so covers work
   * without any extra extension installed.
   */
  coverField() {
    const d = this.data;
    const canUpload = !!app.forum.attribute('calendarCoverUploads');

    return m('.Form-group', [
      m('label', t('field_cover')),
      d.coverUrl
        ? m('.CalendarForm-cover', { style: { backgroundImage: `url("${String(d.coverUrl).replace(/"/g, '%22')}")` } }, [
            m('button.Button.Button--icon.CalendarForm-coverRemove', { type: 'button', title: t('cover_remove'), onclick: () => (d.coverUrl = '') }, m('i.fas.fa-times')),
          ])
        : null,
      m('.CalendarForm-coverControls', [
        canUpload
          ? m('label.Button.CalendarForm-upload' + (this.uploading ? '.disabled' : ''), [
              m('i.Button-icon.fas.fa-upload'),
              m('span.Button-label', this.uploading ? t('cover_uploading') : t('cover_upload')),
              m('input', { type: 'file', accept: 'image/*', disabled: this.uploading, onchange: (e: any) => this.uploadCover(e) }),
            ])
          : null,
        m('input.FormControl', { value: d.coverUrl, placeholder: canUpload ? t('cover_or_url') : 'https://…/cover.jpg', oninput: (e: any) => (d.coverUrl = e.target.value) }),
      ]),
    ]);
  }

  uploadCover(e: any) {
    const file = e.target?.files?.[0];
    if (!file) return;
    this.uploading = true;
    m.redraw();

    const body = new FormData();
    body.append('files[]', file);

    fetch(app.forum.attribute('apiUrl') + '/fof/upload', {
      method: 'POST',
      body,
      credentials: 'include',
      headers: { 'X-CSRF-Token': (app.session as any).csrfToken },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((res: any) => {
        const f = res?.data?.[0]?.attributes || {};
        const url = f.url || f.relativeUrl || f.uploadUrl;
        if (url) this.data.coverUrl = url;
        else app.alerts.show({ type: 'error' }, t('cover_error'));
      })
      .catch(() => app.alerts.show({ type: 'error' }, t('cover_error')))
      .then(() => { this.uploading = false; m.redraw(); });
  }

  onsubmit(e: Event) {
    e.preventDefault();
    this.loading = true;
    const d = this.data;
    const attrs: Record<string, unknown> = {
      title: d.title,
      description: d.description,
      start: localToIso(d.start, d.allDay),
      end: d.end ? localToIso(d.end, d.allDay) : null,
      allDay: d.allDay,
      timezone: d.timezone,
      location: d.location,
      url: d.url,
      coverUrl: d.coverUrl,
      categoryId: d.categoryId || null,
      rrule: d.repeat ? 'FREQ=' + d.repeat : null,
    };

    saveEvent(attrs, this.attrs.event?.id)
      .then((ev) => {
        this.attrs.onsave?.(ev);
        app.modal.close();
        app.alerts.show({ type: 'success' }, t('saved'));
      })
      .catch(() => {
        this.loading = false;
        app.alerts.show({ type: 'error' }, t('save_error'));
        m.redraw();
      });
  }
}

function guessTz(): string {
  try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch (e) { return 'UTC'; }
}
function freqOf(rrule?: string | null): string {
  const m2 = /FREQ=([A-Z]+)/.exec(rrule || '');
  return m2 ? m2[1] : '';
}
/** A datetime-local / date string is local wall time → ISO (UTC). */
function localToIso(val: string, allDay: boolean): string {
  const v = allDay && val.length === 10 ? val + 'T00:00' : val;
  return new Date(v).toISOString();
}
