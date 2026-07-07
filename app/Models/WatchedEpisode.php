<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WatchedEpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $episode_id
 * @property Carbon|null $watched_at
 * @property int|null $rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'episode_id', 'watched_at', 'rating'])]
class WatchedEpisode extends Model
{
    /** @use HasFactory<WatchedEpisodeFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Episode, $this> */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watched_at' => 'datetime',
            'rating' => 'integer',
        ];
    }
}
