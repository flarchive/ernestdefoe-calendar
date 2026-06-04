import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import CalendarPage from './components/CalendarPage';
import UpcomingEvents from './components/UpcomingEvents';
import { registerIntegrations } from './integrations';

declare const m: any;

/**
 * Forum entry point.
 *  - Registers the /calendar route + a nav link.
 *  - On stock Flarum, mounts the "Upcoming events" widget in the index sidebar
 *    (no theme required). Bespoke / Page Builder get a proper widget/block via
 *    registerIntegrations().
 */
app.initializers.add('ernestdefoe/calendar', () => {
  app.routes.calendar = { path: '/calendar', component: CalendarPage };

  // Nav link sits with "All Discussions" in the sidebar navigation (Flarum 2
  // exposes these via navItems, not items — items is the New Discussion button +
  // the nav dropdown itself).
  extend(IndexSidebar.prototype, 'navItems', function (items: any) {
    items.add(
      'calendar',
      LinkButton.component(
        { href: app.route('calendar'), icon: 'fas fa-calendar-days' },
        app.translator.trans('ernestdefoe-calendar.forum.nav')
      ),
      5
    );
  });

  // Stock-theme upcoming-events widget, mounted as its own sidebar block below
  // the navigation.
  extend(IndexSidebar.prototype, 'items', function (items: any) {
    if (app.forum.attribute('ernestdefoe-calendar.showIndexWidget')) {
      const count = app.forum.attribute('ernestdefoe-calendar.indexWidgetCount') || 5;
      items.add('calendar-upcoming', m(UpcomingEvents, { count }), -10);
    }
  });

  registerIntegrations();
});

export { default as UpcomingEvents } from './components/UpcomingEvents';
