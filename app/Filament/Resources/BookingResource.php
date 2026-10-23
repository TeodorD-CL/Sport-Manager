<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToManagedFacilities;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    use ScopesToManagedFacilities;

    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name', modifyQueryUsing: fn (Builder $query) => static::scopeBookableUsers($query))
                    ->searchable(['name', 'email'])
                    ->requiredWithout('customer_email'),
                Forms\Components\TextInput::make('customer_email')
                    ->label('Or customer email')
                    ->email()
                    ->helperText("For a customer who hasn't booked here yet. Must match their account email exactly.")
                    ->hiddenOn('edit'),
                Forms\Components\Select::make('court_id')
                    ->relationship(
                        'court',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => static::scopeToManagedFacilities($query)
                    )
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->seconds(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->seconds(false)
                    ->required()
                    ->after('start_time'),
                Forms\Components\Select::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'checked_in' => 'Checked In',
                    ])
                    ->default('confirmed')
                    ->required(),
                Forms\Components\TextInput::make('total_price')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('MKD')
                    ->formatStateUsing(fn ($state) => $state !== null ? (string) ((int) round($state / 100)) : null)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round(((float) $state) * 100) : null)
                    ->helperText('Leave empty to calculate it from the court price.'),
                Forms\Components\TextInput::make('qr_code')
                    ->disabled()
                    ->helperText('Generated automatically.'),
            ]);
    }

    /**
     * Users a manager may book for: themselves and people who have booked at their facilities.
     * Without this, any manager could list every account on the platform.
     */
    public static function scopeBookableUsers(Builder $query): Builder
    {
        $ids = static::managedFacilityIdsOrAll();

        return $ids === null ? $query : $query->where(fn (Builder $users) => $users
            ->whereKey(auth()->id())
            ->orWhereHas('bookings.court', fn (Builder $courts) => $courts->whereIn('facility_id', $ids)));
    }

    /**
     * Saves a booking from the create or edit page. Like Booking::book(), it checks the slot and overlaps
     * with the court locked, so two people can't take the same slot at the same moment.
     * The price is calculated when left empty, or when an edit moves the booking without touching the price.
     */
    public static function saveBooking(array $data, ?Booking $record, Closure $save): Booking
    {
        $court = Court::with('facility')->find($data['court_id']);
        static::assertManagesFacility($court?->facility_id, 'court_id');
        if (!$court) {
            throw ValidationException::withMessages(['data.court_id' => 'Select a court.']);
        }
        // An exact email lets a manager book for a new customer without being able to browse all accounts.
        if (filled($data['customer_email'] ?? null)) {
            // Throttled so the field can't be scripted into an email checker; staff accounts are never matched.
            if (!RateLimiter::attempt('booking-email-lookup:' . auth()->id(), 10, fn () => true)) {
                throw ValidationException::withMessages(['data.customer_email' => 'Too many email lookups. Try again in a minute.']);
            }
            $customer = User::where('email', Str::lower(trim($data['customer_email'])))
                ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', ['super_admin', 'facility_manager']))
                ->first();
            if (!$customer) {
                throw ValidationException::withMessages(['data.customer_email' => 'No account uses this email.']);
            }
            $data['user_id'] = $customer->id;
        } elseif (!static::scopeBookableUsers(User::query())->whereKey($data['user_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['data.user_id' => 'Select one of the listed users.']);
        }
        unset($data['customer_email']);

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if ($error = Booking::slotError($court, $start, $end)) {
            throw ValidationException::withMessages(['data.start_time' => $error]);
        }

        $moved = $record && ($record->court_id !== $court->id || !$record->start_time->equalTo($start) || !$record->end_time->equalTo($end));
        $data['total_price'] ??= null;
        if ($data['total_price'] === null || ($moved && $data['total_price'] === $record->total_price)) {
            $rentalCost = $record?->rentals->sum(fn ($rental) => $rental->pivot->unit_price * $rental->pivot->quantity) ?? 0;
            $data['total_price'] = Booking::calculatePrice($court, $start, $end) + $rentalCost;
        }

        $taken = ValidationException::withMessages(['data.start_time' => 'This court is already booked at that time.']);

        try {
            return DB::transaction(function () use ($data, $record, $save, $court, $start, $end, $taken) {
                Court::whereKey($court->id)->lockForUpdate()->first();

                $clashes = $data['status'] !== 'cancelled' && Booking::overlapping($court->id, $start, $end)
                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                    ->exists();
                if ($clashes) {
                    throw $taken;
                }

                return $save($data);
            });
        } catch (QueryException $e) {
            if (Booking::isOverlapViolation($e)) {
                throw $taken;
            }
            throw $e;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('court.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('total_price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 0) . ' MKD')
                    ->sortable(),
            ])
            ->defaultSort('start_time', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'checked_in' => 'Checked In',
                    ]),
                static::facilityFilter('court'),
                Tables\Filters\Filter::make('upcoming')
                    ->query(fn (Builder $query) => $query->where('start_time', '>=', now())),
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
        $query = parent::getEloquentQuery();
        $ids = static::managedFacilityIdsOrAll();

        return $ids === null
            ? $query
            : $query->whereHas('court', fn (Builder $courtQuery) => $courtQuery->whereIn('facility_id', $ids));
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
}
