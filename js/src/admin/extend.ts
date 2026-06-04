import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import CategoryManager from './components/CategoryManager';

declare const m: any;
const t = (k: string) => app.translator.trans('ernestdefoe-calendar.admin.' + k);

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ernestdefoe-calendar.default_view',
      type: 'select',
      label: t('default_view'),
      options: { month: t('view_month_opt'), list: t('view_list_opt') },
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
      setting: 'ernestdefoe-calendar.show_index_widget',
      type: 'boolean',
      label: t('show_index_widget'),
      default: true,
    }))
    .setting(() => ({
      setting: 'ernestdefoe-calendar.index_widget_count',
      type: 'number',
      label: t('index_widget_count'),
      default: 5,
    }))
    .setting(() => ({
      setting: 'ernestdefoe-calendar.link_discussion',
      type: 'boolean',
      label: t('link_discussion'),
      default: true,
    }))
    .permission(
      () => ({ icon: 'fas fa-calendar-plus', label: t('permission_create'), permission: 'calendar.create' }),
      'start'
    )
    .permission(
      () => ({ icon: 'fas fa-calendar-check', label: t('permission_manage'), permission: 'calendar.manage' }),
      'moderate'
    )
    // Custom component (category CRUD) on the same settings page — the Flarum 2
    // declarative replacement for app.extensionData.for(...).registerSetting().
    .customSetting(() => m(CategoryManager), -10),
];
