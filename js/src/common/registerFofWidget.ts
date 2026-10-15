declare const flarum: any;

/** The calendar's widgets, keyed as fof/forum-widgets-core stores them. */
export type FofWidgetKey = 'calendarUpcoming' | 'calendarPulse' | 'calendarCelebrations' | 'calendarMemories';

const WIDGETS: { key: FofWidgetKey; title: string; icon: string; position: number }[] = [
  { key: 'calendarUpcoming', title: 'widget_title', icon: 'fas fa-calendar-days', position: 1 },
  { key: 'calendarPulse', title: 'pulse_title', icon: 'fas fa-chart-simple', position: 2 },
  { key: 'calendarCelebrations', title: 'celebrations_title', icon: 'fas fa-cake-candles', position: 3 },
  { key: 'calendarMemories', title: 'memories_title', icon: 'fas fa-clock-rotate-left', position: 4 },
];

/** Whether fof/forum-widgets-core is enabled, so it places the widgets instead of the sidebar. */
export function hasFofWidgets(): boolean {
  return typeof flarum !== 'undefined' && !!flarum?.extensions && 'fof-forum-widgets-core' in flarum.extensions;
}

/**
 * Optional fof/forum-widgets-core integration. When that extension is enabled,
 * each calendar widget registers as its own managed widget, so admins place
 * them independently with the widget manager — events at the top, the pulse at
 * the bottom, one of them not at all. Without it, nothing changes (the stock
 * sidebar mounts in forum/index.ts keep working). Resolved through flarum.reg
 * at runtime — no build-time dependency, so the bundle never breaks when the
 * core is absent.
 *
 * Registered from BOTH frontends (the widget manager's admin UI lists widgets
 * by their class metadata); the admin side passes no content renderers.
 */
export default function registerFofWidget(app: any, render: Partial<Record<FofWidgetKey, () => any>> = {}): void {
  if (!hasFofWidgets()) return;

  const widgetsMod = flarum.reg?.get?.('fof-forum-widgets-core', 'common/extend/Widgets');
  const widgetMod = flarum.reg?.get?.('fof-forum-widgets-core', 'common/components/Widget');
  const Widgets = widgetsMod?.default ?? widgetsMod;
  const WidgetBase = (widgetMod?.default ?? widgetMod) as { new (...args: any[]): any } | undefined;
  if (!Widgets || !WidgetBase) return;

  const widgets = new Widgets();

  WIDGETS.forEach(({ key, title, icon, position }) => {
    class CalendarWidget extends WidgetBase {
      className() {
        // Flat: each component draws its own card + heading, so skip the
        // widget-core container chrome to avoid a double header.
        return 'CalendarFofWidget FofWidgets-Widget--flat';
      }

      header() {
        // The component renders its own heading — the chrome title on top of
        // it read as a duplicate.
        return null;
      }

      icon() {
        return icon;
      }

      title() {
        return app.translator.trans('ernestdefoe-calendar.forum.' + title);
      }

      content() {
        return render[key] ? render[key]!() : null;
      }
    }

    widgets.add({
      key,
      component: CalendarWidget,
      // How the tile reads in the widget manager's layout editor, in versions
      // that support it; older ones ignore these and label every tile with the
      // extension's name. A callback, so only the editor ever translates it.
      title: () => app.translator.trans('ernestdefoe-calendar.lib.widgets.' + key),
      icon,
      isDisabled: false,
      isUnique: true,
      placement: 'end',
      position,
    });
  });

  widgets.extend(app, 'ernestdefoe-calendar');
}
