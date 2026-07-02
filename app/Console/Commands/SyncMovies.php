<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Movie;
use App\Services\Tmdb;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('movies:sync {--all : Risincronizza anche i film già collegati a TMDB}')]
#[Description('Arricchisce i film con dati TMDB: poster, trama e stato (match per titolo+anno)')]
class SyncMovies extends Command
{
    public function handle(Tmdb $tmdb): int
    {
        if (blank(config('services.tmdb.token'))) {
            $this->error('TMDB_TOKEN mancante in .env — prendi il token v4 su themoviedb.org.');

            return self::FAILURE;
        }

        $query = Movie::query();
        if (! $this->option('all')) {
            $query->whereNull('tmdb_id');
        }
        $movies = $query->get();

        if ($movies->isEmpty()) {
            $this->info('Nessun film da sincronizzare.');

            return self::SUCCESS;
        }

        $counters = ['synced' => 0, 'unresolved' => 0];
        $bar = $this->output->createProgressBar($movies->count());
        $bar->start();

        foreach ($movies as $movie) {
            $this->syncMovie($tmdb, $movie, $counters);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Film sincronizzati', 'Non risolti su TMDB'],
            [[$counters['synced'], $counters['unresolved']]],
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function syncMovie(Tmdb $tmdb, Movie $movie, array &$counters): void
    {
        $year = $movie->release_date?->year;
        $match = $tmdb->searchMovie($movie->title, $year);

        if (! $match) {
            $counters['unresolved']++;

            return;
        }

        $movie->fill([
            'tmdb_id' => $match['id'] ?? null,
            'title' => $match['title'] ?? $movie->title,
            'poster_path' => $match['poster_path'] ?? $movie->poster_path,
            'overview' => $match['overview'] ?? $movie->overview,
            'release_date' => ($match['release_date'] ?? '') ?: $movie->release_date,
        ])->save();
        $counters['synced']++;
    }
}
