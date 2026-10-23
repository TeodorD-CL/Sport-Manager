<?php

namespace App\Filament\Resources\CourtResource\Pages;

use App\Filament\Resources\CourtResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourt extends CreateRecord
{
    protected static string $resource = CourtResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        CourtResource::assertManagesFacility($data['facility_id'] ?? null, 'facility_id');

        return $data;
    }
}
