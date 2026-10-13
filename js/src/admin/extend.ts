import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import CategoryManager from './components/CategoryManager';
import { hasFofWidgets } from '../common/registerFofWidget';

declare const m: any;
declare const flarum: any;
const t = (k: string) => app.translator.trans('ernestdefoe-calendar.admin.' + k);

/**
 * `{ id: label }` for a store type, for a multi-select's options. The label
 * is read with `label`, because models name it differently.
 *
 * 🚨 2.3.0 called `model.name()` on groups, and a Group has no name() — it
 * has nameSingular()/namePlural(). The throw took the whole Calendar settings
 * page down with it. Reported by jslirola on discuss.flarum.org.
 */
function options(type: string, label: (model: any) => string, keep: (model: any) => boolean = () => true): Record<string, string> {
  const out: Record<string, string> = {};
  app.store
    .all(type)
    .filter(keep)
    .forEach((model: any) => (out[model.id()] = label(model)));
  return out;
}

/*
 * With fof/forum-widgets-core enabled, its widget manager decides where each
 * calendar widget goes (or whether it shows at all), and the sidebar mounts
 * these toggles drive stand aside — so a toggle there would do nothing.
 */
const unlessWidgetCore = (config: any) => (hasFofWidgets() ? null : config);

// Groups are in the admin payload; tags are not, so ask for them once.
let tagsRequested = false;
function tagOptions(): Record<string, string> {
  if (!tagsRequested && !app.store.all('tags').length) {
    tagsRequested = true;
    app.store.find('tags').then(() => m.redraw());
  }
  return options('tags', (tag: any) => tag.name());
}

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ernestdefoe-calendar.default_view',
      type: 'select',
      label: t('default_view'),
      options: { month: t('view_month_opt'), week: t('view_week_opt'), day: t('view_day_opt'), list: t('view_list_opt') },
      default: 'month',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-calendar.week_starts_on',
      type: 'select',
      label: t('week_starts_on'),
      options: { '0': t('week_sunday'), '1': t('week_monday') },
      default: '0',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-calendar.hide_nav_link',
      type: 'boolean',
      label: t('hide_nav_link'),
      help: t('hide_nav_link_help'),
      default: false,
    }))
    .setting(() =>
      unlessWidgetCore({
        setting: 'ernestdefoe-calendar.show_index_widget',
        type: 'boolean',
        label: t('show_index_widget'),
        default: true,
      })
    )
    .setting(() => ({
      setting: 'ernestdefoe-calendar.index_widget_count',
      type: 'number',
      label: t('index_widget_count'),
      default: 5,
    }))
    .setting(() =>
      unlessWidgetCore({
        setting: 'ernestdefoe-calendar.show_pulse_widget',
        type: 'boolean',
        label: t('show_pulse_widget'),
        help: t('show_pulse_widget_help'),
        default: true,
      })
    )
    .setting(() => ({
      setting: 'ernestdefoe-calendar.pulse_exclude_groups',
      type: 'select',
      multiple: true,
      json: true,
      label: t('pulse_exclude_groups'),
      help: t('pulse_exclude_groups_help'),
      // Guests and Members are not stored as memberships, so they cannot be
      // matched — and leaving out every member would empty the pulse anyway.
      options: options(
        'groups',
        (g: any) => g.namePlural(),
        (g: any) => !['2', '3'].includes(g.id())
      ),
      default: [],
    }))
    .setting(() => ({
      setting: 'ernestdefoe-calendar.pulse_exclude_users',
      type: 'text',
      label: t('pulse_exclude_users'),
      help: t('pulse_exclude_users_help'),
      placeholder: 'newsbot, rss-feed',
    }))
    .setting(() =>
      unlessWidgetCore({
        setting: 'ernestdefoe-calendar.show_memories_widget',
        type: 'boolean',
        label: t('show_memories_widget'),
        default: true,
      })
    )
    .setting(() =>
      'flarum-tags' in (flarum?.extensions ?? {})
        ? {
            setting: 'ernestdefoe-calendar.onthisday_exclude_tags',
            type: 'select',
            multiple: true,
            json: true,
            label: t('onthisday_exclude_tags'),
            help: t('onthisday_exclude_tags_help'),
            options: tagOptions(),
            default: [],
          }
        : null
    )
    .setting(() =>
      unlessWidgetCore({
        setting: 'ernestdefoe-calendar.show_celebrations_widget',
        type: 'boolean',
        label: t('show_celebrations_widget'),
        default: true,
      })
    )
    .permission(() => ({ icon: 'fas fa-calendar-plus', label: t('permission_create'), permission: 'calendar.create' }), 'start')
    .permission(() => ({ icon: 'fas fa-calendar-check', label: t('permission_manage'), permission: 'calendar.manage' }), 'moderate')
    // Custom component (category CRUD) on the same settings page — the Flarum 2
    // declarative replacement for app.extensionData.for(...).registerSetting().
    .customSetting(() => m(CategoryManager), -10),
];
