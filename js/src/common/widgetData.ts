/**
 * Module-level store for what the calendar's widgets fetch.
 *
 * 🚨 A widget must not own its data. Bespoke re-creates its widgets on every
 * redraw, so a component that fetches in `oninit` and calls `m.redraw()` when
 * the answer lands is mounted again by that redraw, fetches again, redraws
 * again — a request loop, measured at ~80 requests in seconds on another
 * widget. The same widget placed twice, or the index sidebar re-rendered on
 * every return to the page, re-fetched too.
 *
 * So the data lives here, keyed by what was asked: one request per key per
 * TTL, shared by every mount, and a component that finds a fresh value reads
 * it synchronously in `oninit` — no request and no redraw, which is what ends
 * the loop. A failure is stored as the caller's fallback for the same TTL, so
 * an erroring endpoint is asked once, not on every remount.
 */

const TTL = 5 * 60 * 1000;

interface Entry {
  at: number;
  value?: unknown;
  promise?: Promise<unknown>;
}

const store = new Map<string, Entry>();

/** The stored value for `key` if it is still fresh, else undefined. */
export function peek<T>(key: string): T | undefined {
  const entry = store.get(key);

  if (!entry || !('value' in entry) || Date.now() - entry.at > TTL) return undefined;

  return entry.value as T;
}

/** The value for `key`, fetching at most once per TTL however many ask. */
export function load<T>(key: string, fetch: () => Promise<T>, fallback: T): Promise<T> {
  const fresh = peek<T>(key);
  if (fresh !== undefined) return Promise.resolve(fresh);

  const pending = store.get(key)?.promise;
  if (pending && !('value' in (store.get(key) as Entry))) return pending as Promise<T>;

  const promise = fetch()
    .catch(() => fallback)
    .then((value) => {
      store.set(key, { at: Date.now(), value });
      return value;
    });

  store.set(key, { at: Date.now(), promise });

  return promise;
}

/** Forget every stored value whose key starts with `prefix` (after a write). */
export function invalidate(prefix: string): void {
  Array.from(store.keys()).forEach((key) => {
    if (key.startsWith(prefix)) store.delete(key);
  });
}

/**
 * The usual widget wiring: fill `target[prop]` now if the value is fresh,
 * otherwise once it arrives (then redraw).
 */
export function bind<T>(target: any, prop: string, key: string, fetch: () => Promise<T>, fallback: T): void {
  const fresh = peek<T>(key);

  if (fresh !== undefined) {
    target[prop] = fresh;
    return;
  }

  target[prop] = null;

  load(key, fetch, fallback).then((value) => {
    target[prop] = value;
    m.redraw();
  });
}

declare const m: any;
