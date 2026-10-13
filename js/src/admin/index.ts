import app from 'flarum/admin/app';
import registerFofWidget from '../common/registerFofWidget';

/**
 * Admin entry. Scalar settings, permissions, and the custom category manager
 * are registered declaratively in ./extend — the Flarum 2 way. The one
 * imperative piece is the optional fof/forum-widgets-core registration, which
 * the widget manager's admin UI needs so the Upcoming Events widget is
 * listed and placeable (no content is rendered on this frontend).
 */
app.initializers.add('ernestdefoe/calendar-widgets', () => {
  registerFofWidget(app);
});

export { default as extend } from './extend';
