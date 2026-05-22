<?php

namespace App\Filament\Resources\CourtResource\Pages;

use App\Filament\Resources\CourtResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCourt extends EditRecord
{
    protected static string $resource = CourtResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth('admin')->user();

        if ($user instanceof User && $user->isFacilityManager() && !$user->managesFacility($data['facility_id'])) {
            throw ValidationException::withMessages([
                'facility_id' => 'You can only assign courts to your managed facilities.',
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
