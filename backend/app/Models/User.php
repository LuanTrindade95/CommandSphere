<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'discord_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Community, $this>
     */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * @return HasMany<CommandView, $this>
     */
    public function commandViews(): HasMany
    {
        return $this->hasMany(CommandView::class);
    }

    /**
     * @return MorphMany<Favorite, $this>
     */
    public function favoredBy(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function hasCommunityPermission(Community|int $community, string $permission): bool
    {
        $communityId = $community instanceof Community ? $community->getKey() : $community;
        $previousCommunityId = getPermissionsTeamId();

        setPermissionsTeamId($communityId);

        try {
            return $this->hasPermissionTo($permission, 'web');
        } finally {
            setPermissionsTeamId($previousCommunityId);
        }
    }

    public function communityRole(Community|int $community): ?string
    {
        $communityId = $community instanceof Community ? $community->getKey() : $community;
        $loadedCommunity = $this->communities
            ->firstWhere('id', $communityId);

        if ($loadedCommunity !== null) {
            return $loadedCommunity->pivot->role;
        }

        return $this->communities()
            ->whereKey($communityId)
            ->first()
            ?->pivot
            ?->role;
    }
}
