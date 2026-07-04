<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\UserMovie;
use App\Models\UserShow;

/**
 * Aggiunge alla libreria dell'utente una serie o un film trovato su TMDB,
 * scaricandone i dati (per le serie anche l'elenco episodi).
 */
class TmdbLibrary
{
    public function __construct(private readonly Tmdb $tmdb) {}

    public function addShow(int $tmdbId, int $userId): ?Show
    {
        $data = $this->tmdb->getShow($tmdbId);
        if ($data === null) {
            return null;
        }

        $show = Show::updateOrCreate(
            ['tmdb_id' => $tmdbId],
            [
                'name' => $data['name'] ?? '',
                'poster_path' => $data['poster_path'] ?? null,
                'overview' => $data['overview'] ?? null,
                'first_air_date' => ($data['first_air_date'] ?? '') ?: null,
                'total_episodes' => $data['number_of_episodes'] ?? null,
                'status' => $data['status'] ?? null,
            ],
        );

        foreach ($data['seasons'] ?? [] as $season) {
            $seasonNumber = $season['season_number'] ?? null;
            if ($seasonNumber === null) {
                continue;
            }

            foreach ($this->tmdb->getSeasonEpisodes($tmdbId, (int) $seasonNumber) as $episode) {
                if (! isset($episode['episode_number'])) {
                    continue;
                }

                Episode::updateOrCreate(
                    [
                        'show_id' => $show->id,
                        'season_number' => (int) $seasonNumber,
                        'episode_number' => (int) $episode['episode_number'],
                    ],
                    [
                        'tmdb_id' => $episode['id'] ?? null,
                        'name' => $episode['name'] ?? null,
                        'overview' => $episode['overview'] ?? null,
                        'still_path' => $episode['still_path'] ?? null,
                        'air_date' => ($episode['air_date'] ?? '') ?: null,
                        'runtime' => $episode['runtime'] ?? null,
                    ],
                );
            }
        }

        UserShow::firstOrCreate(['user_id' => $userId, 'show_id' => $show->id], ['status' => 'watchlist']);

        return $show;
    }

    public function addMovie(int $tmdbId, int $userId): ?Movie
    {
        $data = $this->tmdb->getMovie($tmdbId);
        if ($data === null) {
            return null;
        }

        $movie = Movie::updateOrCreate(
            ['tmdb_id' => $tmdbId],
            [
                'title' => $data['title'] ?? '',
                'poster_path' => $data['poster_path'] ?? null,
                'overview' => $data['overview'] ?? null,
                'release_date' => ($data['release_date'] ?? '') ?: null,
                'runtime' => $data['runtime'] ?? null,
            ],
        );

        UserMovie::firstOrCreate(['user_id' => $userId, 'movie_id' => $movie->id], ['status' => 'watchlist']);

        return $movie;
    }
}
