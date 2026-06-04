import app from 'flarum/forum/app';

/** A serialized event (mirrors ErnestDefoe\Calendar\Api\EventSerializer). */
export interface CalEvent {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  start: string;
  end?: string | null;
  allDay: boolean;
  timezone: string;
  location?: string | null;
  url?: string | null;
  coverUrl?: string | null;
  rrule?: string | null;
  recurring: boolean;
  occurrence?: string | null;
  category?: { id: number; name: string; color: string } | null;
  author?: { id: number; username: string; displayName: string; avatarUrl: string } | null;
  discussionId?: number | null;
  rsvp: { going: number; interested: number; mine: string | null };
  canEdit: boolean;
  icalUrl: string;
  googleUrl: string;
}

export interface CalCategory { id: number; name: string; slug: string; color: string }

const api = (path: string) => app.forum.attribute('apiUrl') + path;
export const base = () => app.forum.attribute<string>('baseUrl');

/** Absolute URL for a server path the serializer handed us (e.g. an .ics path). */
export const abs = (path: string) => base().replace(/\/$/, '') + '/' + path.replace(/^\//, '');
export const feedUrl = () => abs('calendar/feed.ics');
export const mapUrl = (location: string) =>
  'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(location);

export function listEvents(from: Date, to: Date, category?: string): Promise<{ data: CalEvent[]; categories: CalCategory[] }> {
  const qs = new URLSearchParams({ from: from.toISOString(), to: to.toISOString() });
  if (category) qs.set('category', category);
  return app.request<any>({ method: 'GET', url: api('/calendar/events?' + qs.toString()) });
}

export function showEvent(id: number): Promise<CalEvent> {
  return app.request<any>({ method: 'GET', url: api('/calendar/events/' + id) }).then((r: any) => r.data);
}

export function saveEvent(attrs: Record<string, unknown>, id?: number): Promise<CalEvent> {
  return app
    .request<any>({ method: id ? 'PATCH' : 'POST', url: api('/calendar/events' + (id ? '/' + id : '')), body: { data: { attributes: attrs } } })
    .then((r: any) => r.data);
}

export function deleteEvent(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: api('/calendar/events/' + id) });
}

export function rsvp(id: number, status: string): Promise<CalEvent['rsvp']> {
  return app.request<any>({ method: 'POST', url: api('/calendar/events/' + id + '/rsvp'), body: { data: { status } } }).then((r: any) => r.data);
}

export function listCategories(): Promise<CalCategory[]> {
  return app.request<any>({ method: 'GET', url: api('/calendar/categories') }).then((r: any) => r.data);
}

export function saveCategory(attrs: Record<string, unknown>, id?: number): Promise<CalCategory> {
  return app
    .request<any>({ method: id ? 'PATCH' : 'POST', url: api('/calendar/categories' + (id ? '/' + id : '')), body: { data: { attributes: attrs } } })
    .then((r: any) => r.data);
}

export function deleteCategory(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: api('/calendar/categories/' + id) });
}
