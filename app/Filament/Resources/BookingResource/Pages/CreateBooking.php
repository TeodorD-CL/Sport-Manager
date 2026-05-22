<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Court;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth('admin')->user();

        if ($user instanceof User && $user->isFacilityManager()) {
            $court = Court::query()->select(['id', 'facility_id'])->find($data['court_id']);
            if (!$court || !$user->managesFacility($court->facility_id)) {
                throw ValidationException::withMessages([
                    'court_id' => 'You can only create bookings for courts in your managed facilities.',
                ]);
            }
        }

        return $data;
    }
}
