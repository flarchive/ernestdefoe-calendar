import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.admin.' + k, p);

interface Cat { id?: number; name: string; slug?: string; color: string }

const api = (path: string) => app.forum.attribute('apiUrl') + path;
const PRESETS = ['#3b5bdb', '#e8590c', '#2f9e44', '#9c36b5', '#e03131', '#1098ad', '#f08c00', '#495057'];

/**
 * In-place CRUD for event categories, rendered inside the extension's settings
 * page. Talks straight to the JSON API (admin-guarded) so it needs no store
 * models — keeping the extension's footprint small.
 */
export default class CategoryManager extends Component {
  loading = true;
  saving: Record<string | number, boolean> = {};
  cats: Cat[] = [];
  draft: Cat = { name: '', color: PRESETS[0] };

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    this.loading = true;
    app.request<any>({ method: 'GET', url: api('/calendar/categories') })
      .then((res: any) => { this.cats = res.data || []; this.loading = false; m.redraw(); })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  view() {
    return m('.CalendarAdmin', [
      m('label.FormLabel', t('categories')),
      m('p.helpText', t('categories_help')),

      this.loading
        ? m(LoadingIndicator)
        : m('.CalendarAdmin-categories', [
            ...this.cats.map((c) => this.row(c)),
            this.newRow(),
          ]),
    ]);
  }

  row(c: Cat) {
    return m('.CalendarAdmin-category', { key: c.id }, [
      this.swatch(c),
      m('input.FormControl', { value: c.name, oninput: (e: any) => (c.name = e.target.value) }),
      Button.component({ className: 'Button Button--icon', icon: 'fas fa-check', loading: !!this.saving[c.id!], disabled: !c.name.trim(), onclick: () => this.save(c) }),
      Button.component({ className: 'Button Button--icon CalendarAdmin-del', icon: 'fas fa-trash', onclick: () => this.remove(c) }),
    ]);
  }

  newRow() {
    const d = this.draft;
    return m('.CalendarAdmin-category.CalendarAdmin-new', [
      this.swatch(d),
      m('input.FormControl', { value: d.name, placeholder: t('category_add'), oninput: (e: any) => (d.name = e.target.value) }),
      Button.component({ className: 'Button Button--primary', icon: 'fas fa-plus', loading: !!this.saving['new'], disabled: !d.name.trim(), onclick: () => this.create() }, t('category_add')),
    ]);
  }

  swatch(c: Cat) {
    return m('.CalendarAdmin-swatchWrap', [
      m('span.CalendarAdmin-swatch', { style: { background: c.color } }),
      m('input.CalendarAdmin-color', { type: 'color', value: c.color, oninput: (e: any) => (c.color = e.target.value) }),
      m('.CalendarAdmin-presets', PRESETS.map((p) =>
        m('button.CalendarAdmin-preset', { type: 'button', style: { background: p }, onclick: () => (c.color = p) })
      )),
    ]);
  }

  save(c: Cat) {
    this.saving[c.id!] = true;
    app.request({ method: 'PATCH', url: api('/calendar/categories/' + c.id), body: { data: { attributes: { name: c.name, color: c.color } } } })
      .then(() => { this.saving[c.id!] = false; app.alerts.show({ type: 'success' }, t('category_saved')); m.redraw(); })
      .catch(() => { this.saving[c.id!] = false; m.redraw(); });
  }

  create() {
    this.saving['new'] = true;
    app.request<any>({ method: 'POST', url: api('/calendar/categories'), body: { data: { attributes: { name: this.draft.name, color: this.draft.color } } } })
      .then((res: any) => {
        this.cats.push(res.data);
        this.draft = { name: '', color: PRESETS[this.cats.length % PRESETS.length] };
        this.saving['new'] = false;
        m.redraw();
      })
      .catch(() => { this.saving['new'] = false; m.redraw(); });
  }

  remove(c: Cat) {
    if (!confirm(t('category_delete_confirm') as any)) return;
    app.request({ method: 'DELETE', url: api('/calendar/categories/' + c.id) })
      .then(() => { this.cats = this.cats.filter((x) => x !== c); m.redraw(); })
      .catch(() => {});
  }
}
