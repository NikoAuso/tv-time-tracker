<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserMovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $movie_id
 * @property string $status
 * @property Carbon|null $watched_at
 * @property int $rewatch_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'movie_id', 'status', 'watched_at', 'rewatch_count'])]
class UserMovie extends Model
{
    /** @use HasFactory<UserMovieFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watched_at' => 'datetime',
            'rewatch_count' => 'integer',
        ];
    }
}
