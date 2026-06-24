<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rental extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facility_id',
        'name',
        'price',
        'suitable_for',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'suitable_for' => 'array',
        ];
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class)->withPivot('quantity');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function isSuitableFor(string $courtType): bool
    {
        if (empty($this->suitable_for)) {
            return true;
        }

        return in_array($courtType, $this->suitable_for);
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->price / 100, 0) . ' MKD'
        );
    }
}
