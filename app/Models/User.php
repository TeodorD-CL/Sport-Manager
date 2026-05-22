<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function managedFacilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_user')->withTimestamps();
    }

    /**
     * Filament many-to-many attach actions expect the conventional inverse
     * relationship name when managing Facility <-> User from a relation manager.
     */
    public function facilities(): BelongsToMany
    {
        return $this->managedFacilities();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('name', 'super_admin')->exists();
    }

    public function isFacilityManager(): bool
    {
        return $this->roles()->where('name', 'facility_manager')->exists();
    }

    public function hasAdminPanelAccess(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isFacilityManager() && $this->managedFacilities()->exists();
    }

    public function managedFacilityIds(): array
    {
        return $this->managedFacilities()->pluck('facilities.id')->all();
    }

    public function managesFacility(string $facilityId): bool
    {
        return $this->managedFacilities()->where('facilities.id', $facilityId)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAdminPanelAccess();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
