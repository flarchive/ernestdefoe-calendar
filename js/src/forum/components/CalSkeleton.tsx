import Component from 'flarum/common/Component';

declare const m: any;

/**
 * Remembered heights for the calendar's loading states.
 *
 * 🚨 A skeleton only holds the layout still if it is the right SIZE, and the
 * right size is a property of THIS forum: how many weeks the month spans, how
 * many people have a birthday today, how long the leaderboard is. None of that
 * is knowable before the response, so each surface remembers what it rendered
 * last time and reserves exactly that.
 *
 * Heights rather than item counts, deliberately: the month grid is 5 rows in
 * some months and 6 in others, and a count of "rows" would have to model that.
 * A remembered height cannot drift from what the page actually draws.
 *
 * Access is wrapped because a browser in private mode, or one told to block
 * site data, throws on read rather than returning null.
 */
const KEY = 'ernestdefoe-calendar.h.';

export function remember(surface: string, px: number): void {
  // A collapsed or hidden render is not worth learning from — it would train
  // the skeleton to reserve nothing.
  if (px < 20) return;

  try {
    localStorage.setItem(KEY + surface, String(Math.round(px)));
  } catch {
    // Storage unavailable; the fallback below is used instead.
  }
}

function recalled(surface: string, fallback: number): number {
  try {
    const px = Number(localStorage.getItem(KEY + surface));

    // Cap it: a stale or hand-edited value would otherwise reserve screens of
    // empty page, which is worse than the fallback.
    return Number.isFinite(px) && px >= 20 && px <= 8000 ? px : fallback;
  } catch {
    return fallback;
  }
}

/** Hook a rendered element up to the memory, on its own lifecycle. */
export function measure(surface: string) {
  // 🚨 On the element's lifecycle, not after the fetch: a
  // requestAnimationFrame there races Mithril's redraw and can run while the
  // element still does not exist, storing nothing at all.
  const record = (v: any) => remember(surface, v.dom.getBoundingClientRect().height);

  return { oncreate: record, onupdate: record };
}

/**
 * The month grid while it loads.
 *
 * The header above it renders immediately — it needs no data — so only the grid
 * itself is stood in for. Fallback is 663px: a six-week month at the measured
 * 76px minimum cell height plus the weekday strip.
 */
export default class CalSkeleton extends Component {
  view() {
    return m('.CalendarGrid.CalSkeleton', {
      style: { height: recalled('grid', 663) + 'px' },
      'aria-hidden': 'true',
    });
  }
}

/**
 * The three sidebar widgets.
 *
 * 🚨 The stylesheet already described every one of these — `.CalHeat--loading`
 * with `.CalHeat-bar`, `.CalPulse-loading` with `.CalPulse-skel`,
 * `.CalCeleb-loading` with `.CalCeleb-skel`, all three with the cal-pulse
 * animation. None of them were ever rendered: the components showed a spinner
 * instead, so the skeleton CSS had been sitting in the file doing nothing.
 * These render what was already designed.
 */
export class CalWidgetSkeleton extends Component {
  view(vnode: any) {
    const { block, bar, rows, fallback } = vnode.attrs as {
      block: string;
      bar: string;
      rows: number;
      fallback: number;
    };

    return m(
      `.${block}-loading.CalSkeleton`,
      { style: { minHeight: recalled(block, fallback) + 'px' }, 'aria-hidden': 'true' },
      Array.from({ length: rows }, (_, i) => m(`.${bar}`, { key: i }))
    );
  }
}
