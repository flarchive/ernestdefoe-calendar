import app from 'flarum/common/app';
import type User from 'flarum/common/models/User';

/** The user fields the calendar's endpoints send (see src/BasicUser.php). */
export interface BasicUser {
  username: string;
  displayName: string;
  avatarUrl: string | null;
  avatarSrcset?: string | null;
  slug?: string;
}

/**
 * A real User model for a row the calendar's endpoints returned, so it can be
 * drawn with core's Avatar and linked with app.route.user — exactly like every
 * other user on the forum, including whatever a forum's extensions do to
 * avatars. The store's own copy wins when the forum already has the user
 * loaded; otherwise the basic fields are pushed, the same set Flarum sends for
 * any post author.
 */
export function asUser(id: number, u: BasicUser): User {
  const key = String(id);

  return (
    app.store.getById<User>('users', key) ??
    app.store.pushObject<User>({
      type: 'users',
      id: key,
      attributes: {
        username: u.username,
        displayName: u.displayName,
        avatarUrl: u.avatarUrl,
        avatarSrcset: u.avatarSrcset ?? null,
        // A response cached before slugs were sent has none; the username is
        // the default slug driver's value.
        slug: u.slug ?? u.username,
      },
    })!
  );
}
