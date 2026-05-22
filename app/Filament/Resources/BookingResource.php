<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('court_id')
                    ->relationship(
                        'court',
                        'name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = static::getCurrentAdminUser();
                            if (!$user || $user->isSuperAdmin()) {
                                return;
                            }

                            $query->whereIn('facility_id', $user->managedFacilityIds());
                        }
                    )
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time'),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\Select::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'checked_in' => 'Checked In',
                    ]),
                Forms\Components\TextInput::make('total_price')
                    ->numeric(),
                Forms\Components\TextInput::make('qr_code')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('court.name'),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        'checked_in' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_price'),
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
            return $query->whereHas('court', function (Builder $courtQuery) use ($user) {
                $courtQuery->whereIn('facility_id', $user->managedFacilityIds());
            });
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

        if (!$user->isFacilityManager()) {
            return false;
        }

        $record->loadMissing('court:id,facility_id');

        return $record->court && $user->managesFacility($record->court->facility_id);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
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
