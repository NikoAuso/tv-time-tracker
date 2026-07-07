<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use App\Services\Tmdb;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa l'export JSON dell'estensione browser di TV Time (file
 * tvtime-series-*.json e tvtime-movies-*.json). Le serie combaciano col DB
 * per tvdb_id (stessa chiave dell'import CSV); i film risolvono il tmdb_id
 * dall'imdb_id per un match esatto. Merge idempotente: mai duplicati, i campi
 * mancanti vengono completati senza sovrascrivere con null.
 */
class ImportTvTimeJson extends Command
{
    protected $signature = 'import:tvtime-json {path : Cartella con i JSON dell\'estensione} {--user= : ID utente destinazione}';

    protected $description = 'Importa i dati dall\'export JSON dell\'estensione browser di TV Time';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $dir = rtrim($this->argument('path'), '/');

        $seriesFile = collect(glob($dir.'/*series*.json'))->first();
        $moviesFile = collect(glob($dir.'/*movies*.json'))->first();

        if (! $seriesFile && ! $moviesFile) {
            $this->error('Nessun file *series*.json o *movies*.json in: '.$dir);

            return self::FAILURE;
        }

        DB::transaction(function () use ($seriesFile, $moviesFile, $userId) {
            if ($seriesFile) {
                $this->importSeries($this->readJson($seriesFile), $userId);
            }
            if ($moviesFile) {
                $this->importMovies($this->readJson($moviesFile), $userId);
            }
        });

        $this->info('Import JSON completato.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJson(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $series
     */
    private function importSeries(array $series, int $userId): void
    {
        foreach ($series as $s) {
            $tvdb = data_get($s, 'id.tvdb');
            if (! $tvdb) {
                continue;
            }

            $show = Show::firstOrCreate(['tvdb_id' => (int) $tvdb], ['name' => (string) ($s['title'] ?? '')]);

            $hasWatched = false;
            foreach ($s['seasons'] ?? [] as $season) {
                foreach ($season['episodes'] ?? [] as $ep) {
                    if (! ($ep['is_watched'] ?? false) && empty($ep['watched_at'])) {
                        continue;
                    }
                    $hasWatched = true;

                    $episode = Episode::firstOrCreate(
                        [
                            'show_id' => $show->id,
                            'season_number' => (int) ($season['number'] ?? 0),
                            'episode_number' => (int) ($ep['number'] ?? 0),
                        ],
                        $this->present(['name' => $ep['name'] ?? null]),
                    );

                    WatchedEpisode::firstOrCreate(
                        ['user_id' => $userId, 'episode_id' => $episode->id],
                        ['watched_at' => $this->date($ep['watched_at'] ?? null)],
                    );
                }
            }

            UserShow::updateOrCreate(
                ['user_id' => $userId, 'show_id' => $show->id],
                $this->present([
                    'status' => $hasWatched ? 'following' : 'watchlist',
                    'is_favorite' => (bool) ($s['is_favorite'] ?? false),
                    'followed_at' => $this->date($s['created_at'] ?? null),
                ]),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $movies
     */
    private function importMovies(array $movies, int $userId): void
    {
        $tmdb = app(Tmdb::class);

        foreach ($movies as $m) {
            $imdb = data_get($m, 'id.imdb');
            $tmdbId = null;
            if ($imdb) {
                $found = rescue(fn () => $tmdb->findByImdbId((string) $imdb), null, report: false);
                $tmdbId = $found['id'] ?? null;
            }

            $match = match (true) {
                $tmdbId !== null => ['tmdb_id' => $tmdbId],
                $imdb !== null => ['imdb_id' => $imdb],
                default => ['title' => (string) ($m['title'] ?? '')],
            };

            $movie = Movie::updateOrCreate($match, $this->present([
                'tmdb_id' => $tmdbId,
                'imdb_id' => $imdb,
                'title' => $m['title'] ?? null,
                'release_date' => isset($m['year']) ? Carbon::createFromDate((int) $m['year'], 1, 1)->startOfDay() : null,
            ]));

            UserMovie::updateOrCreate(
                ['user_id' => $userId, 'movie_id' => $movie->id],
                $this->present([
                    'status' => 'watched',
                    'watched_at' => $this->date($m['watched_at'] ?? null),
                    'rewatch_count' => (int) ($m['rewatch_count'] ?? 0),
                    'is_favorite' => (bool) ($m['is_favorite'] ?? false),
                ]),
            );
        }
    }

    /**
     * Rimuove i valori null così l'updateOrCreate non sovrascrive campi già
     * valorizzati con un null (merge arricchente).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function present(array $values): array
    {
        return array_filter($values, fn ($v) => $v !== null);
    }

    private function date(?string $iso): ?Carbon
    {
        return $iso ? rescue(fn () => Carbon::parse($iso), null, report: false) : null;
    }
}
