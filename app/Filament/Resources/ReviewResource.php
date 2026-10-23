<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToManagedFacilities;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    use ScopesToManagedFacilities;

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable(['name', 'email'])
                    ->required(),
                Forms\Components\Select::make('facility_id')
                    ->relationship(
                        'facility',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => static::scopeToManagedFacilities($query, 'facilities.id')
                    )
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule->where('user_id', $get('user_id')),
                    )
                    ->validationMessages(['unique' => 'This user has already reviewed this facility.']),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(5),
                Forms\Components\Textarea::make('comment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('facility.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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

    // Without this, Filament allows bulk delete when no policy exists, letting managers remove their own bad reviews.
    public static function canDeleteAny(): bool
    {
        return static::getCurrentAdminUser()?->isSuperAdmin() ?? false;
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
}
