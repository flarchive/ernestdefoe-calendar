<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Http\SlugManager;
use Flarum\User\User;

/**
 * The fields Flarum itself sends for a user shown beside content — enough for
 * the frontend to turn a row into a real User model and draw it with core's
 * Avatar component, the way every other avatar on the forum is drawn.
 *
 * 🚨 The widgets used to draw their own avatar markup from avatarUrl alone.
 * Anything a forum does to avatars through core's component — a default
 * avatar, frames, srcset — never reached them, so the pulse leaderboard showed
 * different faces from the rest of the forum. Reported by jslirola on
 * discuss.flarum.org/d/39377/43.
 */
class BasicUser
{
    public static function of(User $user): array
    {
        return [
            'username' => (string) $user->username,
            'displayName' => (string) $user->display_name,
            'avatarUrl' => $user->avatar_url,
            'avatarSrcset' => $user->avatar_srcset,
            'slug' => resolve(SlugManager::class)->forResource(User::class)->toSlug($user),
        ];
    }
}
