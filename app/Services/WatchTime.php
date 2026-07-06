<?php

declare(strict_types=1);

namespace App\Services;

class WatchTime
{
    /**
     * Minuti scomposti in mesi/giorni/ore (mese = 30 giorni), per la visualizzazione.
     *
     * @return list<array{value: int, unit: string}>
     */
    public static function humanParts(int $minutes): array
    {
        $hours = intdiv($minutes, 60);
        $months = intdiv($hours, 720);
        $days = intdiv($hours % 720, 24);
        $h = $hours % 24;

        $parts = [];
        if ($months > 0) {
            $parts[] = ['value' => $months, 'unit' => $months === 1 ? __('mese') : __('mesi')];
        }
        if ($days > 0) {
            $parts[] = ['value' => $days, 'unit' => $days === 1 ? __('giorno') : __('giorni')];
        }
        if ($h > 0 || $parts === []) {
            $parts[] = ['value' => $h, 'unit' => $h === 1 ? __('ora') : __('ore')];
        }

        return $parts;
    }
}
