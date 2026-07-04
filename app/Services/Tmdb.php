<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Tmdb
{
    private const BASE = 'https://api.themoviedb.org/3';

    private const LANGUAGE = 'it-IT';

    public function __construct(private readonly ?string $token = null) {}

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            ->withToken($this->token ?? (string) config('services.tmdb.token'))
            ->withQueryParameters(['language' => self::LANGUAGE])
            ->acceptJson()
            ->retry(2, 200);
    }

    /**
     * Risolve un ID TheTVDB nel corrispondente record serie TMDB.
     *
     * @return array<string, mixed>|null
     */
    public function findByTvdbId(int $tvdbId): ?array
    {
        $response = $this->client()->get("/find/{$tvdbId}", ['external_source' => 'tvdb_id']);

        return $response->ok() ? ($response->json('tv_results.0') ?: null) : null;
    }

    /**
     * Dettaglio serie TMDB (include number_of_episodes, status, seasons).
     *
     * @return array<string, mixed>|null
     */
    public function getShow(int $tmdbId): ?array
    {
        $response = $this->client()->get("/tv/{$tmdbId}");

        return $response->ok() ? $response->json() : null;
    }

    /**
     * Episodi di una stagione.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSeasonEpisodes(int $tmdbId, int $seasonNumber): array
    {
        $response = $this->client()->get("/tv/{$tmdbId}/season/{$seasonNumber}");

        return $response->ok() ? ($response->json('episodes') ?? []) : [];
    }

    /**
     * Cerca un film per titolo (e anno se disponibile), restituisce il primo risultato.
     *
     * @return array<string, mixed>|null
     */
    public function searchMovie(string $title, ?int $year = null): ?array
    {
        $response = $this->client()->get('/search/movie', array_filter([
            'query' => $title,
            'primary_release_year' => $year,
        ]));

        return $response->ok() ? ($response->json('results.0') ?: null) : null;
    }

    /**
     * Cerca serie per titolo (tutti i risultati).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchShows(string $query): array
    {
        $response = $this->client()->get('/search/tv', ['query' => $query]);

        return $response->ok() ? ($response->json('results') ?? []) : [];
    }

    /**
     * Cerca film per titolo (tutti i risultati).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchMovies(string $query): array
    {
        $response = $this->client()->get('/search/movie', ['query' => $query]);

        return $response->ok() ? ($response->json('results') ?? []) : [];
    }

    /**
     * Dettaglio film TMDB (include runtime).
     *
     * @return array<string, mixed>|null
     */
    public function getMovie(int $tmdbId): ?array
    {
        $response = $this->client()->get("/movie/{$tmdbId}");

        return $response->ok() ? $response->json() : null;
    }
}
