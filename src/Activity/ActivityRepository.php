<?php

namespace ErnestDefoe\Calendar\Activity;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;

/**
 * Aggregates forum activity into the day-bucketed shapes the heatmap, streaks,
 * pulse and leaderboard need. The signal is comment posts (which covers both
 * replies AND new discussions, since a discussion's opening post is a comment) —
 * the one universal "this member showed up today" measure every forum has.
 *
 * Days are bucketed by the DB's DATE() (UTC on a standard install). Queries are
 * grouped/aggregated in SQL so even a busy forum only ships small rollups.
 */
class ActivityRepository
{
    public function __construct(protected ConnectionInterface $db) {}

    /** Daily comment counts for one user over the last $days days, keyed 'Y-m-d'. */
    public function userDaily(int $userId, int $days = 365): array
    {
        return $this->daily($days, fn ($q) => $q->where('user_id', $userId));
    }

    /** Daily comment counts across the whole forum over the last $days days. */
    public function forumDaily(int $days = 120): array
    {
        return $this->daily($days, fn ($q) => $q->whereNotNull('user_id'));
    }

    /** Top contributors (by comment count) over the last $days days. */
    public function leaders(int $days = 30, int $limit = 10): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        return $this->db->table('posts')
            ->selectRaw('user_id, COUNT(*) as c')
            ->where('type', 'comment')
            ->whereNotNull('user_id')
            ->where('is_private', false)
            ->whereNull('hidden_at')
            ->where('created_at', '>=', $from)
            ->groupBy('user_id')
            ->orderByDesc('c')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['userId' => (int) $r->user_id, 'count' => (int) $r->c])
            ->all();
    }

    /**
     * Current + longest streak (consecutive days with ≥1 comment) from a daily
     * map. The current streak counts back from today; if there's nothing yet
     * today it counts back from yesterday, so the streak stays "alive" until a
     * whole day passes with no activity.
     */
    public function streak(array $daily): array
    {
        $set = [];
        foreach ($daily as $d => $c) {
            if ($c > 0) $set[$d] = true;
        }

        // Current: walk backwards from today (or yesterday if today is empty).
        $current = 0;
        $cursor = Carbon::now()->startOfDay();
        if (empty($set[$cursor->toDateString()])) {
            $cursor = $cursor->subDay();
        }
        while (! empty($set[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        // Longest: measure each run from its start (a day whose predecessor is empty).
        $longest = 0;
        foreach (array_keys($set) as $d) {
            $prev = Carbon::parse($d)->subDay()->toDateString();
            if (! empty($set[$prev])) continue; // not the start of a run
            $len = 0;
            $c = Carbon::parse($d);
            while (! empty($set[$c->toDateString()])) {
                $len++;
                $c = $c->addDay();
            }
            $longest = max($longest, $len);
        }

        return ['current' => $current, 'longest' => max($longest, $current)];
    }

    private function daily(int $days, callable $scope): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $q = $this->db->table('posts')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('type', 'comment')
            ->where('is_private', false)
            ->whereNull('hidden_at')
            ->where('created_at', '>=', $from)
            ->groupBy('d');

        $scope($q);

        $out = [];
        foreach ($q->get() as $row) {
            $out[(string) $row->d] = (int) $row->c;
        }
        return $out;
    }
}
