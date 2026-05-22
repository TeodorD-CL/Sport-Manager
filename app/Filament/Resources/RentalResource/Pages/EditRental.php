<?php

namespace App\Filament\Resources\RentalResource\Pages;

use App\Filament\Resources\RentalResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRental extends EditRecord
{
    protected static string $resource = RentalResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth('admin')->user();

        if ($user instanceof User && $user->isFacilityManager() && !$user->managesFacility($data['facility_id'])) {
            throw ValidationException::withMessages([
                'facility_id' => 'You can only assign rentals to your managed facilities.',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
