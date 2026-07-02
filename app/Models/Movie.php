<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property string|null $tvtime_uuid
 * @property string $title
 * @property Carbon|null $release_date
 * @property string|null $poster_path
 * @property string|null $overview
 * @property int|null $runtime
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tmdb_id', 'tvtime_uuid', 'title', 'release_date', 'poster_path', 'overview', 'runtime'])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /** @return HasMany<UserMovie, $this> */
    public function userEntries(): HasMany
    {
        return $this->hasMany(UserMovie::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
        ];
    }
}
