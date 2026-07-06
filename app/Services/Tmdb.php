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

        if (! $response->ok()) {
            return null;
        }

        return $this->fillFromEnglish("/tv/{$tmdbId}", $response->json(), ['name', 'overview']);
    }

    /**
     * Episodi di una stagione. I campi testuali senza traduzione italiana
     * (tipicamente titolo e trama dell'episodio) vengono presi in inglese.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSeasonEpisodes(int $tmdbId, int $seasonNumber): array
    {
        $response = $this->client()->get("/tv/{$tmdbId}/season/{$seasonNumber}");
        if (! $response->ok()) {
            return [];
        }

        $episodes = (array) ($response->json('episodes') ?? []);

        $missing = collect($episodes)->contains(
            fn (array $e): bool => $this->blankField($e, 'name') || $this->blankField($e, 'overview')
        );
        if (! $missing) {
            return $episodes;
        }

        $en = $this->client()->get("/tv/{$tmdbId}/season/{$seasonNumber}", ['language' => 'en-US']);
        if (! $en->ok()) {
            return $episodes;
        }
        $enByNumber = collect((array) ($en->json('episodes') ?? []))->keyBy('episode_number');

        return collect($episodes)->map(function (array $e) use ($enByNumber): array {
            $enEp = $enByNumber->get($e['episode_number'] ?? null);
            if (! is_array($enEp)) {
                return $e;
            }
            foreach (['name', 'overview'] as $field) {
                if ($this->blankField($e, $field) && ! $this->blankField($enEp, $field)) {
                    $e[$field] = $enEp[$field];
                }
            }

            return $e;
        })->all();
    }

    /**
     * Riempie i campi testuali vuoti (traduzione italiana assente) con la
     * versione inglese, con una sola chiamata extra solo quando serve.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function fillFromEnglish(string $path, array $data, array $fields): array
    {
        $missing = array_values(array_filter($fields, fn (string $f): bool => $this->blankField($data, $f)));
        if ($missing === []) {
            return $data;
        }

        $en = $this->client()->get($path, ['language' => 'en-US']);
        if (! $en->ok()) {
            return $data;
        }
        $enData = $en->json();

        foreach ($missing as $field) {
            if (! $this->blankField($enData, $field)) {
                $data[$field] = $enData[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function blankField(array $data, string $field): bool
    {
        return trim((string) ($data[$field] ?? '')) === '';
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

        if (! $response->ok()) {
            return null;
        }

        return $this->fillFromEnglish("/movie/{$tmdbId}", $response->json(), ['title', 'overview']);
    }

    /**
     * Piattaforme streaming (flatrate) per la regione e link JustWatch.
     *
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    public function movieProviders(int $tmdbId, string $region = 'IT'): array
    {
        return $this->providers('movie', $tmdbId, $region);
    }

    /**
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    public function showProviders(int $tmdbId, string $region = 'IT'): array
    {
        return $this->providers('tv', $tmdbId, $region);
    }

    /**
     * URL YouTube del trailer del film, con fallback su lingua e tipo di video.
     */
    public function movieTrailer(int $tmdbId): ?string
    {
        return $this->trailer('movie', $tmdbId);
    }

    public function showTrailer(int $tmdbId): ?string
    {
        return $this->trailer('tv', $tmdbId);
    }

    /**
     * @param  'movie'|'tv'  $type
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    private function providers(string $type, int $tmdbId, string $region): array
    {
        $response = $this->client()->get("/{$type}/{$tmdbId}/watch/providers");
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
     * @param  'movie'|'tv'  $type
     */
    private function trailer(string $type, int $tmdbId): ?string
    {
        foreach (['it-IT', 'en-US'] as $language) {
            $response = $this->client()->get("/{$type}/{$tmdbId}/videos", ['language' => $language]);
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
