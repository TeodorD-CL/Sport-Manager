<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeaveReview extends Component
{
    #[Locked]
    public $facilityId;
    public int $rating = 5;
    public string $comment = '';
    public bool $alreadyReviewed = false;

    protected function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function mount($facilityId)
    {
        abort_unless(is_string($facilityId) && Str::isUuid($facilityId), 404);
        $this->facilityId = $facilityId;
        $this->alreadyReviewed = auth()->check() && Review::where('user_id', auth()->id())
            ->where('facility_id', $facilityId)
            ->exists();
    }

    public function submit()
    {
        if (!auth()->check()) {
            return;
        }

        $this->validate();

        if ($this->alreadyReviewed || Review::where('user_id', auth()->id())->where('facility_id', $this->facilityId)->exists()) {
            session()->flash('error', 'You have already submitted a review for this facility.');
            $this->alreadyReviewed = true;
            return;
        }

        // Only a booking that has already been played counts, so nobody can book, review and then cancel.
        $hasPlayed = Booking::where('user_id', auth()->id())
            ->whereHas('court', fn ($q) => $q->where('facility_id', $this->facilityId))
            ->where('status', '!=', 'cancelled')
            ->where('end_time', '<', now())
            ->exists();

        if (!$hasPlayed) {
            session()->flash('error', 'You can review this facility after your first booking here has taken place.');
            return;
        }

        try {
            Review::create([
                'user_id' => auth()->id(),
                'facility_id' => $this->facilityId,
                'rating' => $this->rating,
                'comment' => $this->comment,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double submit raced past the check above; the first one was saved.
            session()->flash('error', 'You have already submitted a review for this facility.');
            $this->alreadyReviewed = true;
            return;
        }

        $this->alreadyReviewed = true;
        $this->rating = 5;
        $this->comment = '';

        session()->flash('message', 'Thank you! Your review has been submitted.');
        $this->dispatch('reviewSubmitted');
    }

    public function render()
    {
        return view('livewire.leave-review');
    }
}
