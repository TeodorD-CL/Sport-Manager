<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A time a facility (court_id null) or one of its courts can't be booked: maintenance, holidays, tournaments. */
class Closure extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facility_id',
        'court_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** Closures of this court, or of its whole facility, that overlap start–end. */
    public function scopeAffecting(Builder $query, Court $court, Carbon $start, Carbon $end): void
    {
        $query->where('facility_id', $court->facility_id)
            ->where(fn (Builder $q) => $q->whereNull('court_id')->orWhere('court_id', $court->id))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }

    /** True when this closure covers the given court. */
    public function covers(Court $court): bool
    {
        return $this->facility_id === $court->facility_id && ($this->court_id === null || $this->court_id === $court->id);
    }

    /** Live bookings that fall inside this closure. They are kept; staff decide whether to cancel or move them. */
    public function conflictingBookings(): \Illuminate\Database\Eloquent\Collection
    {
        return Booking::with(['court', 'user'])
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereHas('court', fn (Builder $q) => $q->where('facility_id', $this->facility_id)
                ->when($this->court_id, fn (Builder $q) => $q->whereKey($this->court_id)))
            ->where('start_time', '<', $this->ends_at)
            ->where('end_time', '>', $this->starts_at)
            ->orderBy('start_time')
            ->get();
    }
}
