<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Today's schedule for the facilities this user runs, in time order, with check-in. */
class TodaysBookings extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Today';

    public function table(Table $table): Table
    {
        return $table
            // BookingResource's query keeps managers to their own facilities.
            ->query(BookingResource::getEloquentQuery()
                ->with(['court.facility', 'user'])
                ->where('status', '!=', 'cancelled')
                ->whereBetween('start_time', [today(), today()->endOfDay()]))
            ->defaultSort('start_time')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No bookings today')
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn (Booking $record) => $record->start_time->format('H:i') . '–' . $record->end_time->format('H:i')),
                Tables\Columns\TextColumn::make('court.name')
                    ->label('Court')
                    ->description(fn (Booking $record) => $record->court->facility->name),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst())
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'checked_in' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('qr_code')
                    ->label('Reference')
                    ->formatStateUsing(fn (?string $state) => str((string) $state)->limit(8, '')->upper()),
            ])
            ->actions([
                BookingResource::checkInAction(),
                Tables\Actions\EditAction::make()
                    ->url(fn (Booking $record) => BookingResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
