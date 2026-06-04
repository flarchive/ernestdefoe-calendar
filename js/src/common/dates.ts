import type { CalEvent } from './api';

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
  const base = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  return [...base.slice(weekStartsOn), ...base.slice(0, weekStartsOn)];
}

export function monthLabel(year: number, month: number): string {
  return new Date(year, month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
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
  return d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

/** "Jun 1 – 7, 2026" / "Jun 29 – Jul 5, 2026" — label for the week view. */
export function weekRangeLabel(days: Date[]): string {
  const a = days[0];
  const b = days[6];
  const aMon = a.toLocaleDateString(undefined, { month: 'short' });
  const bMon = b.toLocaleDateString(undefined, { month: 'short' });
  return aMon === bMon
    ? `${aMon} ${a.getDate()} – ${b.getDate()}, ${b.getFullYear()}`
    : `${aMon} ${a.getDate()} – ${bMon} ${b.getDate()}, ${b.getFullYear()}`;
}

/** "8 AM", "12 PM" — left-gutter labels for the time grid. */
export function hourLabel(h: number): string {
  return new Date(2000, 0, 1, h).toLocaleTimeString(undefined, { hour: 'numeric' });
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
    if (e && !sameDay(s, e)) return `${s.toLocaleDateString(undefined, dOpts)} – ${e.toLocaleDateString(undefined, dOpts)}`;
    return s.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  }

  const date = s.toLocaleDateString(undefined, dOpts);
  const startT = s.toLocaleTimeString(undefined, tOpts);
  if (!e) return `${date}, ${startT}`;
  if (sameDay(s, e)) return `${date}, ${startT} – ${e.toLocaleTimeString(undefined, tOpts)}`;
  return `${date}, ${startT} – ${e.toLocaleDateString(undefined, dOpts)}, ${e.toLocaleTimeString(undefined, tOpts)}`;
}

export function shortTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/** Format a Date for a datetime-local input value (local wall time). */
export function toLocalInput(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
