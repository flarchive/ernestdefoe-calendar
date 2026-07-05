import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import PostsUserPage from 'flarum/forum/components/PostsUserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import FieldSet from 'flarum/common/components/FieldSet';
import CalendarPage from './components/CalendarPage';
import UpcomingEvents from './components/UpcomingEvents';
import ActivityHeatmap from './components/ActivityHeatmap';
import PulseWidget from './components/PulseWidget';
import OnThisDayWidget from './components/OnThisDayWidget';
import CelebrationsWidget from './components/CelebrationsWidget';
import BirthdayField from './components/BirthdayField';
import { registerIntegrations } from './integrations';
import registerFofWidget from '../common/registerFofWidget';
import { startCountdowns } from './countdowns';

declare const m: any;
declare const flarum: any;

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
  // the nav dropdown itself). Admins can hide it — the widgets link to /calendar
  // too, so some forums prefer an uncluttered nav.
  extend(IndexSidebar.prototype, 'navItems', function (items: any) {
    if (app.forum.attribute('ernestdefoe-calendar.hideNavLink')) return;
    items.add(
      'calendar',
      LinkButton.component(
        { href: app.route('calendar'), icon: 'fas fa-calendar-days' },
        app.translator.trans('ernestdefoe-calendar.forum.nav')
      ),
      5
    );
  });

  // With fof/forum-widgets-core enabled, Upcoming Events becomes a managed
  // widget (registered below) — the admin controls its placement there, so the
  // hardcoded sidebar mount steps aside to avoid showing the list twice.
  const hasWidgetCore = typeof flarum !== 'undefined' && flarum?.extensions && 'fof-forum-widgets-core' in flarum.extensions;

  // Stock-theme widgets, each its own sidebar block below the navigation.
  extend(IndexSidebar.prototype, 'items', function (items: any) {
    if (!hasWidgetCore && app.forum.attribute('ernestdefoe-calendar.showIndexWidget')) {
      const count = app.forum.attribute('ernestdefoe-calendar.indexWidgetCount') || 5;
      items.add('calendar-upcoming', m(UpcomingEvents, { count }), -10);
    }
    if (app.forum.attribute('ernestdefoe-calendar.showPulseWidget')) {
      items.add('calendar-pulse', m(PulseWidget, { count: 5 }), -12);
    }
    if (app.forum.attribute('ernestdefoe-calendar.showCelebrationsWidget')) {
      items.add('calendar-celebrations', m(CelebrationsWidget), -13);
    }
    if (app.forum.attribute('ernestdefoe-calendar.showMemoriesWidget')) {
      items.add('calendar-memories', m(OnThisDayWidget, { count: 6 }), -14);
    }
  });

  // Self-service birthday picker in the account settings page (opt-in, MM-DD only).
  // SettingsPage isn't an exported runtime module, so reach it via the registered
  // route component; guarded so a core change can never break boot.
  const SettingsPage = (app.routes.settings as any) && (app.routes.settings as any).component;
  if (SettingsPage && SettingsPage.prototype) {
    extend(SettingsPage.prototype, 'settingsItems', function (items: any) {
      items.add(
        'calendarBirthday',
        FieldSet.component({ className: 'Settings-calendarBirthday', label: app.translator.trans('ernestdefoe-calendar.forum.birthday_section') }, [m(BirthdayField)]),
        5
      );
    });
  }

  // The marquee feature: a contribution heatmap + streaks at the top of every
  // member's profile. override() lets us prepend above the activity feed.
  override(PostsUserPage.prototype, 'content', function (this: any, original: any) {
    const heatmap = this.user ? m('.PostsUserPage-heatmap', m(ActivityHeatmap, { userId: this.user.id() })) : null;
    return [heatmap, original()];
  });

  // fof/forum-widgets-core (optional): register Upcoming Events as a managed
  // widget. Its modules live in the main bundle, so this is safe at init time.
  registerFofWidget(app, () =>
    m(UpcomingEvents, { count: app.forum.attribute('ernestdefoe-calendar.indexWidgetCount') || 5 })
  );

  // Defer so sibling extensions (Bespoke / Page Builder) have run their own
  // initializers and created app.bespoke / app.pageBuilder before we register our
  // widgets into them — cross-extension initializer order isn't guaranteed, and a
  // macrotask runs after Flarum's synchronous boot completes.
  setTimeout(registerIntegrations, 0);

  // Turn [countdown=…] placeholders (and event-modal countdowns) into live timers.
  startCountdowns();
});

export { default as UpcomingEvents } from './components/UpcomingEvents';
