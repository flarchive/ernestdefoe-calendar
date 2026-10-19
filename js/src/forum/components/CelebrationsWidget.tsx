import app from 'flarum/forum/app';
import { CalWidgetSkeleton, measure } from './CalSkeleton';
import { celebrations, type Celebrant } from '../../common/api';
import { bind } from '../../common/widgetData';
import { asUser } from '../../common/users';
import Avatar from 'flarum/common/components/Avatar';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

/**
 * "Today's celebrations" — opt-in birthdays + join-anniversaries. Self-contained
 * POJO for the stock index, a Bespoke widget, or a Page Builder block.
 * attrs: { title }.
 */
const CelebrationsWidget = {
  oninit(this: any) {
    bind<Celebrant[]>(this, 'people', 'celebrations', () => celebrations(), []);
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('celebrations_title');
    const people: Celebrant[] | null = this.people;

    // Hide the widget entirely when there's nothing to celebrate (no empty box).
    if (people && people.length === 0) return null;

    return m('.CalCeleb', measure('CalCeleb'), [
      m('.CalCeleb-head', [m('h4.CalCeleb-title', ['🎉 ', title])]),
      people === null
        ? m(CalWidgetSkeleton, { block: 'CalCeleb', bar: 'CalCeleb-skel', rows: 3, fallback: 118 })
        : m(
            'ul.CalCeleb-list',
            people.map((p) => row(p))
          ),
    ]);
  },
};

function row(p: Celebrant) {
  const emoji = p.type === 'birthday' ? '🎂' : '🎈';
  const note = p.type === 'birthday' ? t('celebrations_birthday') : t('celebrations_anniversary', { years: p.years });

  const user = asUser(p.userId, p);

  return m('li.CalCeleb-item', [
    m('span.CalCeleb-emoji', emoji),
    m('a.CalCeleb-user', { href: app.route.user(user), oncreate: m.route.Link }, [
      m(Avatar, { user, className: 'CalCeleb-avatar notranslate' }),
      m('span.CalCeleb-name', user.displayName()),
    ]),
    m('span.CalCeleb-note', note),
  ]);
}

export default CelebrationsWidget;
