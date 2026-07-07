<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Carbon;

/**
 * Risolve o crea un Movie convergendo su tmdb_id quando risolvibile (via
 * imdb_id o titolo+anno). Così l'import CSV (che identifica per tvtime_uuid) e
 * l'import JSON dell'estensione (che identifica per imdb_id) si fondono sullo
 * stesso record invece di duplicarlo. Merge arricchente: non sovrascrive campi
 * già valorizzati con un null.
 */
class MovieMatcher
{
    public function __construct(private readonly Tmdb $tmdb) {}

    /**
     * @param  array{tmdb_id?: int|null, imdb_id?: string|null, tvtime_uuid?: string|null, title?: string|null, year?: int|null}  $ids
     * @param  array<string, mixed>  $extra  attributi aggiuntivi (es. runtime dal CSV)
     */
    public function resolve(array $ids, array $extra = []): Movie
    {
        $tmdbId = $ids['tmdb_id'] ?? null;
        $found = null;

        if ($tmdbId === null && ! empty($ids['imdb_id'])) {
            $found = rescue(fn () => $this->tmdb->findByImdbId((string) $ids['imdb_id']), null, report: false);
            $tmdbId = $found['id'] ?? null;
        }
        if ($tmdbId === null && ! empty($ids['title'])) {
            $found = rescue(fn () => $this->tmdb->searchMovie((string) $ids['title'], $ids['year'] ?? null), null, report: false);
            $tmdbId = $found['id'] ?? null;
        }

        $match = match (true) {
            $tmdbId !== null => ['tmdb_id' => $tmdbId],
            ! empty($ids['imdb_id']) => ['imdb_id' => $ids['imdb_id']],
            ! empty($ids['tvtime_uuid']) => ['tvtime_uuid' => $ids['tvtime_uuid']],
            default => ['title' => (string) ($ids['title'] ?? 'Film')],
        };

        $values = array_filter([
            'tmdb_id' => $tmdbId,
            'imdb_id' => $ids['imdb_id'] ?? null,
            'tvtime_uuid' => $ids['tvtime_uuid'] ?? null,
            'title' => $found['title'] ?? $ids['title'] ?? null,
            'release_date' => $this->releaseDate($found, $ids['year'] ?? null),
            'poster_path' => $found['poster_path'] ?? null,
            'overview' => $found['overview'] ?? null,
        ], fn ($v) => $v !== null);

        return Movie::updateOrCreate($match, [...$values, ...array_filter($extra, fn ($v) => $v !== null)]);
    }

    /**
     * @param  array<string, mixed>|null  $found
     */
    private function releaseDate(?array $found, ?int $year): ?Carbon
    {
        if (! empty($found['release_date'])) {
            return rescue(fn () => Carbon::parse((string) $found['release_date']), null, report: false);
        }

        return $year ? Carbon::createFromDate($year, 1, 1)->startOfDay() : null;
    }
}
