<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Booking extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'court_id',
        'start_time',
        'end_time',
        'status',
        'total_price',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function rentals(): BelongsToMany
    {
        return $this->belongsToMany(Rental::class)->withPivot('quantity');
    }

    public static function calculatePrice(Court $court, $startTime, $endTime, array $rentals = []): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('end_time must be after start_time');
        }

        $hours = max(1, $start->diffInHours($end));

        $basePrice = $court->base_price_per_hour * $hours;

        $startHour = (int) $start->format('H');
        $isPeak = $startHour >= 18 && $startHour < 22;
        if ($isPeak) {
            $basePrice = (int) round($basePrice * 1.2);
        }

        if ($start->isWeekend()) {
            $basePrice = (int) round($basePrice * 1.1);
        }

        $rentalCost = 0;
        if (!empty($rentals)) {
            $rentalIds = array_column($rentals, 'id');
            $quantities = array_column($rentals, 'quantity', 'id');
            $rentalModels = Rental::whereIn('id', $rentalIds)->get()->keyBy('id');

            foreach ($rentalIds as $rentalId) {
                $rentalModel = $rentalModels->get($rentalId);
                if ($rentalModel) {
                    $rentalCost += $rentalModel->price * ($quantities[$rentalId] ?? 1);
                }
            }
        }

        return (int) round($basePrice + $rentalCost);
    }

    protected function formattedTotalPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_price / 100, 0) . ' MKD'
        );
    }
}
