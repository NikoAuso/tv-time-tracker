<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\UserList;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\DB;

class UserData
{
    public const APP = 'tv-time-tracker';

    public const VERSION = 1;

    /**
     * Esporta tutti i dati dell'utente in una struttura JSON-friendly.
     *
     * @return array<string, mixed>
     */
    public static function export(int $userId): array
    {
        $watchedByShow = DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', $userId)
            ->select(
                'episodes.show_id',
                'episodes.season_number',
                'episodes.episode_number',
                'episodes.tmdb_id',
                'episodes.name',
                'episodes.runtime',
                'episodes.air_date',
                'watched_episodes.watched_at',
            )
            ->orderBy('episodes.season_number')
            ->orderBy('episodes.episode_number')
            ->get()
            ->groupBy('show_id');

        $shows = UserShow::where('user_id', $userId)->with('show')->get()->map(fn (UserShow $us) => [
            'tmdb_id' => $us->show->tmdb_id,
            'name' => $us->show->name,
            'poster_path' => $us->show->poster_path,
            'first_air_date' => $us->show->first_air_date?->toDateString(),
            'total_episodes' => $us->show->total_episodes,
            'air_status' => $us->show->status,
            'status' => $us->status,
            'is_favorite' => $us->is_favorite,
            'rating' => $us->rating,
            'followed_at' => $us->followed_at?->toIso8601String(),
            'watched_episodes' => ($watchedByShow[$us->show_id] ?? collect())->map(fn ($e) => [
                'season' => (int) $e->season_number,
                'episode' => (int) $e->episode_number,
                'tmdb_id' => $e->tmdb_id,
                'name' => $e->name,
                'runtime' => $e->runtime,
                'air_date' => $e->air_date,
                'watched_at' => $e->watched_at,
            ])->values()->all(),
        ])->values()->all();

        $movies = UserMovie::where('user_id', $userId)->with('movie')->get()->map(fn (UserMovie $um) => [
            'tmdb_id' => $um->movie->tmdb_id,
            'title' => $um->movie->title,
            'poster_path' => $um->movie->poster_path,
            'release_date' => $um->movie->release_date?->toDateString(),
            'runtime' => $um->movie->runtime,
            'status' => $um->status,
            'is_favorite' => $um->is_favorite,
            'rating' => $um->rating,
            'rewatch_count' => $um->rewatch_count,
            'watched_at' => $um->watched_at?->toIso8601String(),
        ])->values()->all();

        $lists = UserList::where('user_id', $userId)->with('shows', 'movies')->get()->map(fn (UserList $l) => [
            'name' => $l->name,
            'shows' => $l->shows->pluck('tmdb_id')->filter()->values()->all(),
            'movies' => $l->movies->pluck('tmdb_id')->filter()->values()->all(),
        ])->values()->all();

        return [
            'app' => self::APP,
            'version' => self::VERSION,
            'shows' => $shows,
            'movies' => $movies,
            'lists' => $lists,
        ];
    }

    /**
     * Importa (merge, mai duplicati) i dati di un export TvTimeTracker.
     *
     * @param  array<string, mixed>  $data
     */
    public static function import(int $userId, array $data): void
    {
        DB::transaction(function () use ($userId, $data) {
            $showByTmdb = [];
            $movieByTmdb = [];

            foreach ($data['shows'] ?? [] as $s) {
                $show = self::resolveShow($s);
                $showByTmdb[$s['tmdb_id'] ?? null] = $show->id;

                UserShow::updateOrCreate(
                    ['user_id' => $userId, 'show_id' => $show->id],
                    self::withoutNulls([
                        'status' => $s['status'] ?? 'watchlist',
                        'is_favorite' => $s['is_favorite'] ?? false,
                        'rating' => $s['rating'] ?? null,
                        'followed_at' => $s['followed_at'] ?? null,
                    ]),
                );

                foreach ($s['watched_episodes'] ?? [] as $e) {
                    $episode = Episode::updateOrCreate(
                        ['show_id' => $show->id, 'season_number' => (int) $e['season'], 'episode_number' => (int) $e['episode']],
                        self::withoutNulls([
                            'tmdb_id' => $e['tmdb_id'] ?? null,
                            'name' => $e['name'] ?? null,
                            'runtime' => $e['runtime'] ?? null,
                            'air_date' => $e['air_date'] ?? null,
                        ]),
                    );

                    WatchedEpisode::firstOrCreate(
                        ['user_id' => $userId, 'episode_id' => $episode->id],
                        ['watched_at' => $e['watched_at'] ?? now()],
                    );
                }
            }

            foreach ($data['movies'] ?? [] as $m) {
                $movie = self::resolveMovie($m);
                $movieByTmdb[$m['tmdb_id'] ?? null] = $movie->id;

                UserMovie::updateOrCreate(
                    ['user_id' => $userId, 'movie_id' => $movie->id],
                    self::withoutNulls([
                        'status' => $m['status'] ?? 'watchlist',
                        'is_favorite' => $m['is_favorite'] ?? false,
                        'rating' => $m['rating'] ?? null,
                        'rewatch_count' => $m['rewatch_count'] ?? 0,
                        'watched_at' => $m['watched_at'] ?? null,
                    ]),
                );
            }

            foreach ($data['lists'] ?? [] as $l) {
                $list = UserList::firstOrCreate(['user_id' => $userId, 'name' => $l['name']]);

                $showIds = collect((array) ($l['shows'] ?? []))->map(fn ($t) => $showByTmdb[$t] ?? Show::where('tmdb_id', $t)->value('id'))->filter()->all();
                $movieIds = collect((array) ($l['movies'] ?? []))->map(fn ($t) => $movieByTmdb[$t] ?? Movie::where('tmdb_id', $t)->value('id'))->filter()->all();

                $list->shows()->syncWithoutDetaching($showIds);
                $list->movies()->syncWithoutDetaching($movieIds);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private static function resolveShow(array $s): Show
    {
        $attrs = self::withoutNulls([
            'name' => $s['name'] ?? null,
            'poster_path' => $s['poster_path'] ?? null,
            'first_air_date' => $s['first_air_date'] ?? null,
            'total_episodes' => $s['total_episodes'] ?? null,
            'status' => $s['air_status'] ?? null,
        ]);

        if (! empty($s['tmdb_id'])) {
            return Show::updateOrCreate(['tmdb_id' => $s['tmdb_id']], $attrs);
        }

        return Show::firstOrCreate(['name' => $s['name'] ?? ''], $attrs);
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private static function resolveMovie(array $m): Movie
    {
        $attrs = self::withoutNulls([
            'title' => $m['title'] ?? null,
            'poster_path' => $m['poster_path'] ?? null,
            'release_date' => $m['release_date'] ?? null,
            'runtime' => $m['runtime'] ?? null,
        ]);

        if (! empty($m['tmdb_id'])) {
            return Movie::updateOrCreate(['tmdb_id' => $m['tmdb_id']], $attrs);
        }

        return Movie::firstOrCreate(['title' => $m['title'] ?? ''], $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $attrs): array
    {
        return array_filter($attrs, fn ($v) => $v !== null);
    }
}
