<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'city',
        'address',
        'image_path',
        'opening_hour',
        'closing_hour',
    ];

    protected function casts(): array
    {
        return [
            'opening_hour' => 'integer',
            'closing_hour' => 'integer',
        ];
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_user')->withTimestamps();
    }

    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (array_key_exists('reviews_avg_rating', $this->attributes)) {
                    return $this->attributes['reviews_avg_rating'];
                }
                return $this->reviews->avg('rating');
            },
        );
    }

    protected function reviewCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (array_key_exists('reviews_count', $this->attributes)) {
                    return (int) $this->attributes['reviews_count'];
                }
                return $this->reviews->count();
            },
        );
    }
}
