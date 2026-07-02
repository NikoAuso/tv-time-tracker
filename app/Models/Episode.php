<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $show_id
 * @property int|null $tmdb_id
 * @property int $season_number
 * @property int $episode_number
 * @property string|null $name
 * @property string|null $overview
 * @property string|null $still_path
 * @property Carbon|null $air_date
 * @property int|null $runtime
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['show_id', 'tmdb_id', 'season_number', 'episode_number', 'name', 'overview', 'still_path', 'air_date', 'runtime'])]
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    /** @return BelongsTo<Show, $this> */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** @return HasMany<WatchedEpisode, $this> */
    public function watches(): HasMany
    {
        return $this->hasMany(WatchedEpisode::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'air_date' => 'date',
        ];
    }
}
