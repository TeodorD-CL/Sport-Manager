<?php

namespace App\Filament\Resources\FacilityResource\RelationManagers;

use App\Models\Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Times the facility, or one of its courts, can't be booked (maintenance, holidays, tournaments).
 * Booking::slotError() enforces them on every booking path. Visible to anyone who can edit the facility.
 */
class ClosuresRelationManager extends RelationManager
{
    protected static string $relationship = 'closures';

    protected static ?string $title = 'Closures';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('court_id')
                    ->label('Court')
                    ->options(fn () => $this->getOwnerRecord()->courts()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Whole facility'),
                Forms\Components\TextInput::make('reason')
                    ->maxLength(255)
                    ->placeholder('e.g. Maintenance'),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('From')
                    ->seconds(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Until')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at'),
                Forms\Components\Checkbox::make('keep_bookings')
                    ->label('Close even if customers have bookings then. Their bookings stay confirmed; cancel or move them yourself.')
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('court.name')
                    ->label('Court')
                    ->placeholder('Whole facility'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('From')
                    ->dateTime('D j M Y, H:i'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Until')
                    ->dateTime('D j M Y, H:i'),
                Tables\Columns\TextColumn::make('reason'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add closure')
                    ->before(fn (Action $action, array $data) => $this->stopOnConflicts($action, $data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->before(fn (Action $action, array $data, Closure $record) => $this->stopOnConflicts($action, $data, $record)),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /**
     * Closing a court over existing bookings must be a deliberate choice: without the checkbox the save is stopped
     * and the affected bookings are listed. Bookings are never cancelled automatically.
     */
    private function stopOnConflicts(Action $action, array $data, ?Closure $record = null): void
    {
        $courtId = $data['court_id'] ?? null;
        if ($courtId !== null && !$this->getOwnerRecord()->courts()->whereKey($courtId)->exists()) {
            Notification::make()->danger()->title('Pick one of this facility\'s courts.')->send();
            $action->halt();
        }

        $closure = new Closure([...$data, 'facility_id' => $this->getOwnerRecord()->getKey()]);
        $conflicts = $closure->conflictingBookings();
        // The checkbox isn't saved (dehydrated(false)), so read it from the raw form state.
        $keep = (bool) ($this->mountedTableActionsData[0]['keep_bookings'] ?? false);
        if ($conflicts->isEmpty() || $keep) {
            return;
        }

        Notification::make()
            ->warning()
            ->title($conflicts->count() === 1 ? '1 booking falls in this closure' : $conflicts->count() . ' bookings fall in this closure')
            ->body($conflicts->take(5)->map(fn ($booking) => "{$booking->start_time->format('D j M, H:i')} · {$booking->court->name} · {$booking->user?->name}")->join('<br>')
                . ($conflicts->count() > 5 ? '<br>…' : '')
                . '<br>Tick the box to close anyway, or move these bookings first.')
            ->persistent()
            ->send();
        $action->halt();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }
}
