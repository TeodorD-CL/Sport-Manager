<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityResource\Pages;
use App\Filament\Resources\FacilityResource\RelationManagers\ManagersRelationManager;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('city')
                    ->required(),
                Forms\Components\TextInput::make('address')
                    ->required(),
                Forms\Components\FileUpload::make('image_path'),
                Forms\Components\TextInput::make('opening_hour')
                    ->label('Opening hour')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(23)
                    ->required()
                    ->helperText('24h format (0-23).'),
                Forms\Components\TextInput::make('closing_hour')
                    ->label('Closing hour')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(24)
                    ->required()
                    ->gt('opening_hour')
                    ->helperText('Must be greater than opening hour.'),
                Forms\Components\CheckboxList::make('amenities')
                    ->relationship('amenities', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\TextColumn::make('opening_hour')
                    ->label('Open')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 2, '0', STR_PAD_LEFT) . ':00'),
                Tables\Columns\TextColumn::make('closing_hour')
                    ->label('Close')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 2, '0', STR_PAD_LEFT) . ':00'),
                Tables\Columns\TextColumn::make('courts_count')
                    ->counts('courts'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = static::getCurrentAdminUser();

        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isFacilityManager()) {
            return $query->whereIn('id', $user->managedFacilityIds());
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        $user = static::getCurrentAdminUser();

        return $user?->hasAdminPanelAccess() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::getCurrentAdminUser()?->isSuperAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::getCurrentAdminUser();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isFacilityManager() && $user->managesFacility($record->getKey());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getRelations(): array
    {
        return [
            ManagersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }

    protected static function getCurrentAdminUser(): ?User
    {
        $user = auth('admin')->user();

        if ($user instanceof User) {
            return $user;
        }

        $defaultGuardUser = auth()->user();

        return $defaultGuardUser instanceof User ? $defaultGuardUser : null;
    }
}
