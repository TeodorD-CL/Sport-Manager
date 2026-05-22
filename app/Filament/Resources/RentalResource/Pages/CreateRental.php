<?php

namespace App\Filament\Resources\RentalResource\Pages;

use App\Filament\Resources\RentalResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateRental extends CreateRecord
{
    protected static string $resource = RentalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth('admin')->user();

        if ($user instanceof User && $user->isFacilityManager() && !$user->managesFacility($data['facility_id'])) {
            throw ValidationException::withMessages([
                'facility_id' => 'You can only create rentals for your managed facilities.',
            ]);
        }

        return $data;
    }
}
