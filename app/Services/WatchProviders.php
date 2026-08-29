<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Models\Show;
use Closure;

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
