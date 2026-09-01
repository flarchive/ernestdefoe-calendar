import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import EventFormModal from './EventFormModal';
import { rsvp, deleteEvent, showEvent, abs, mapUrl, type CalEvent, type RsvpAttendee } from '../../common/api';
import { formatRange } from '../../common/dates';

// Official WoW class colors — used to tint attendee character names when the
// armory extension supplies them. Static reference data; harmless elsewhere.
const CLASS_COLORS: Record<string, string> = {
  'Death Knight': '#C41E3A', 'Demon Hunter': '#A330C9', Druid: '#FF7C0A',
  Evoker: '#33937F', Hunter: '#AAD372', Mage: '#3FC7EB', Monk: '#00FF98',
  Paladin: '#F48CBA', Priest: '#FFFFFF', Rogue: '#FFF468', Shaman: '#0070DD',
  Warlock: '#8788EE', Warrior: '#C69B6D',
};

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

interface DetailAttrs extends IInternalModalAttrs {
  event: CalEvent;
  onchange?: () => void;
}

/** Read view of an event: details, location→map, RSVP, export, edit/delete. */
export default class EventDetailModal extends Modal<DetailAttrs> {
  event!: CalEvent;
  busy = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.event = this.attrs.event;

    // The grid passes a list-endpoint event, which omits the attendee roster.
    // Refresh just the rsvp block from the show endpoint (keeping the rest of
    // the object intact so recurring occurrences keep their expanded dates).
    if (this.event.id) {
      showEvent(this.event.id).then((full) => {
        if (full && full.id === this.event.id && full.rsvp) { this.event.rsvp = full.rsvp; m.redraw(); }
      }).catch(() => {});
    }
  }

  className() { return 'CalendarEventModal CalendarEventDetail Modal--medium'; }
  title() { return this.event.title; }

  content() {
    const ev = this.event;
    const row = (icon: string, content: any) => content ? m('.CalendarDetail-row', [m('i.icon.' + icon), m('span', content)]) : null;

    return m('.Modal-body.CalendarDetail', [
      ev.coverUrl ? m('.CalendarDetail-cover', { style: { backgroundImage: `url("${ev.coverUrl.replace(/"/g, '%22')}")` } }) : null,

      ev.category ? m('span.CalendarBadge', { style: { background: ev.category.color } }, ev.category.name) : null,

      // Live countdown for events that haven't started yet (the global ticker in
      // countdowns.ts animates any .CalCountdown[data-deadline] it finds).
      new Date(ev.start).getTime() > Date.now()
        ? m('.CalCountdown.CalCountdown--event', { 'data-deadline': ev.start }, t('starts_in'))
        : null,

      m('.CalendarDetail-meta', [
        row('fas fa-clock', formatRange(ev) + (ev.recurring ? ' · ' + t('repeat_' + freqWord(ev.rrule)) : '')),
        ev.location
          ? m('.CalendarDetail-row', [m('i.icon.fas.fa-location-dot'), m('a', { href: mapUrl(ev.location), target: '_blank', rel: 'noopener' }, ev.location)])
          : null,
        ev.url && (/^https?:\/\//i.test(ev.url) || (ev.url.startsWith('/') && !ev.url.startsWith('//')))
          ? m('.CalendarDetail-row', [m('i.icon.fas.fa-link'), m('a', { href: ev.url, target: '_blank', rel: 'noopener noreferrer' }, ev.url)])
          : null,
        ev.author ? row('fas fa-user', t('organizer') + ' ' + ev.author.displayName) : null,
      ]),

      ev.description ? m('.CalendarDetail-desc', m.trust(safe(ev.description))) : null,

      // RSVP
      m('.CalendarRsvp', [
        rsvpButton(this, 'going', 'fas fa-check', t('rsvp_going'), ev.rsvp.going),
        rsvpButton(this, 'interested', 'far fa-star', t('rsvp_interested'), ev.rsvp.interested),
      ]),

      // Who's coming — with main-character class/spec/ilvl when armory is installed.
      attendeeGroup(t('attendees_going'), ev.rsvp.attendees?.going),
      attendeeGroup(t('attendees_interested'), ev.rsvp.attendees?.interested),

      // Export + links
      m('.CalendarDetail-actions', [
        m('a.Button.Button--icon', { href: ev.googleUrl, target: '_blank', rel: 'noopener' }, [m('i.Button-icon.fab.fa-google'), m('span.Button-label', t('add_to_google'))]),
        m('a.Button.Button--icon', { href: abs(ev.icalUrl) }, [m('i.Button-icon.fas.fa-download'), m('span.Button-label', t('download_ics'))]),
        ev.discussionId ? m('a.Button.Button--icon', { href: app.route('discussion', { id: ev.discussionId }) }, [m('i.Button-icon.fas.fa-comments'), m('span.Button-label', t('view_discussion'))]) : null,
      ]),

      ev.canEdit
        ? m('.CalendarDetail-manage', [
            Button.component({ className: 'Button Button--link', icon: 'fas fa-pencil', onclick: () => this.edit() }, t('edit_event')),
            Button.component({ className: 'Button Button--link CalendarDetail-delete', icon: 'fas fa-trash', loading: this.busy, onclick: () => this.remove() }, t('delete_event')),
          ])
        : null,
    ]);
  }

  setRsvp(status: string) {
    const next = this.event.rsvp.mine === status ? 'none' : status;
    rsvp(this.event.id, next).then((r) => { this.event.rsvp = r; m.redraw(); }).catch(() => {});
  }

  edit() {
    app.modal.show(EventFormModal, { event: this.event, onsave: (ev: CalEvent) => { this.attrs.onchange?.(); Object.assign(this.event, ev); } });
  }

  remove() {
    if (!confirm(t('delete_confirm') as any)) return;
    this.busy = true;
    deleteEvent(this.event.id).then(() => { this.attrs.onchange?.(); app.modal.close(); }).catch(() => { this.busy = false; m.redraw(); });
  }
}

function attendeeGroup(label: any, attendees?: RsvpAttendee[]) {
  if (!attendees || !attendees.length) return null;
  return m('.CalendarAttendees', [
    m('.CalendarAttendees-label', label),
    m('.CalendarAttendees-list', attendees.map((a) => {
      const c = a.character;
      const color = c?.class ? CLASS_COLORS[c.class] : undefined;
      return m('.CalendarAttendee', { title: a.displayName }, [
        a.avatarUrl
          ? m('img.CalendarAttendee-avatar', { src: a.avatarUrl, alt: '', loading: 'lazy' })
          : m('span.CalendarAttendee-avatar.CalendarAttendee-avatar--letter', a.displayName.charAt(0).toUpperCase()),
        m('.CalendarAttendee-names', [
          m('.CalendarAttendee-user', a.displayName),
          c
            ? m('.CalendarAttendee-char', { style: color ? { color } : undefined }, [
                c.name,
                c.spec ? ' · ' + c.spec : '',
                c.itemLevel ? m('span.CalendarAttendee-ilvl', ' ' + c.itemLevel) : null,
              ])
            : null,
        ]),
      ]);
    })),
  ]);
}

function rsvpButton(modal: EventDetailModal, status: string, icon: string, label: string, count: number) {
  const active = modal.event.rsvp.mine === status;
  return m('button.Button.CalendarRsvp-btn' + (active ? '.is-active' : ''), { type: 'button', onclick: () => modal.setRsvp(status) }, [
    m('i.icon.' + icon), ' ', label, count ? m('span.CalendarRsvp-count', count) : null,
  ]);
}

function freqWord(rrule?: string | null): string {
  const m2 = /FREQ=([A-Z]+)/.exec(rrule || '');
  return m2 ? m2[1].toLowerCase() : 'none';
}

/** The description is plain text from the editor; render it escaped with line breaks. */
function safe(text: string): string {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML.replace(/\n/g, '<br>');
}
