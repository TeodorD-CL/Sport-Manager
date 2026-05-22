<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateReview extends CreateRecord
{
    protected static string $resource = ReviewResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth('admin')->user();

        if ($user instanceof User && $user->isFacilityManager() && !$user->managesFacility($data['facility_id'])) {
            throw ValidationException::withMessages([
                'facility_id' => 'You can only create reviews for your managed facilities.',
            ]);
        }

        return $data;
    }
}
