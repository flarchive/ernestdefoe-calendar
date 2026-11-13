import app from 'flarum/admin/app';
import { extend, override } from 'flarum/common/extend';
import Select from 'flarum/common/components/Select';

declare const m: any;
declare const flarum: any;

/**
 * Calendar's two FoF Badges metrics in its trigger builder. The builder's
 * list of metrics is fixed in its own code, so they are added to it here; the
 * server registers the metrics themselves (src/Badges). Without fof/badges
 * the module never loads and none of this runs.
 */
const BUILDER = 'admin/components/TriggerBuilder';
const METRICS = ['calendar_events_attended', 'calendar_events_organized'];
const t = (k: string): string => app.translator.trans('ernestdefoe-calendar.admin.badges.' + k, {}, true);

let categories: { id: number; name: string }[] | null = null;
function loadCategories() {
  if (categories) return;
  categories = [];
  app
    .request<any>({ method: 'GET', url: app.forum.attribute('apiUrl') + '/calendar/categories' })
    .then((res: any) => {
      categories = res.data || [];
      m.redraw();
    })
    .catch(() => {});
}

const usesCalendar = (builder: any): boolean =>
  Array.isArray(builder?.config?.conditions) && builder.config.conditions.some((c: any) => METRICS.includes(c?.metric));

function categoryField(builder: any) {
  return m('.TriggerBuilder-calendarCategory', [
    m('.Form-group', [
      m('label', t('category')),
      m(Select, {
        // Keys like "c4": a plain "4" sorts ahead of "" in an object, which
        // put "Any category" last.
        value: builder.config.calendar_category_id ? 'c' + builder.config.calendar_category_id : '',
        options: { '': t('category_any'), ...Object.fromEntries((categories || []).map((c) => ['c' + c.id, c.name])) },
        onchange: (val: string) => {
          builder.config.calendar_category_id = val ? parseInt(val.slice(1), 10) : undefined;
          builder.emitChange();
        },
      }),
      m('p.helpText', t('category_help')),
    ]),
  ]);
}

export default function registerBadges() {
  // Only once fof/badges' builder has loaded, and only the methods it still
  // has: if a later fof/badges renames or drops one, Calendar's part simply
  // doesn't appear instead of throwing on the badge form.
  flarum.reg.onLoad('fof-badges', BUILDER, (Builder: any) => {
    const proto = Builder?.prototype;
    if (!proto) return;

    if (typeof proto.getAvailableMetrics === 'function') {
      extend(proto, 'getAvailableMetrics', function (metrics: { value: string; label: string }[]) {
        if (!Array.isArray(metrics)) return;
        metrics.push({ value: METRICS[0], label: t('events_attended') }, { value: METRICS[1], label: t('events_organized') });
      });
    }

    // An optional category, under the conditions, while a Calendar metric is used.
    if (typeof proto.view === 'function' && typeof proto.emitChange === 'function') {
      extend(proto, 'view', function (this: any, vdom: any) {
        if (!usesCalendar(this) || !Array.isArray(vdom?.children)) return;
        loadCategories();

        vdom.children.push(categoryField(this));
      });

      // Never leave a category behind once no condition uses a Calendar metric.
      override(proto, 'emitChange', function (this: any, original: () => void) {
        if (this.config && (!usesCalendar(this) || !this.config.calendar_category_id)) delete this.config.calendar_category_id;
        original();
      });
    }
  });
}
