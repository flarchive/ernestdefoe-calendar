declare const flarum: any;

/**
 * Optional fof/forum-widgets-core integration. When that extension is enabled,
 * "Upcoming events" registers as a managed widget so admins place it with the
 * widget manager; without it, nothing changes (the stock sidebar mount in
 * forum/index.ts keeps working). Resolved through flarum.reg at runtime — no
 * build-time dependency, so the bundle never breaks when the core is absent.
 *
 * Registered from BOTH frontends (the widget manager's admin UI lists widgets
 * by their class metadata); the admin side passes no content renderer.
 */
export default function registerFofWidget(app: any, makeContent?: () => any): void {
  if (!flarum?.extensions || !('fof-forum-widgets-core' in flarum.extensions)) return;

  const widgetsMod = flarum.reg?.get?.('fof-forum-widgets-core', 'common/extend/Widgets');
  const widgetMod = flarum.reg?.get?.('fof-forum-widgets-core', 'common/components/Widget');
  const Widgets = widgetsMod?.default ?? widgetsMod;
  const WidgetBase = (widgetMod?.default ?? widgetMod) as { new (...args: any[]): any } | undefined;
  if (!Widgets || !WidgetBase) return;

  class CalendarUpcomingWidget extends WidgetBase {
    className() {
      // Flat: UpcomingEvents draws its own card + heading, so skip the
      // widget-core container chrome to avoid a double header.
      return 'CalendarFofWidget FofWidgets-Widget--flat';
    }

    header() {
      // The component renders its own heading (with a Calendar link) — the
      // chrome title on top of it read as a duplicate.
      return null;
    }

    icon() {
      return 'fas fa-calendar-days';
    }

    title() {
      return app.translator.trans('ernestdefoe-calendar.forum.widget_title');
    }

    content() {
      return makeContent ? makeContent() : null;
    }
  }

  new Widgets()
    .add({
      key: 'calendarUpcoming',
      component: CalendarUpcomingWidget,
      isDisabled: false,
      isUnique: true,
      placement: 'end',
      position: 1,
    })
    .extend(app, 'ernestdefoe-calendar');
}
