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
     * Cerca un film per titolo. Preferisce i risultati il cui titolo (o titolo
     * originale) coincide con la query, così un omonimo oscuro con l'anno più
     * vicino non scavalca il film giusto; tra i candidati sceglie l'anno più
     * vicino, perché le date importate da TV Time sono spesso sfasate di 1-2
     * anni. Senza corrispondenza esatta ripiega sull'ordine di rilevanza TMDB.
     *
     * @return array<string, mixed>|null
     */
    public function searchMovie(string $title, ?int $year = null): ?array
    {
        $results = $this->searchMovies($title);

        if ($results === []) {
            return null;
        }

        $normalize = fn (string $value): string => (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
        $query = $normalize($title);

        $exact = array_values(array_filter($results, fn (array $r): bool => $normalize((string) ($r['title'] ?? '')) === $query
            || $normalize((string) ($r['original_title'] ?? '')) === $query));

        // Senza match di titolo e senza anno, l'ordine di rilevanza TMDB è la scelta migliore.
        if ($exact === [] && $year === null) {
            return $results[0];
        }

        $pool = $exact !== [] ? $exact : $results;

        if ($year === null) {
            return $pool[0];
        }

        usort($pool, function (array $a, array $b) use ($year): int {
            $ya = (int) substr((string) ($a['release_date'] ?? ''), 0, 4);
            $yb = (int) substr((string) ($b['release_date'] ?? ''), 0, 4);

            return abs($ya - $year) <=> abs($yb - $year);
        });

        return $pool[0];
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

    /**
     * Piattaforme streaming (flatrate) per la regione e link JustWatch.
     *
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    public function movieProviders(int $tmdbId, string $region = 'IT'): array
    {
        $response = $this->client()->get("/movie/{$tmdbId}/watch/providers");
        $data = $response->ok() ? ($response->json("results.{$region}") ?? []) : [];

        return [
            'link' => $data['link'] ?? null,
            'flatrate' => array_map(fn (array $p): array => [
                'name' => (string) $p['provider_name'],
                'logo_path' => $p['logo_path'] ?? null,
            ], $data['flatrate'] ?? []),
        ];
    }

    /**
     * URL YouTube del trailer del film, con fallback su lingua e tipo di video.
     */
    public function movieTrailer(int $tmdbId): ?string
    {
        foreach (['it-IT', 'en-US'] as $language) {
            $response = $this->client()->get("/movie/{$tmdbId}/videos", ['language' => $language]);
            $videos = collect((array) ($response->ok() ? ($response->json('results') ?? []) : []))
                ->where('site', 'YouTube');

            $trailer = $videos->firstWhere('type', 'Trailer') ?? $videos->first();

            if ($trailer !== null) {
                return 'https://www.youtube.com/watch?v='.$trailer['key'];
            }
        }

        return null;
    }

    /**
     * Serie di tendenza della settimana.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trendingShows(): array
    {
        $response = $this->client()->get('/trending/tv/week');

        return $response->ok() ? ($response->json('results') ?? []) : [];
    }

    /**
     * Film di tendenza della settimana.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trendingMovies(): array
    {
        $response = $this->client()->get('/trending/movie/week');

        return $response->ok() ? ($response->json('results') ?? []) : [];
    }
}
