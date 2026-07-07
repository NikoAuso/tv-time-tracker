<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property string|null $tvtime_uuid
 * @property string|null $imdb_id
 * @property string $title
 * @property Carbon|null $release_date
 * @property string|null $poster_path
 * @property string|null $overview
 * @property int|null $runtime
 * @property array<int, string>|null $genres
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tmdb_id', 'tvtime_uuid', 'imdb_id', 'title', 'release_date', 'poster_path', 'overview', 'runtime', 'genres'])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /** @return HasMany<UserMovie, $this> */
    public function userEntries(): HasMany
    {
        return $this->hasMany(UserMovie::class);
    }

    /** @return MorphToMany<UserList, $this> */
    public function lists(): MorphToMany
    {
        return $this->morphToMany(UserList::class, 'listable', 'list_items', 'listable_id', 'user_list_id')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'genres' => 'array',
        ];
    }
}
