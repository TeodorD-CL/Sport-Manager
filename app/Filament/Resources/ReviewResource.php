<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\Select::make('facility_id')
                    ->relationship(
                        'facility',
                        'name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = static::getCurrentAdminUser();
                            if (!$user || $user->isSuperAdmin()) {
                                return;
                            }
                            $query->whereIn('facilities.id', $user->managedFacilityIds());
                        }
                    )
                    ->required(),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5),
                Forms\Components\Textarea::make('comment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('facility.name'),
                Tables\Columns\TextColumn::make('rating'),
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
            return $query->whereIn('facility_id', $user->managedFacilityIds());
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

        return false;
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
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
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
