<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToManagedFacilities;
use App\Filament\Resources\RentalResource\Pages;
use App\Enums\CourtType;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RentalResource extends Resource
{
    use ScopesToManagedFacilities;

    protected static ?string $model = Rental::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

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
                    ->required(fn () => !static::getCurrentAdminUser()?->isSuperAdmin())
                    ->placeholder('All facilities')
                    ->helperText('Super admins can leave this empty to offer the rental at every facility.'),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->suffix('MKD')
                    ->formatStateUsing(fn ($state) => $state !== null ? (string) ((int) round($state / 100)) : null)
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                    ->helperText('Enter price in MKD (e.g. 100 = 100 MKD).'),
                Forms\Components\CheckboxList::make('suitable_for')
                    ->options(CourtType::options())
                    ->columns(2)
                    ->helperText('Leave empty to show for all sport types'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->placeholder('All facilities')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 0) . ' MKD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('suitable_for')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ($state ?? 'All')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
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
        return static::getCurrentAdminUser()?->hasAdminPanelAccess() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
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

        return $user->isFacilityManager() && $record->facility_id && $user->managesFacility($record->facility_id);
    }

    public static function canDelete(Model $record): bool
    {
        // Rentals used in bookings are kept so those bookings keep their extras.
        return static::canEdit($record) && !$record->bookings()->exists();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentals::route('/'),
            'create' => Pages\CreateRental::route('/create'),
            'edit' => Pages\EditRental::route('/{record}/edit'),
        ];
    }
}
