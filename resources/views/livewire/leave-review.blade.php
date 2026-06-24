<div>
    @if(session()->has('message'))
        <div class="mb-6 border-l-4 border-black bg-gray-50 p-4">
            <p class="text-sm font-bold text-black">{{ session('message') }}</p>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="mb-6 border-l-4 border-red-600 bg-red-50 p-4">
            <p class="text-sm font-bold text-red-900">{{ session('error') }}</p>
        </div>
    @endif

    @if($alreadyReviewed)
        <div class="border border-gray-200 p-8 text-center bg-gray-50">
            <p class="text-lg font-bold text-black mb-1">Review Submitted</p>
            <p class="text-gray-600 text-sm">Thank you for sharing your experience.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Overall Rating</label>
                <div class="flex gap-2">
                    @for($i = 1; $i <= 5; $i++)
                        <label class="cursor-pointer group">
                            <input type="radio" wire:model.live="rating" value="{{ $i }}" class="sr-only">
                            <span class="text-3xl {{ $i <= $rating ? 'text-black' : 'text-gray-300 group-hover:text-gray-500' }}">★</span>
                        </label>
                    @endfor
                </div>
                @error('rating')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Your Feedback</label>
                <textarea wire:model="comment" rows="4"
                          class="w-full border border-gray-300 p-4 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none resize-none"
                          placeholder="What did you think of the courts and facilities?"></textarea>
                @error('comment')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="bg-black text-white px-8 py-3 font-bold text-sm hover:bg-gray-800 transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">Post Review</span>
                <span wire:loading wire:target="submit">Processing...</span>
            </button>
        </form>
    @endif
</div>
