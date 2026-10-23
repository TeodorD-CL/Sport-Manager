<?php

namespace App\Filament\Concerns;

use App\Models\Facility;
use App\Models\User;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Facility managers only see and change records of the facilities they manage; super admins see everything. */
trait ScopesToManagedFacilities
{
    protected static function getCurrentAdminUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** The facility IDs the current user may manage, or null when they may manage all of them. */
    protected static function managedFacilityIdsOrAll(): ?array
    {
        $user = static::getCurrentAdminUser();

        if ($user?->isSuperAdmin()) {
            return null;
        }

        return $user?->isFacilityManager() ? $user->managedFacilityIds() : [];
    }

    protected static function scopeToManagedFacilities(Builder $query, string $column = 'facility_id'): Builder
    {
        $ids = static::managedFacilityIdsOrAll();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** @throws ValidationException when a manager picks a facility they don't manage. */
    public static function assertManagesFacility(?string $facilityId, string $field): void
    {
        $ids = static::managedFacilityIdsOrAll();

        if ($ids !== null && !in_array($facilityId, $ids, true)) {
            throw ValidationException::withMessages([
                "data.{$field}" => 'You can only use facilities you manage.',
            ]);
        }
    }

    /** Filter by facility, listing only the facilities this user manages. $through is the relation that leads to facility_id. */
    protected static function facilityFilter(?string $through = null): SelectFilter
    {
        return SelectFilter::make('facility')
            ->options(fn () => static::scopeToManagedFacilities(Facility::query(), 'id')->orderBy('name')->pluck('name', 'id'))
            ->query(function (Builder $query, array $data) use ($through) {
                if (blank($data['value'])) {
                    return $query;
                }

                return $through
                    ? $query->whereHas($through, fn (Builder $q) => $q->where('facility_id', $data['value']))
                    : $query->where('facility_id', $data['value']);
            });
    }

    /** Bulk delete that skips records canDelete() refuses, since Filament doesn't check them one by one. */
    protected static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->using(fn (Collection $records) => $records
                ->filter(fn (Model $record) => static::canDelete($record))
                ->each(fn (Model $record) => $record->delete()));
    }
}
