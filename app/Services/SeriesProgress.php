<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Episode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Classifica le serie per progresso di visione (non per status di libreria):
 * 0 episodi visti = "Da iniziare", iniziate ma incomplete = "In corso",
 * tutti gli episodi usciti visti = "Concluse". Unica fonte di verità
 * condivisa da Libreria e Statistiche.
 */
class SeriesProgress
{
    /**
     * show_id con almeno un episodio visto dall'utente.
     *
     * @return Collection<int, int>
     */
    public static function startedShowIds(int $userId): Collection
    {
        return DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', $userId)
            ->distinct()->pluck('episodes.show_id');
    }

    /**
     * show_id iniziate e senza episodi già usciti ancora da vedere.
     * Stesso criterio di episodio in sospeso della dashboard "Da guardare".
     *
     * @return Collection<int, int>
     */
    public static function concludedShowIds(int $userId): Collection
    {
        $started = self::startedShowIds($userId);

        $withUnwatched = Episode::query()
            ->whereIn('show_id', $started)
            ->where('season_number', '>=', 1)
            ->whereDoesntHave('watches', fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q->whereNull('air_date')->orWhereDate('air_date', '<=', now()))
            ->distinct()->pluck('show_id');

        return $started->diff($withUnwatched)->values();
    }
}
