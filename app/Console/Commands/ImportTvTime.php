<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Signature('import:tvtime {path : Cartella che contiene i CSV export di TV Time} {--user= : ID utente destinazione}')]
#[Description('Importa serie, episodi e visualizzazioni da un export GDPR di TV Time')]
class ImportTvTime extends Command
{
    public function handle(): int
    {
        $file = rtrim((string) $this->argument('path'), '/').'/tracking-prod-records-v2.csv';

        if (! is_readable($file)) {
            $this->error("File non leggibile: {$file}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        /** @var array<int, Show> $showCache */
        $showCache = [];
        $counters = ['shows' => 0, 'episodes' => 0, 'watched' => 0, 'library' => 0];

        DB::transaction(function () use ($file, $user, &$showCache, &$counters): void {
            foreach ($this->rows($file) as $row) {
                $key = $row['key'] ?? '';
                $tvdbId = (int) ($row['s_id'] ?? 0);
                if ($tvdbId === 0) {
                    continue;
                }

                if (! isset($showCache[$tvdbId])) {
                    $showCache[$tvdbId] = Show::firstOrCreate(
                        ['tvdb_id' => $tvdbId],
                        ['name' => $row['series_name'] ?: "Show #{$tvdbId}"],
                    );
                    if ($showCache[$tvdbId]->wasRecentlyCreated) {
                        $counters['shows']++;
                    }
                }
                $show = $showCache[$tvdbId];

                if (str_starts_with($key, 'user-series')) {
                    $this->importLibraryEntry($user, $show, $row, $counters);
                } elseif (str_starts_with($key, 'watch-episode')) {
                    $this->importWatch($user, $show, $row, $counters);
                }
            }
        });

        $this->info("Import completato per l'utente #{$user->id}:");
        $this->table(
            ['Serie (nuove)', 'In libreria', 'Episodi', 'Visti'],
            [[$counters['shows'], $counters['library'], $counters['episodes'], $counters['watched']]],
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $counters
     */
    private function importLibraryEntry(User $user, Show $show, array $row, array &$counters): void
    {
        $status = match (true) {
            ($row['is_archived'] ?? '') === 'true' => 'archived',
            ($row['is_for_later'] ?? '') === 'true' => 'watchlist',
            default => 'following',
        };

        UserShow::updateOrCreate(
            ['user_id' => $user->id, 'show_id' => $show->id],
            ['status' => $status, 'followed_at' => $this->microtime($row['followed_at'] ?? null)],
        );
        $counters['library']++;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $counters
     */
    private function importWatch(User $user, Show $show, array $row, array &$counters): void
    {
        $season = $row['season_number'] ?? '';
        $number = $row['episode_number'] ?? '';
        if ($season === '' || $number === '') {
            return;
        }

        $episode = Episode::firstOrCreate([
            'show_id' => $show->id,
            'season_number' => (int) $season,
            'episode_number' => (int) $number,
        ]);
        if ($episode->wasRecentlyCreated) {
            $counters['episodes']++;
        }

        $watch = WatchedEpisode::firstOrCreate(
            ['user_id' => $user->id, 'episode_id' => $episode->id],
            ['watched_at' => $this->datetime($row['updated_at'] ?? null)],
        );
        if ($watch->wasRecentlyCreated) {
            $counters['watched']++;
        }
    }

    /**
     * Legge il CSV come righe associative header => valore.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function rows(string $file): \Generator
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            /** @var array<string, string> $row */
            $row = array_combine($header, array_pad($data, count($header), ''));
            yield $row;
        }

        fclose($handle);
    }

    private function datetime(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /** Converte un timestamp in microsecondi (formato export) in Carbon. */
    private function microtime(?string $value): ?Carbon
    {
        if (! $value || ! ctype_digit($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) ($value / 1_000_000));
    }

    private function resolveUser(): User
    {
        if ($id = $this->option('user')) {
            return User::findOrFail($id);
        }

        return User::first() ?? tap(User::create([
            'name' => 'Me',
            'email' => 'me@tvtime.local',
            'password' => Hash::make('password'),
        ]), fn (User $u) => $this->warn("Creato utente #{$u->id} me@tvtime.local / password — cambiala nell'app."));
    }
}
