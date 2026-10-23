@push('head')
    <meta name="description" content="{{ Str::limit($facility->description ?? $facility->name . ' – book courts in ' . $facility->city . ' on Sport Manager.', 160) }}">
    <meta property="og:title" content="{{ $facility->name }} – Sport Manager">
@endpush

<div class="bg-white min-h-screen pb-16">
    {{-- Header Section --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 pb-6 border-b border-gray-200">
        <h1 class="text-3xl md:text-4xl font-bold text-black tracking-tight mb-2">{{ $facility->name }}</h1>
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 text-sm text-gray-700">
            @if($avgRating > 0)
                <div class="flex items-center font-bold text-black">
                    ★ {{ $avgRating }} <span class="text-gray-500 ml-1 font-normal underline">({{ $reviewCount }} reviews)</span>
                </div>
            @endif
            <div class="flex items-center">
                <span class="font-medium">{{ $facility->city }}</span>
                <span class="mx-2 text-gray-300">•</span>
                <span class="text-gray-600">{{ $facility->address }}</span>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        @if(session('error'))
            <div class="mb-8 border-l-4 border-black bg-gray-50 p-4">
                <p class="text-sm font-bold text-black">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Gallery --}}
        @php
            $images = $facility->courts->pluck('image_path')->filter();
            if ($facility->image_path) { $images = collect([$facility->image_path])->merge($images); }
            if ($images->isEmpty()) { $images = collect(['/images/sports-hall.jpg']); }
        @endphp
        @if($images->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-12">
            @foreach($images->take(2) as $index => $img)
                @php
                    $src = $img;
                    if ($src && !str_starts_with($src, '/') && !str_starts_with($src, 'http')) { $src = '/storage/' . $src; }
                @endphp
                <div class="aspect-[4/3] md:aspect-video bg-gray-100 border border-gray-200">
                    <img src="{{ $src }}" alt="Facility view" class="w-full h-full object-cover">
                </div>
            @endforeach
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            {{-- Main Content Column --}}
            <div class="lg:col-span-8 space-y-12">
                
                {{-- About --}}
                @if($facility->description)
                <div class="prose max-w-none text-gray-800">
                    <h2 class="text-xl font-bold text-black tracking-tight mb-4 border-b border-black pb-2">About</h2>
                    <p class="leading-relaxed">{{ $facility->description }}</p>
                </div>
                @endif

                {{-- Amenities --}}
                @if($facility->amenities->count() > 0)
                <div>
                    <h2 class="text-xl font-bold text-black tracking-tight mb-4 border-b border-black pb-2">Amenities</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-3 gap-x-4 text-sm text-gray-700">
                        @foreach($facility->amenities as $amenity)
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-black"></span>
                                {{ $amenity->name }}
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Book a Court --}}
                <div class="scroll-mt-6">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 border-b border-black pb-2 gap-4">
                        <h2 class="text-xl font-bold text-black tracking-tight">Availability</h2>
                        <div class="w-full sm:w-auto" wire:ignore>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Date</label>
                            <input type="text" 
                                   x-data="{ value: @entangle('selectedDate').live }" 
                                   x-init="flatpickr($el, { dateFormat: 'Y-m-d', minDate: 'today', defaultDate: value, onChange: function(selectedDates, dateStr) { value = dateStr; } })" 
                                   class="w-full sm:w-auto border border-gray-300 px-3 py-2 text-sm font-bold text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none placeholder-black cursor-pointer bg-white" 
                                   placeholder="Select date">
                        </div>
                    </div>

                    <div class="space-y-8">
                        @foreach($availableSlots as $courtId => $data)
                            <div class="border border-gray-200 p-5">
                                <div class="mb-4">
                                    <h3 class="font-bold text-black text-lg">{{ $data['court']['name'] }}</h3>
                                    <p class="text-sm text-gray-600">
                                        {{ $data['court']['type'] ?? 'Court' }} · {{ number_format($data['court']['base_price_per_hour'] / 100, 0) }} MKD / hr
                                    </p>
                                </div>
                                <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 gap-2">
                                    @foreach($data['slots'] as $slot)
                                        @if($slot['available'])
                                            <button wire:click="selectSlot(@js($courtId), @js($slot['start']))"
                                                    class="py-2 text-sm font-medium border border-black text-black hover:bg-black hover:text-white transition-colors">
                                                {{ $slot['start'] }}
                                                <span class="block text-[10px] font-normal">{{ number_format($slot['price'] / 100, 0) }} MKD</span>
                                            </button>
                                        @else
                                            <button disabled class="py-2 text-sm font-medium border border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed line-through">
                                                {{ $slot['start'] }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Reviews --}}
                @if($reviewCount > 0 || Auth::check())
                <div class="pt-8">
                    <h2 class="text-xl font-bold text-black tracking-tight mb-6 border-b border-black pb-2">Reviews</h2>
                    
                    @if($reviewCount > 0)
                        <div class="space-y-6 mb-10">
                            @foreach($reviews as $review)
                            <div class="border-b border-gray-200 pb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-black">{{ $review->author }}</span>
                                    <div class="text-sm font-bold text-black">★ {{ $review->rating }}</div>
                                </div>
                                @if($review->comment)
                                    <p class="text-sm text-gray-700 leading-relaxed">{{ $review->comment }}</p>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    @endif

                    @auth
                        <div class="border border-gray-200 p-6 bg-gray-50">
                            <h3 class="text-base font-bold text-black mb-4">Write a review</h3>
                            <livewire:leave-review :facilityId="$facility->id" />
                        </div>
                    @endauth
                </div>
                @endif
            </div>

            {{-- Sidebar Column --}}
            <div class="lg:col-span-4">
                <div class="border border-gray-300 p-6 sticky top-24">
                    <h3 class="font-bold text-black mb-4 border-b border-gray-200 pb-2 text-lg">Details</h3>
                    <ul class="space-y-4 text-sm text-gray-800">
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-500 mb-1">Hours</span>
                            {{ str_pad($facility->opening_hour, 2, '0', STR_PAD_LEFT) }}:00 – {{ str_pad($facility->closing_hour, 2, '0', STR_PAD_LEFT) }}:00
                        </li>
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-500 mb-1">Address</span>
                            {{ $facility->address }}<br>{{ $facility->city }}
                        </li>
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-500 mb-1">Total Courts</span>
                            {{ $facility->courts->count() }} available
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Booking Modal --}}
    @if($showBookingModal && $breakdown)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-on:keydown.escape.window="$wire.set('showBookingModal', false)">
            <div class="absolute inset-0 bg-black/60" wire:click="$set('showBookingModal', false)"></div>
            <div class="bg-white border border-gray-300 w-full max-w-md relative z-10 flex flex-col max-h-[90vh]"
                 role="dialog" aria-modal="true" aria-labelledby="booking-modal-title" x-data x-trap.noscroll="true">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 id="booking-modal-title" class="text-lg font-bold text-black tracking-tight">Confirm Booking</h3>
                    <button wire:click="$set('showBookingModal', false)" class="text-gray-500 hover:text-black" aria-label="Close">
                        ✕
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto">
                    <div class="mb-6">
                        <p class="font-bold text-black text-lg mb-1">{{ $selectedCourt?->name }}</p>
                        <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($selectedDate)->format('D, M j, Y') }} · {{ $selectedSlot['start'] ?? '' }} - {{ $selectedSlot['end'] ?? '' }}</p>
                    </div>

                    @if(count($rentals) > 0)
                    <div class="mb-6">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-100 pb-1">Extras</p>
                        <div class="space-y-2">
                            @foreach($rentals as $rental)
                                <label class="flex items-center justify-between p-3 border border-gray-200 cursor-pointer hover:border-black transition-colors {{ in_array($rental->id, $selectedRentals) ? 'border-black bg-gray-50' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" wire:click="toggleRental(@js($rental->id))" class="w-4 h-4 border-gray-300 rounded-none text-black focus:ring-black" {{ in_array($rental->id, $selectedRentals) ? 'checked' : '' }}>
                                        <span class="text-sm font-medium text-black">{{ $rental->name }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-black">+{{ number_format($rental->price / 100, 0) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-100 pb-1">Summary</p>
                        <div class="space-y-2 text-sm text-gray-700">
                            <div class="flex justify-between">
                                <span>Base Price</span>
                                <span>{{ number_format($breakdown['base'] / 100, 0) }} MKD</span>
                            </div>
                            @if($breakdown['peak'] > 0)
                                <div class="flex justify-between">
                                    <span>Peak Hours (+{{ \App\Models\Booking::PEAK_SURCHARGE * 100 }}%)</span>
                                    <span>+{{ number_format($breakdown['peak'] / 100, 0) }} MKD</span>
                                </div>
                            @endif
                            @if($breakdown['weekend'] > 0)
                                <div class="flex justify-between">
                                    <span>Weekend (+{{ \App\Models\Booking::WEEKEND_SURCHARGE * 100 }}%)</span>
                                    <span>+{{ number_format($breakdown['weekend'] / 100, 0) }} MKD</span>
                                </div>
                            @endif
                            @if($breakdown['rentals'] > 0)
                                <div class="flex justify-between border-t border-gray-100 pt-2">
                                    <span>Extras</span>
                                    <span>+{{ number_format($breakdown['rentals'] / 100, 0) }} MKD</span>
                                </div>
                            @endif
                            
                            <div class="flex justify-between items-center pt-4 mt-4 border-t border-black">
                                <span class="text-base font-bold text-black">Total</span>
                                <span class="text-xl font-bold text-black">{{ number_format($breakdown['total'] / 100, 0) }} MKD</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 pt-0 mt-auto">
                    @auth
                        <button wire:click="confirmBooking" wire:loading.attr="disabled" class="w-full h-12 bg-black text-white font-bold text-sm hover:bg-gray-800 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="confirmBooking">Confirm Reservation</span>
                            <span wire:loading wire:target="confirmBooking">Processing...</span>
                        </button>
                    @else
                        <a href="/login" class="flex w-full h-12 bg-black text-white font-bold text-sm hover:bg-gray-800 transition-colors items-center justify-center">Login to Book</a>
                    @endauth
                </div>
            </div>
        </div>
    @endif
</div>
