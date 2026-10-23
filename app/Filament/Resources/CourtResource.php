<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToManagedFacilities;
use App\Filament\Resources\CourtResource\Pages;
use App\Enums\CourtType;
use App\Models\Court;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourtResource extends Resource
{
    use ScopesToManagedFacilities;

    protected static ?string $model = Court::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('facility_id')
                    ->relationship(
                        'facility',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => static::scopeToManagedFacilities($query, 'facilities.id')
                    )
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options(CourtType::options())
                    ->required(),
                Forms\Components\TextInput::make('base_price_per_hour')
                    ->numeric()
                    ->required()
                    ->suffix('MKD')
                    ->formatStateUsing(fn ($state) => $state !== null ? (string) ((int) round($state / 100)) : null)
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                    ->helperText('Enter price in MKD (e.g. 100 = 100 MKD).'),
                Forms\Components\FileUpload::make('image_path')
                    ->disk('public')
                    ->directory('courts')
                    ->image(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('facility.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price_per_hour')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 0) . ' MKD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(CourtType::options()),
                static::facilityFilter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    static::deleteBulkAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToManagedFacilities(parent::getEloquentQuery(), 'facility_id');
    }

    public static function canViewAny(): bool
    {
        $user = static::getCurrentAdminUser();

        return $user?->hasAdminPanelAccess() ?? false;
    }

    public static function canCreate(): bool
    {
        $user = static::getCurrentAdminUser();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isFacilityManager() && !empty($user->managedFacilityIds());
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

        return $user->isFacilityManager() && $user->managesFacility($record->facility_id);
    }

    public static function canDelete(Model $record): bool
    {
        // Courts with bookings are kept so booking history and revenue stay intact.
        return static::canEdit($record) && !$record->bookings()->exists();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourts::route('/'),
            'create' => Pages\CreateCourt::route('/create'),
            'edit' => Pages\EditCourt::route('/{record}/edit'),
        ];
    }
}
