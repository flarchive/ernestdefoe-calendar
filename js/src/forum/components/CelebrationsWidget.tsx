import app from 'flarum/forum/app';
import { celebrations, type Celebrant } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

/**
 * "Today's celebrations" — opt-in birthdays + join-anniversaries. Self-contained
 * POJO for the stock index, a Bespoke widget, or a Page Builder block.
 * attrs: { title }.
 */
const CelebrationsWidget = {
  oninit(this: any) {
    this.people = null as Celebrant[] | null;
    celebrations()
      .then((d) => { this.people = d; m.redraw(); })
      .catch(() => { this.people = []; m.redraw(); });
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('celebrations_title');
    const people: Celebrant[] | null = this.people;

    // Hide the widget entirely when there's nothing to celebrate (no empty box).
    if (people && people.length === 0) return null;

    return m('.CalCeleb', [
      m('.CalCeleb-head', [m('h4.CalCeleb-title', ['🎉 ', title])]),
      people === null
        ? m('.CalCeleb-loading', m('span.CalCeleb-skel'))
        : m('ul.CalCeleb-list', people.map((p) => row(p))),
    ]);
  },
};

function row(p: Celebrant) {
  const emoji = p.type === 'birthday' ? '🎂' : '🎈';
  const note = p.type === 'birthday'
    ? t('celebrations_birthday')
    : t('celebrations_anniversary', { years: p.years });

  return m('li.CalCeleb-item', [
    m('span.CalCeleb-emoji', emoji),
    m('a.CalCeleb-user', { href: app.route('user', { username: p.username }), oncreate: m.route.Link }, [
      avatar(p),
      m('span.CalCeleb-name', p.displayName || p.username),
    ]),
    m('span.CalCeleb-note', note),
  ]);
}

function avatar(p: Celebrant) {
  if (p.avatarUrl) return m('span.Avatar.CalCeleb-avatar', { style: { backgroundImage: `url("${p.avatarUrl}")` } });
  const ch = (p.displayName || p.username || '?').charAt(0).toUpperCase();
  return m('span.Avatar.CalCeleb-avatar.CalCeleb-avatar--text', ch);
}

export default CelebrationsWidget;
