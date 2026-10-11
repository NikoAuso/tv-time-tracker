<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Models\Show;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Piattaforme "flatrate" (Dove guardarlo) di TMDB, persistite sul catalogo:
 * la colonna providers fa da cache (12h) ed è la sorgente del filtro per piattaforma.
 */
class WatchProviders
{
    public function __construct(private readonly Tmdb $tmdb) {}

    /**
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    public function forMovie(Movie $movie, bool $force = false): array
    {
        return $this->sync($movie, fn (): array => $this->tmdb->movieProviders((int) $movie->tmdb_id), $force);
    }

    /**
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    public function forShow(Show $show, bool $force = false): array
    {
        return $this->sync($show, fn (): array => $this->tmdb->showProviders((int) $show->tmdb_id), $force);
    }

    /**
     * Riscarica in parallelo, ignorando la cache, le piattaforme delle serie indicate.
     *
     * @param  Collection<int, Show>  $shows
     */
    public function refreshShows(Collection $shows): void
    {
        $this->refresh('tv', $shows);
    }

    /**
     * Riscarica in parallelo, ignorando la cache, le piattaforme dei film indicati.
     *
     * @param  Collection<int, Movie>  $movies
     */
    public function refreshMovies(Collection $movies): void
    {
        $this->refresh('movie', $movies);
    }

    /**
     * @param  'movie'|'tv'  $type
     * @param  Collection<int, Movie>|Collection<int, Show>  $models
     */
    private function refresh(string $type, Collection $models): void
    {
        $models = $models->filter(fn (Movie|Show $model): bool => (bool) $model->tmdb_id)->keyBy('id');

        $providers = $this->tmdb->manyProviders($type, $models->map(fn (Movie|Show $model): int => (int) $model->tmdb_id)->all());

        // Una sola transazione: su SQLite un commit per riga rallenta parecchio.
        DB::transaction(function () use ($models, $providers): void {
            foreach ($providers as $id => $data) {
                $models[$id]->update(['providers' => $data, 'providers_synced_at' => now()]);
            }
        });
    }

    /**
     * @param  Closure(): array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}  $fetch
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    private function sync(Movie|Show $model, Closure $fetch, bool $force = false): array
    {
        if (! $model->tmdb_id) {
            return ['link' => null, 'flatrate' => []];
        }

        if (! $force && $model->providers !== null && $model->providers_synced_at?->gt(now()->subHours(12))) {
            return $model->providers;
        }

        $data = rescue($fetch, ['link' => null, 'flatrate' => []], report: false);

        $model->update(['providers' => $data, 'providers_synced_at' => now()]);

        return $data;
    }
}
