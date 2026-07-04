<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property int|null $tvdb_id
 * @property string $name
 * @property string|null $poster_path
 * @property string|null $overview
 * @property Carbon|null $first_air_date
 * @property int|null $total_episodes
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tmdb_id', 'tvdb_id', 'name', 'poster_path', 'overview', 'first_air_date', 'total_episodes', 'status'])]
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory;

    /** @return HasMany<Episode, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
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
            'first_air_date' => 'date',
        ];
    }
}
