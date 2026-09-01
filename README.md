# Calendar & Events for Flarum

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/ernestdefoe/calendar/blob/main/LICENSE)
[![Latest version](https://img.shields.io/github/v/tag/ernestdefoe/calendar?label=release)](https://github.com/ernestdefoe/calendar/releases)
[![Flarum](https://img.shields.io/badge/Flarum-%5E2.0-orange.svg)](https://flarum.org)

A full-featured, **free** calendar & events extension for **Flarum 2** — and an
**engagement engine** that makes your whole community feel alive. Events (month/
week/day/list, RSVPs, recurrence, iCal + Google Calendar) **plus** a GitHub-style
activity heatmap with streaks, a forum-pulse leaderboard, live countdowns in any
post, "on this day" memories, and member birthday/anniversary celebrations.

Everything works on the **default theme** and integrates natively with
[Bespoke](https://github.com/ernestdefoe/bespoke) and
[Page Builder](https://github.com/ernestdefoe/page-builder).

> Built to feel premium. Priced at free.

---

## ✨ Engagement engine (the reason to install even if you don't need a calendar)

- 🔥 **Activity heatmap + streaks** — every member profile gets a GitHub-style
  year-long contribution graph with current/longest **streaks**, totals, and
  earned **achievement badges**. The single best driver of daily return visits.
- 📈 **Forum Pulse** — a community-wide activity heatmap + "most active"
  leaderboard widget that shows, at a glance, that the place is buzzing.
- ⏳ **Live countdowns** — drop `[countdown=2026-12-31T23:59:59]New Year[/countdown]`
  into *any* post for a live ticking timer; upcoming events get one automatically.
- 🕰️ **On this day** — resurfaces the most-discussed threads from today's date in
  years past, reviving old conversations.
- 🎂 **Member celebrations** — opt-in **birthdays** (month/day only — never a year)
  and join-**anniversaries**, shown in a "today's celebrations" widget.

Each appears on the default theme **and** as a Bespoke widget / Page Builder block,
with admin toggles for the index widgets.

## Calendar & events features

- 🗓️ **Month, Week, Day & list** views with quick prev / today / next navigation.
  Week/Day are true time grids with hour lines, a live "now" indicator, side-by-side
  overlapping events, and double-click-to-create at the clicked time slot.
- 📍 **Location** on every event, rendered as a one-tap **Google Maps** link.
- 🔁 **Recurring events** (daily / weekly / monthly / yearly) via standard `RRULE`,
  expanded on the fly so each occurrence shows on the right day.
- ✅ **RSVP** — members mark themselves *Going* or *Interested*, with live counts.
- 🎨 **Categories with colours** — filter the calendar and give each event a
  coloured accent. Managed from a dedicated admin panel with a colour picker.
- 🖼️ **Cover image** per event — paste a URL, or **upload a file** directly when
  [FoF Upload](https://github.com/FriendsOfFlarum/upload) is installed (auto-detected;
  falls back to a URL field otherwise).
- 📅 **iCal export** — a subscribable `feed.ics` for the whole calendar plus a
  per-event `.ics` download (RFC 5545, proper escaping & folding).
- 🟦 **Google Calendar** — "Add to Google Calendar" deep-links with title, time,
  description and location pre-filled.
- 💬 **Optional discussion thread** per event (hybrid model) so members can
  comment using Flarum's native discussion UI.
- 🧩 **Upcoming Events widget** — works standalone on the **default theme**, and
  registers as a first-class widget/block in **Bespoke** and **Page Builder**.
- 🌓 **Theme-aware** — every colour resolves through Flarum's own CSS variables,
  so it looks correct in light & dark and on custom themes out of the box.
- 🔐 **Permissions** — separate "create events" and "manage all events" controls.

## Installation

```bash
composer require ernestdefoe/calendar
php flarum migrate
php flarum cache:clear
```

Then open **Admin → Calendar** to configure it.

## Updating

```bash
composer update ernestdefoe/calendar
php flarum migrate
php flarum cache:clear
```

## Configuration

**Admin → Calendar** gives you:

| Setting | What it does |
| --- | --- |
| **Default view** | Month grid or upcoming list when the calendar opens. |
| **Week starts on** | Sunday or Monday. |
| **Index widget** | Show an "Upcoming events" widget on the forum index. |
| **Widget count** | How many upcoming events the widget lists. |
| **Link discussion** | Auto-create a discussion thread for each new event (for comments). |
| **Categories** | Add / rename / recolour / delete colour-coded categories. |

Two permissions are added under **Admin → Permissions**:

- **Create calendar events** — who can add events.
- **Manage all calendar events** — who can edit/delete events they don't own.

## The Upcoming Events widget

The widget is a self-contained component, so it shows up automatically wherever
your theme supports it:

- **Default theme** — appears in the index sidebar (toggle in admin).
- **Bespoke** — available in the widget tray as **Upcoming events**.
- **Page Builder** — available as an **Events** block.

## Calendar subscription (iCal)

Every calendar exposes a subscribable feed at:

```
https://your-forum.example/calendar/feed.ics
```

Paste that URL into Google Calendar (*Other calendars → From URL*), Apple
Calendar (*File → New Calendar Subscription*), or Outlook to keep events in sync.
Each event also has its own `.ics` download and an "Add to Google Calendar" link.

## Creating events from outside Flarum (REST API)

Every screen in the calendar is built on public HTTP endpoints under `/api`, so
anything that can talk to your forum can create events — a cron job, a Discord
bot, a game server, another site. There is no separate integration to install.

Authenticate the way you would with any Flarum endpoint: a
[master API key](https://docs.flarum.org/extend/api/) sent as
`Authorization: Token <key>; userId=<id>`. The acting user needs the
**Create calendar events** permission.

```bash
curl -X POST https://your-forum.example/api/calendar/events \
  -H 'Authorization: Token YOUR_API_KEY; userId=1' \
  -H 'Content-Type: application/json' \
  -d '{
        "data": {
          "attributes": {
            "title":    "Raid night",
            "start":    "2026-10-04T19:00:00Z",
            "end":      "2026-10-04T22:00:00Z",
            "allDay":   false,
            "timezone": "Europe/Berlin",
            "location": "Discord",
            "description": "Bring consumables."
          }
        }
      }'
```

`start` and `end` are ISO-8601 and are **stored in UTC** — send an offset (or a
trailing `Z`) rather than a bare local time, or the server has to guess. For an
**all-day** event send `allDay: true` with the date's UTC midnight
(`2026-10-04T00:00:00Z`); that is what keeps a birthday on the 4th for a reader in
Auckland as well as in Los Angeles. `timezone` is the *display* timezone and does
not change what is stored.

The rest of the surface:

| Method   | Route                             | Purpose                          |
| -------- | --------------------------------- | -------------------------------- |
| `GET`    | `/api/calendar/events`            | List (accepts `from` / `to`)     |
| `GET`    | `/api/calendar/events/{id}`       | One event                        |
| `POST`   | `/api/calendar/events`            | Create                           |
| `PATCH`  | `/api/calendar/events/{id}`       | Update                           |
| `DELETE` | `/api/calendar/events/{id}`       | Delete                           |
| `POST`   | `/api/calendar/events/{id}/rsvp`  | Set the actor's RSVP             |
| `GET`    | `/api/calendar/categories`        | List categories                  |

Requests take the JSON:API envelope (`{"data": {"attributes": {…}}}`); responses
come back as `{"data": {…}}` with the event flattened into one object — id, slug,
resolved category, author, RSVP counts and the export links — rather than as
JSON:API `included` relationships you have to stitch together.

A rejected save is a `422` carrying `errors[]` with a `source.pointer` per field,
the same shape Flarum uses everywhere else, so existing error handling works
unchanged.

## How events are modelled

Events are their own records (title, time, location, recurrence, etc.). When
**Link discussion** is enabled, creating an event also spins up a normal Flarum
discussion linked to it, so comments, mentions, likes and notifications all work
exactly as members expect — no parallel comment system to learn.

## Compatibility

- Flarum `^2.0`
- PHP `^8.3`
- No required third-party extensions. Plays nicely with Bespoke & Page Builder
  when present, and degrades gracefully when they aren't.
- **Optional:** [FoF Upload](https://github.com/FriendsOfFlarum/upload) — enables
  drag-free file uploads for event cover images.

## Contributing

Issues and PRs welcome. To build the front-end locally:

```bash
cd js
npm install
npm run dev      # watch
npm run build    # production
```

## License

[MIT](LICENSE) © ernestdefoe
