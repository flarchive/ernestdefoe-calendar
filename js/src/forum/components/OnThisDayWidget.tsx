import app from 'flarum/forum/app';
import { onThisDay, type Memory } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

/**
 * "On this day" — popular threads from today's date in years past. Self-contained
 * POJO so it works on the stock index, a Bespoke widget, or a Page Builder block.
 * attrs: { title, count }.
 */
const OnThisDayWidget = {
  oninit(this: any, vnode: any) {
    this.memories = null as Memory[] | null;
    const count = Math.max(1, Number((vnode.attrs || {}).count) || 6);
    onThisDay(count)
      .then((d) => { this.memories = d; m.redraw(); })
      .catch(() => { this.memories = []; m.redraw(); });
  },

  view(this: any, vnode: any) {
    const a = vnode.attrs || {};
    const title = a.title || t('memories_title');
    const memories: Memory[] | null = this.memories;

    return m('.CalMemories', [
      m('.CalMemories-head', [m('h4.CalMemories-title', ['🕰️ ', title])]),
      memories === null
        ? m('.CalMemories-loading', [m('span.CalMemories-skel'), m('span.CalMemories-skel')])
        : memories.length === 0
        ? m('p.CalMemories-empty', t('memories_empty'))
        : m('ul.CalMemories-list', memories.map((mem) => row(mem))),
    ]);
  },
};

function row(mem: Memory) {
  return m('li.CalMemories-item', [
    m('a.CalMemories-link', { href: app.route('discussion', { id: mem.id + (mem.slug ? '-' + mem.slug : '') }), oncreate: m.route.Link }, mem.title),
    m('span.CalMemories-meta', [
      m('span.CalMemories-years', t('memories_years_ago', { years: mem.yearsAgo })),
      ' · ',
      t('memories_replies', { count: mem.commentCount }),
    ]),
  ]);
}

export default OnThisDayWidget;
