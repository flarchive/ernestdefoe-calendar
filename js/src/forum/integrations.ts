import app from 'flarum/forum/app';
import UpcomingEvents from './components/UpcomingEvents';

declare const m: any;

/**
 * Register the "Upcoming events" widget with sibling extensions when present.
 * Each integration is feature-detected and guarded, so this is a no-op on a
 * stock forum (where the index-sidebar mount in index.ts already covers it).
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
  } catch (e) {
    console.warn('[calendar] page-builder block registration failed', e);
  }
}
