<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Show;
use App\Services\Tmdb;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shows:sync {--all : Risincronizza anche le serie già collegate a TMDB}')]
#[Description('Arricchisce le serie con dati TMDB: poster, elenco episodi, runtime e stato')]
class SyncShows extends Command
{
    public function handle(Tmdb $tmdb): int
    {
        if (blank(config('services.tmdb.token'))) {
            $this->error('TMDB_TOKEN mancante in .env — prendi il token v4 su themoviedb.org.');

            return self::FAILURE;
        }

        $query = Show::query()->whereNotNull('tvdb_id');
        if (! $this->option('all')) {
            $query->whereNull('tmdb_id');
        }
        $shows = $query->get();

        if ($shows->isEmpty()) {
            $this->info('Nessuna serie da sincronizzare.');

            return self::SUCCESS;
        }

        $counters = ['synced' => 0, 'episodes' => 0, 'unresolved' => 0];
        $bar = $this->output->createProgressBar($shows->count());
        $bar->start();

        foreach ($shows as $show) {
            $this->syncShow($tmdb, $show, $counters);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Serie sincronizzate', 'Episodi', 'Non risolte su TMDB'],
            [[$counters['synced'], $counters['episodes'], $counters['unresolved']]],
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function syncShow(Tmdb $tmdb, Show $show, array &$counters): void
    {
        if (! $show->tmdb_id) {
            $found = $tmdb->findByTvdbId((int) $show->tvdb_id);
            if (! $found) {
                $counters['unresolved']++;

                return;
            }
            $show->tmdb_id = $found['id'];
        }

        $data = $tmdb->getShow((int) $show->tmdb_id);
        if (! $data) {
            $counters['unresolved']++;

            return;
        }

        $show->fill([
            'name' => $data['name'] ?? $show->name,
            'poster_path' => $data['poster_path'] ?? $show->poster_path,
            'overview' => $data['overview'] ?? $show->overview,
            'first_air_date' => ($data['first_air_date'] ?? '') ?: null,
            'total_episodes' => $data['number_of_episodes'] ?? null,
            'status' => $data['status'] ?? null,
            'genres' => array_column($data['genres'] ?? [], 'name'),
        ])->save();
        $counters['synced']++;

        foreach ($data['seasons'] ?? [] as $season) {
            $seasonNumber = $season['season_number'] ?? null;
            if ($seasonNumber === null) {
                continue;
            }

            foreach ($tmdb->getSeasonEpisodes((int) $show->tmdb_id, (int) $seasonNumber) as $episode) {
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
                $counters['episodes']++;
            }
        }
    }
}
