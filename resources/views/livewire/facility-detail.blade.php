@push('head')
    <meta name="description" content="{{ Str::limit($facility->description ?? $facility->name . ' – book courts in ' . $facility->city . ' on Sport-Manager.', 160) }}">
    <meta property="og:title" content="{{ $facility->name }} – Sport-Manager">
    <meta property="og:description" content="{{ Str::limit($facility->description ?? 'Book sports courts at ' . $facility->name . ' in ' . $facility->city . '.', 160) }}">
@endpush

<div>
    <section class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ $facility->name }}</h1>
                    <p class="text-gray-300 mt-1">{{ $facility->city }} &middot; {{ $facility->address }}</p>
                    <div class="flex items-center mt-2">
                        @php
                            $avg = round($facility->reviews->avg('rating') ?? 0, 1);
                            $count = $facility->reviews->count();
                        @endphp
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-5 h-5 {{ $i <= round($avg) ? 'text-yellow-400' : 'text-gray-500' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        @endfor
                        <span class="ml-2 text-sm">{{ $avg }} ({{ $count }} reviews)</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                    @foreach($facility->amenities as $amenity)
                        <span class="bg-gray-700 text-gray-200 text-xs px-3 py-1 rounded-full">{{ $amenity->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            @php
                $images = $facility->courts->pluck('image_path')->filter();
                if ($facility->image_path) {
                    $images = collect([$facility->image_path])->merge($images);
                }
                if ($images->isEmpty()) {
                    $images = collect(['/images/sports-hall.jpg']);
                }
            @endphp
            @foreach($images->take(3) as $index => $img)
                @php
                    $src = $img;
                    if ($src && !str_starts_with($src, '/') && !str_starts_with($src, 'http')) {
                        $src = '/storage/' . $src;
                    }
                @endphp
                <div class="{{ $index === 0 ? 'md:col-span-2 md:row-span-2' : '' }} rounded-lg overflow-hidden">
                    <img src="{{ $src }}" alt="{{ $facility->name }}" class="w-full h-full object-cover">
                </div>
            @endforeach
        </div>

        @if($facility->description)
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-3">About</h2>
                <p class="text-gray-600">{{ $facility->description }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="flash-message mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Available Time Slots</h2>
                    <p class="text-sm text-gray-500">
                        Booking hours: {{ str_pad((string) $facility->opening_hour, 2, '0', STR_PAD_LEFT) }}:00 - {{ str_pad((string) $facility->closing_hour, 2, '0', STR_PAD_LEFT) }}:00
                    </p>
                </div>
                <input type="date" wire:model.live="selectedDate" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2">
            </div>

            <div class="space-y-6">
                @foreach($availableSlots as $courtId => $data)
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-lg text-gray-900">{{ $data['court']['name'] }}</h3>
                                <p class="text-sm text-gray-500">{{ $data['court']['type'] }} &middot; {{ number_format($data['court']['base_price_per_hour'] / 100, 0) }} MKD/hr</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                            @foreach($data['slots'] as $slot)
                                @if($slot['available'])
                                    <button
                                        wire:click="selectSlot('{{ $courtId }}', {{ json_encode($slot) }})"
                                        class="px-3 py-2 text-sm bg-green-50 text-green-700 border border-green-200 rounded-md hover:bg-green-100 transition text-center"
                                    >
                                        {{ $slot['start'] }}
                                    </button>
                                @else
                                    <button disabled class="px-3 py-2 text-sm bg-gray-100 text-gray-400 border border-gray-200 rounded-md cursor-not-allowed text-center line-through">
                                        {{ $slot['start'] }}
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if($showBookingModal)
            <div class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="$set('showBookingModal', false)"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Confirm Booking</h3>
                        <div class="space-y-3 text-sm text-gray-600">
                            @php
                                $selectedCourt = $facility->courts->firstWhere('id', $selectedCourtId);
                                $startTime = \Carbon\Carbon::parse($selectedDate . ' ' . $selectedSlot['start']);
                                $isPeak = (int)$startTime->format('H') >= 18 && (int)\Carbon\Carbon::parse($selectedDate . ' ' . $selectedSlot['end'])->format('H') <= 22;
                                $isWeekend = $startTime->isWeekend();
                                $basePrice = $selectedCourt ? $selectedCourt->base_price_per_hour : 0;
                                $rentalTotal = 0;
                                foreach ($selectedRentals as $rId) {
                                    $r = $rentals->firstWhere('id', $rId);
                                    if ($r) $rentalTotal += $r->price;
                                }
                            @endphp
                            <p><span class="font-medium text-gray-900">Court:</span> {{ $selectedCourt?->name }}</p>
                            <p><span class="font-medium text-gray-900">Date:</span> {{ $selectedDate }}</p>
                            <p><span class="font-medium text-gray-900">Time:</span> {{ $selectedSlot['start'] }} - {{ $selectedSlot['end'] }}</p>

                            @if($rentals->count() > 0)
                                <div class="border-t pt-3 mt-3">
                                    <p class="font-medium text-gray-900 mb-2">Equipment Rentals</p>
                                    @foreach($rentals as $rental)
                                        <label class="flex items-center justify-between py-1 cursor-pointer">
                                            <div class="flex items-center">
                                                <input type="checkbox" wire:click="toggleRental('{{ $rental->id }}')" {{ in_array($rental->id, $selectedRentals) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                                                <span>{{ $rental->name }}</span>
                                            </div>
                                            <span class="text-gray-500">{{ number_format($rental->price / 100, 0) }} MKD</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            <div class="border-t pt-3 mt-3 space-y-1">
                                <p class="font-medium text-gray-900 mb-2">Price Breakdown</p>
                                <div class="flex justify-between">
                                    <span>Base price</span>
                                    <span>{{ number_format($basePrice / 100, 0) }} MKD</span>
                                </div>
                                @if($isPeak)
                                    <div class="flex justify-between text-orange-600">
                                        <span>Peak surcharge (20%)</span>
                                        <span>+{{ number_format(round($basePrice * 0.2) / 100, 0) }} MKD</span>
                                    </div>
                                @endif
                                @if($isWeekend)
                                    <div class="flex justify-between text-orange-600">
                                        <span>Weekend surcharge (10%)</span>
                                        <span>+{{ number_format(round(($isPeak ? $basePrice * 1.2 : $basePrice) * 0.1) / 100, 0) }} MKD</span>
                                    </div>
                                @endif
                                @if($rentalTotal > 0)
                                    <div class="flex justify-between">
                                        <span>Equipment rentals</span>
                                        <span>+{{ number_format($rentalTotal / 100, 0) }} MKD</span>
                                    </div>
                                @endif
                                <div class="flex justify-between font-bold text-gray-900 border-t pt-2 mt-2">
                                    <span>Total</span>
                                    <span>{{ number_format($calculatedPrice / 100, 0) }} MKD</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-3">
                            @auth
                                <button wire:click="confirmBooking" wire:loading.attr="disabled" class="flex-1 bg-indigo-600 text-white text-center py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition font-medium flex items-center justify-center gap-2">
                                    <svg wire:loading wire:target="confirmBooking" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Confirm & Book
                                </button>
                            @else
                                <a href="/login" class="flex-1 bg-indigo-600 text-white text-center py-2 rounded-md hover:bg-indigo-700 transition font-medium">
                                    Login to Book
                                </a>
                            @endauth
                            <button wire:click="$set('showBookingModal', false)" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-md hover:bg-gray-300 transition font-medium">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($facility->reviews->count() > 0)
            <div class="mt-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Reviews</h2>
                <div class="space-y-4">
                    @foreach($facility->reviews as $review)
                        <div class="bg-white rounded-lg shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900">{{ $review->user?->name ?? 'Anonymous' }}</span>
                                <div class="flex">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="text-gray-600 text-sm mt-2">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @auth
            <div class="mt-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Leave a Review</h2>
                <livewire:leave-review :facilityId="$facility->id" />
            </div>
        @endauth
    </section>
</div>
