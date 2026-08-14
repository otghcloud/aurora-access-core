<?php

namespace OTGH\AccessControl\Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Individual;

#[Fillable(['name', 'email', 'password', 'access_individual_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function accessIndividual(): BelongsTo
    {
        return $this->belongsTo(Individual::class, 'access_individual_id');
    }

    public function hasAreaPermission(int|string $areaId): bool
    {
        $individualId = $this->access_individual_id ?? $this->getKey();

        return AreaPermission::query()
            ->where('individual_id', $individualId)
            ->where('area_id', $areaId)
            ->where('permission', 'allow')
            ->exists();
    }

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
}
