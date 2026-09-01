import type { CalEvent } from './api';
import app from 'flarum/common/app';

/**
 * The locale to format dates and times in.
 *
 * 🚨 Flarum's locale, NOT the browser's. Every call here used to pass
 * `undefined`, which means "whatever the visitor's browser is set to" — so a
 * German forum read by somebody whose browser is en-US showed
 * "Aug 31 – Sep 6, 2026", "MON"/"TUE" and "1 AM", while every translated string
 * around it was in German. Reported by ClaudiusH.
 *
 * Passing the forum's locale also fixes the 24-hour clock for free: `hour:
 * 'numeric'` is 12-hour in en and 24-hour in de, because that is what those
 * locales mean. There is nothing to configure.
 *
 * Falls back to `undefined` (the old behaviour) if the locale cannot be read,
 * so this can never be worse than it was.
 */
function loc(): string | undefined {
  try {
    const l = (app as any)?.data?.locale || (app as any)?.translator?.locale;

    return typeof l === 'string' && l ? l : undefined;
  } catch (e) {
    return undefined;
  }
}

/** A 6×7 grid of Dates covering the month, padded to whole weeks. */
export function monthMatrix(year: number, month: number, weekStartsOn = 0): Date[] {
  const first = new Date(year, month, 1);
  const start = new Date(first);
  const shift = (first.getDay() - weekStartsOn + 7) % 7;
  start.setDate(first.getDate() - shift);

  const days: Date[] = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    days.push(d);
  }
  return days;
}

export function sameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

export function isToday(d: Date): boolean {
  return sameDay(d, new Date());
}

/** Localised weekday short names, ordered from the configured first day. */
export function weekdayNames(weekStartsOn = 0): string[] {
  // 🚨 This used to be a hardcoded English array, under a docblock that said
  // "localised". Derived from real dates instead: 2023-01-01 was a Sunday, so
  // adding 0..6 days walks Sun→Sat and Intl names them in the forum's locale.
  const base = Array.from({ length: 7 }, (_, i) =>
    new Date(2023, 0, 1 + i).toLocaleDateString(loc(), { weekday: 'short' })
  );

  return [...base.slice(weekStartsOn), ...base.slice(0, weekStartsOn)];
}

export function monthLabel(year: number, month: number): string {
  return new Date(year, month, 1).toLocaleDateString(loc(), { month: 'long', year: 'numeric' });
}

// ---- week / day helpers ----

export function startOfDay(d: Date): Date {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

export function addDays(d: Date, n: number): Date {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
}

/** Midnight of the first day of the week containing `d`. */
export function startOfWeek(d: Date, weekStartsOn = 0): Date {
  const x = startOfDay(d);
  const shift = (x.getDay() - weekStartsOn + 7) % 7;
  x.setDate(x.getDate() - shift);
  return x;
}

/** The 7 Dates of the week containing `d`, ordered from the configured first day. */
export function weekDays(d: Date, weekStartsOn = 0): Date[] {
  const s = startOfWeek(d, weekStartsOn);
  return Array.from({ length: 7 }, (_, i) => addDays(s, i));
}

/** "Mon, June 7, 2026" — full label for the day view. */
export function dayTitle(d: Date): string {
  return d.toLocaleDateString(loc(), { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

/** "Jun 1 – 7, 2026" / "Jun 29 – Jul 5, 2026" — label for the week view. */
export function weekRangeLabel(days: Date[]): string {
  const a = days[0];
  const b = days[6];

  // 🚨 Localising the month NAME is not enough — the ORDER is locale-specific
  // too. Hand-building "Aug 31 – Sep 6, 2026" gives a German reader
  // "Aug. 31 – Sep. 6, 2026" when they should see "31. Aug – 6. Sep 2026".
  // formatRange() knows the right order, the right separator and when to elide
  // a repeated month or year, in every locale. It is ES2021 and present in
  // every browser Flarum 2 supports; the hand-built form stays as a fallback
  // for anything exotic rather than throwing.
  return range(a, b, { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * A date range formatted for the reader's locale, falling back to a plain
 * "a – b" if the engine has no formatRange.
 */
function range(a: Date, b: Date, opts: Intl.DateTimeFormatOptions): string {
  try {
    const fmt: any = new Intl.DateTimeFormat(loc(), opts);
    if (typeof fmt.formatRange === 'function') return fmt.formatRange(a, b);

    return `${fmt.format(a)} – ${fmt.format(b)}`;
  } catch (e) {
    return `${a.toLocaleDateString(loc(), opts)} – ${b.toLocaleDateString(loc(), opts)}`;
  }
}

/** "8 AM", "12 PM" — left-gutter labels for the time grid. */
export function hourLabel(h: number): string {
  return new Date(2000, 0, 1, h).toLocaleTimeString(loc(), { hour: 'numeric' });
}

export interface TimedSegment {
  ev: CalEvent;
  top: number; // % from top of the day (0–100)
  height: number; // % of the day
  col: number; // overlap column index
  cols: number; // total columns in this overlap cluster
}

/**
 * Lay out the timed (non-all-day) events that touch `day` into a set of
 * positioned segments, splitting overlapping events into side-by-side columns.
 */
export function layoutDay(events: CalEvent[], day: Date): TimedSegment[] {
  const dayStart = startOfDay(day).getTime();
  const dayEnd = dayStart + 86400000;

  const segs = events
    .filter((e) => !e.allDay)
    .map((e) => {
      const s = new Date(e.start).getTime();
      const en = e.end ? new Date(e.end).getTime() : s + 3600000; // default 1h
      return { e, s, en };
    })
    .filter((x) => x.en > dayStart && x.s < dayEnd)
    .map((x) => {
      const s = Math.max(x.s, dayStart);
      const en = Math.min(x.en, dayEnd);
      return {
        ev: x.e,
        startMs: s,
        endMs: Math.max(en, s + 1),
        top: ((s - dayStart) / 86400000) * 100,
        height: Math.max(((en - s) / 86400000) * 100, 1.6),
        col: 0,
        cols: 1,
      };
    })
    .sort((a, b) => a.startMs - b.startMs || b.endMs - a.endMs);

  // Group into overlap clusters, then assign columns greedily within each.
  let cluster: typeof segs = [];
  let clusterEnd = -Infinity;
  const flush = () => {
    const colEnds: number[] = [];
    for (const it of cluster) {
      let placed = false;
      for (let c = 0; c < colEnds.length; c++) {
        if (colEnds[c] <= it.startMs) {
          it.col = c;
          colEnds[c] = it.endMs;
          placed = true;
          break;
        }
      }
      if (!placed) {
        it.col = colEnds.length;
        colEnds.push(it.endMs);
      }
    }
    cluster.forEach((it) => (it.cols = colEnds.length));
    cluster = [];
    clusterEnd = -Infinity;
  };

  for (const s of segs) {
    if (cluster.length && s.startMs >= clusterEnd) flush();
    cluster.push(s);
    clusterEnd = Math.max(clusterEnd, s.endMs);
  }
  if (cluster.length) flush();

  return segs.map(({ ev, top, height, col, cols }) => ({ ev, top, height, col, cols }));
}

/** Does an event (with absolute start/end) touch the given calendar day? */
export function eventOnDay(ev: CalEvent, day: Date): boolean {
  const s = new Date(ev.start);
  const e = ev.end ? new Date(ev.end) : s;
  const dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
  const dayEnd = new Date(dayStart);
  dayEnd.setDate(dayEnd.getDate() + 1);
  return s < dayEnd && e >= dayStart;
}

/** Human-friendly date/time range for an event. */
export function formatRange(ev: CalEvent): string {
  const s = new Date(ev.start);
  const e = ev.end ? new Date(ev.end) : null;
  const dOpts: Intl.DateTimeFormatOptions = { weekday: 'short', month: 'short', day: 'numeric' };
  const tOpts: Intl.DateTimeFormatOptions = { hour: 'numeric', minute: '2-digit' };

  if (ev.allDay) {
    // Locale-ordered, for the same reason as weekRangeLabel.
    if (e && !sameDay(s, e)) return range(s, e, dOpts);
    return s.toLocaleDateString(loc(), { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  }

  const date = s.toLocaleDateString(loc(), dOpts);
  const startT = s.toLocaleTimeString(loc(), tOpts);
  if (!e) return `${date}, ${startT}`;
  if (sameDay(s, e)) return `${date}, ${startT} – ${e.toLocaleTimeString(loc(), tOpts)}`;
  return `${date}, ${startT} – ${e.toLocaleDateString(loc(), dOpts)}, ${e.toLocaleTimeString(loc(), tOpts)}`;
}

export function shortTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(loc(), { hour: 'numeric', minute: '2-digit' });
}

/** Format a Date for a datetime-local input value (local wall time). */
export function toLocalInput(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
