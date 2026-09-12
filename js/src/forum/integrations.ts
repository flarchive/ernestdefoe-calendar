import app from 'flarum/forum/app';
import UpcomingEvents from './components/UpcomingEvents';
import PulseWidget from './components/PulseWidget';
import OnThisDayWidget from './components/OnThisDayWidget';
import CelebrationsWidget from './components/CelebrationsWidget';

declare const m: any;

/**
 * Register the calendar's placeable widgets ("Upcoming events" + "Forum pulse")
 * with sibling extensions when present. Each integration is feature-detected and
 * guarded, so this is a no-op on a stock forum (where index.ts mounts them).
 */
export function registerIntegrations(): void {
  registerBespoke();
  registerPageBuilder();
}

/** ernestdefoe/bespoke — register as a placeable theme widget. */
function registerBespoke(): void {
  try {
    const bespoke = (app as any).bespoke;
    if (!bespoke?.widgets?.add) return;

    bespoke.widgets.add({
      type: 'events',
      label: 'widget_events', // bespoke resolves forum.<label>; key contributed via our locale
      icon: '📅',
      zones: ['hero', 'above-list', 'sidebar', 'below-list', 'footer'],
      schema: [
        { key: 'title', type: 'text', label: 'Title', default: 'Upcoming events' },
        { key: 'count', type: 'number', label: 'How many', default: 5 },
        { key: 'category', type: 'text', label: 'Category slug (optional)', default: '' },
      ],
      component: {
        view: (v: any) =>
          m(UpcomingEvents, {
            title: v.attrs.settings.title,
            count: v.attrs.settings.count,
            category: v.attrs.settings.category,
          }),
      },
    });

    bespoke.widgets.add({
      type: 'pulse',
      label: 'widget_pulse',
      icon: '📈',
      zones: ['hero', 'above-list', 'sidebar', 'below-list', 'footer'],
      schema: [
        { key: 'title', type: 'text', label: 'Title', default: 'Forum pulse' },
        { key: 'count', type: 'number', label: 'Leaderboard size', default: 5 },
        { key: 'weeks', type: 'number', label: 'Weeks of activity', default: 14 },
      ],
      component: {
        view: (v: any) =>
          m(PulseWidget, { title: v.attrs.settings.title, count: v.attrs.settings.count, weeks: v.attrs.settings.weeks }),
      },
    });

    bespoke.widgets.add({
      type: 'memories',
      label: 'widget_memories',
      icon: '🕰️',
      zones: ['hero', 'above-list', 'sidebar', 'below-list', 'footer'],
      schema: [
        { key: 'title', type: 'text', label: 'Title', default: 'On this day' },
        { key: 'count', type: 'number', label: 'How many', default: 6 },
      ],
      component: { view: (v: any) => m(OnThisDayWidget, { title: v.attrs.settings.title, count: v.attrs.settings.count }) },
    });

    bespoke.widgets.add({
      type: 'celebrations',
      label: 'widget_celebrations',
      icon: '🎉',
      zones: ['hero', 'above-list', 'sidebar', 'below-list', 'footer'],
      schema: [{ key: 'title', type: 'text', label: 'Title', default: 'Celebrations' }],
      component: { view: (v: any) => m(CelebrationsWidget, { title: v.attrs.settings.title }) },
    });
  } catch (e) {
    console.warn('[calendar] bespoke widget registration failed', e);
  }
}

/**
 * ernestdefoe/page-builder — register the block's RENDER component so a placed
 * "events" block renders (it self-fetches, ignoring PB's server data). Appearing
 * in PB's editor palette additionally needs a server-manifest entry on PB's side
 * (a small coordinated addition there).
 */
function registerPageBuilder(): void {
  try {
    const pb = (app as any).pageBuilder;
    if (!pb?.registerBlock) return;

    pb.registerBlock('events', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(UpcomingEvents, { title: s.title, count: s.count || 5, category: s.category });
      },
    });

    pb.registerBlock('pulse', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(PulseWidget, { title: s.title, count: s.count || 5, weeks: s.weeks });
      },
    });

    pb.registerBlock('memories', {
      view: (v: any) => { const s = v.attrs.settings || {}; return m(OnThisDayWidget, { title: s.title, count: s.count || 6 }); },
    });

    pb.registerBlock('celebrations', {
      view: (v: any) => { const s = v.attrs.settings || {}; return m(CelebrationsWidget, { title: s.title }); },
    });
  } catch (e) {
    console.warn('[calendar] page-builder block registration failed', e);
  }
}
