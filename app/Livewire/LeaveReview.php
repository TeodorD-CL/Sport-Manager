<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Review;
use Livewire\Component;

class LeaveReview extends Component
{
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

        $hasBooked = Booking::where('user_id', auth()->id())
            ->whereHas('court', fn ($q) => $q->where('facility_id', $this->facilityId))
            ->where('status', '!=', 'cancelled')
            ->exists();

        if (!$hasBooked) {
            session()->flash('error', 'You need a booking at this facility before leaving a review.');
            return;
        }

        Review::create([
            'user_id' => auth()->id(),
            'facility_id' => $this->facilityId,
            'rating' => $this->rating,
            'comment' => $this->comment,
        ]);

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
