<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name'])]
class UserList extends Model
{
    /** @use HasFactory<UserListFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphToMany<Show, $this> */
    public function shows(): MorphToMany
    {
        return $this->morphedByMany(Show::class, 'listable', 'list_items', 'user_list_id')->withTimestamps();
    }

    /** @return MorphToMany<Movie, $this> */
    public function movies(): MorphToMany
    {
        return $this->morphedByMany(Movie::class, 'listable', 'list_items', 'user_list_id')->withTimestamps();
    }
}
