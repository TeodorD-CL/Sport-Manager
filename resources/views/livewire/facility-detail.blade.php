@push('head')
    <meta name="description" content="{{ Str::limit($facility->description ?? $facility->name . ' – book courts in ' . $facility->city . ' on Sport Manager.', 160) }}">
    <meta property="og:title" content="{{ $facility->name }} – Sport Manager">
@endpush

<div class="min-h-screen pb-16">
    {{-- Header Section --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 pb-6 border-b border-gray-200">
        <a href="/" class="inline-block mb-4 text-[13px] font-bold text-gray-600 hover:text-black transition-colors">&larr; All facilities</a>
        <h1 class="text-3xl md:text-4xl font-bold text-black tracking-tight mb-2">{{ $facility->name }}</h1>
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 text-sm text-gray-700">
            @if($avgRating > 0)
                <div class="flex items-center font-bold text-black">
                    ★ {{ $avgRating }} <span class="text-gray-600 ml-1 font-normal underline">({{ $reviewCount }} reviews)</span>
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
        @if($notice)
            <div class="mb-8 border-l-4 border-black bg-gray-50 p-4" role="status">
                <p class="text-sm font-bold text-black">{{ $notice }} <a href="#availability" class="underline">See free times</a></p>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-8 border-l-4 border-black bg-gray-50 p-4">
                <p class="text-sm font-bold text-black">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Gallery: phone shows the main photo, tablet two, desktop a mosaic of three; the rest are counted, not hidden silently. --}}
        @php
            $photoUrl = fn ($path) => str_starts_with($path, '/') || str_starts_with($path, 'http') ? $path : '/storage/' . $path;
            // Thumbnails ask Unsplash for a smaller copy, and use public/images/thumbs for our own photos.
            // ponytail: uploaded photos in /storage still load full size; resize on upload if they get heavy.
            $thumbUrl = function ($path) use ($photoUrl) {
                $url = str_replace('w=1600', 'w=200', $photoUrl($path));
                $thumb = str_replace('/images/', '/images/thumbs/', $url);
                return str_starts_with($url, '/images/') && file_exists(public_path($thumb)) ? $thumb : $url;
            };
            $photos = collect($facility->image_path ? [['src' => $photoUrl($facility->image_path), 'alt' => $facility->name]] : [])
                ->merge($facility->courts->filter(fn ($court) => $court->image_path)
                    ->map(fn ($court) => ['src' => $photoUrl($court->image_path), 'alt' => $court->name . ' at ' . $facility->name]))
                ->unique('src')
                ->values();
            if ($photos->isEmpty()) { $photos = collect([['src' => '/images/sports-hall.jpg', 'alt' => $facility->name]]); }
            $total = $photos->count();
            $tile = [
                0 => 'aspect-[4/3] ' . match (true) {
                    $total === 1 => 'sm:col-span-2 sm:aspect-[21/9] lg:aspect-auto',
                    $total === 2 => 'sm:aspect-video lg:aspect-auto',
                    default => 'sm:aspect-video lg:aspect-auto lg:col-span-2 lg:row-span-2',
                },
                1 => 'hidden sm:block aspect-video lg:aspect-auto',
                2 => 'hidden lg:block',
            ];
        @endphp
        <div x-data="{
                photos: @js($photos),
                open: false,
                i: 0,
                opener: null,
                startX: null,
                show(n) { this.opener = document.activeElement; this.i = n; this.open = true },
                close() { this.open = false; this.$nextTick(() => this.opener?.focus()) },
                prev() { this.i = (this.i + this.photos.length - 1) % this.photos.length },
                next() { this.i = (this.i + 1) % this.photos.length },
                swipe(endX) { const dx = endX - this.startX; if (this.startX !== null && Math.abs(dx) > 50) { dx < 0 ? this.next() : this.prev() } this.startX = null },
            }">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-12 lg:h-[420px] {{ $total > 2 ? 'lg:grid-cols-3 lg:grid-rows-2' : '' }}">
                @foreach($photos->take(3) as $index => $photo)
                    <button type="button" @click="show({{ $index }})" aria-label="Open photo {{ $index + 1 }} of {{ $total }}: {{ $photo['alt'] }}"
                            class="group relative overflow-hidden bg-gray-100 border border-gray-200 cursor-pointer {{ $tile[$index] }}">
                        <img src="{{ $photo['src'] }}" alt="" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" class="w-full h-full object-cover transition-opacity group-hover:opacity-90">
                        @if($index === 0 && $total > 1)
                            <span class="sm:hidden absolute bottom-3 right-3 bg-black/75 text-white text-xs font-bold px-2 py-1">1 / {{ $total }}</span>
                        @elseif($index === 1 && $total > 2)
                            <span class="lg:hidden absolute inset-0 flex items-center justify-center bg-black/65 text-white text-lg font-bold">+{{ $total - 2 }} {{ Str::plural('photo', $total - 2) }}</span>
                        @elseif($index === 2 && $total > 3)
                            <span class="absolute inset-0 flex items-center justify-center bg-black/65 text-white text-lg font-bold">+{{ $total - 3 }} {{ Str::plural('photo', $total - 3) }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Full-screen viewer, moved to <body> so it sits above the header and the chat button. --}}
            <template x-teleport="body">
                <div x-show="open" x-cloak x-trap.noscroll="open"
                     @keydown.escape.window="open && close()"
                     @keydown.arrow-left.window="open && prev()"
                     @keydown.arrow-right.window="open && next()"
                     @touchstart="startX = $event.touches[0].clientX"
                     @touchend="swipe($event.changedTouches[0].clientX)"
                     role="dialog" aria-modal="true" aria-label="Photos of {{ $facility->name }}"
                     class="fixed inset-0 z-[70] bg-black flex flex-col font-sans">
                    <div class="flex items-center justify-between gap-4 px-4 py-2 text-white">
                        <p class="text-sm font-bold truncate" aria-live="polite">
                            <span x-text="`${i + 1} / ${photos.length}`"></span> · <span x-text="photos[i].alt"></span>
                        </p>
                        <button type="button" @click="close()" class="w-11 h-11 flex items-center justify-center shrink-0 [@media(hover:hover)]:hover:bg-white/10" aria-label="Close photos">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="flex-1 min-h-0 flex items-center justify-center px-4">
                        <img :src="photos[i].src" :alt="photos[i].alt" class="max-w-full max-h-full object-contain">
                    </div>

                    <div x-show="photos.length > 1" class="flex items-center justify-center gap-4 px-4 py-4">
                        <button type="button" @click="prev()" class="w-11 h-11 flex items-center justify-center border border-white/40 text-white [@media(hover:hover)]:hover:bg-white/10" aria-label="Previous photo">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        {{-- Thumbnails only where there's room for them. --}}
                        <div class="hidden lg:flex gap-2">
                            <template x-for="(photo, n) in photos" :key="photo.src">
                                <button type="button" @click="i = n" :aria-label="`Show photo ${n + 1}`" :aria-current="i === n"
                                        class="w-16 h-12 overflow-hidden border-2" :class="i === n ? 'border-white' : 'border-transparent opacity-60 [@media(hover:hover)]:hover:opacity-100'">
                                    <img :src="photo.src" alt="" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                        <button type="button" @click="next()" class="w-11 h-11 flex items-center justify-center border border-white/40 text-white [@media(hover:hover)]:hover:bg-white/10" aria-label="Next photo">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

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
                <div id="availability" class="scroll-mt-6">
                    {{-- Below lg the wrapper dissolves (contents) so only the one-line date row stays pinned while scrolling slots. --}}
                    <div class="max-lg:contents flex items-end justify-between mb-6 border-b border-black pb-2 gap-4">
                        <h2 class="text-xl font-bold text-black tracking-tight max-lg:border-b max-lg:border-black max-lg:pb-2 max-lg:mb-2">Availability</h2>
                        <div class="max-lg:sticky max-lg:top-0 max-lg:z-20 max-lg:bg-[#f4f3ed] max-lg:py-2 max-lg:mb-4 max-lg:border-b max-lg:border-gray-300 max-lg:flex max-lg:items-center max-lg:gap-3 max-lg:[&>input]:flex-1" wire:ignore>
                            <label class="block shrink-0 text-xs font-bold text-gray-600 uppercase lg:mb-1">Select Date</label>
                            <input type="text" 
                                   x-data="{ value: @entangle('selectedDate').live }" 
                                   x-init="flatpickr($el, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'D, j M Y', minDate: 'today', defaultDate: value, onChange: function(selectedDates, dateStr) { value = dateStr; } })" 
                                   class="w-full lg:w-auto border border-gray-300 px-3 py-2 text-sm font-bold text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none placeholder-black cursor-pointer bg-white" 
                                   placeholder="Select date">
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-6">Pick a free time to book it. Prices include peak ({{ \App\Models\Booking::PEAK_START_HOUR }}:00–{{ \App\Models\Booking::PEAK_END_HOUR }}:00) and weekend surcharges.</p>

                    <div class="space-y-8">
                        @foreach($availableSlots as $courtId => $data)
                            {{-- Phones get one court open at a time instead of a page of slot buttons. --}}
                            <div class="border border-gray-200 p-5" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} || window.matchMedia('(min-width: 1024px)').matches }">
                                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="slots-{{ $courtId }}"
                                        class="w-full flex items-center gap-4 text-left" :class="open && 'mb-4'">
                                    @if($data['court']['image_path'])
                                        <img src="{{ $thumbUrl($data['court']['image_path']) }}" alt="" loading="lazy" width="80" height="80" class="w-16 h-16 sm:w-20 sm:h-20 object-cover border border-gray-200 shrink-0">
                                    @endif
                                    <span class="flex-1">
                                        <span class="block font-bold text-black text-lg">{{ $data['court']['name'] }}</span>
                                        <span class="block text-sm text-gray-600">
                                            {{ $data['court']['type'] ?? 'Court' }} · {{ number_format($data['court']['base_price_per_hour'] / 100, 0) }} MKD / hr
                                        </span>
                                    </span>
                                    <svg class="w-4 h-4 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div id="slots-{{ $courtId }}" x-show="open">
                                @if(empty($data['slots']))
                                    <p class="text-sm text-gray-600">No more times left on this day. Choose another date above.</p>
                                @endif
                                <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 gap-2">
                                    @foreach($data['slots'] as $slot)
                                        @if($slot['available'])
                                            <button wire:click="selectSlot(@js($courtId), @js($slot['start']))"
                                                    class="py-2 text-sm font-medium border border-black text-black hover:bg-black hover:text-white transition-colors {{ $slot['peak'] ? 'bg-[#e8e5dc]' : 'bg-white' }}">
                                                {{ $slot['start'] }}
                                                <span class="block text-xs font-normal">{{ number_format($slot['price'] / 100, 0) }} MKD</span>
                                                @if($slot['peak'])
                                                    <span class="block text-xs font-bold uppercase tracking-wider">Peak</span>
                                                @endif
                                            </button>
                                        @else
                                            <button disabled aria-label="{{ $slot['start'] }} booked" class="py-2 text-sm font-medium border border-gray-200 bg-gray-100 text-gray-600 cursor-not-allowed line-through">
                                                {{ $slot['start'] }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
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
            <div class="lg:col-span-4 order-first lg:order-none">
                <div class="border border-gray-300 bg-white p-6 sticky top-24">
                    <h3 class="font-bold text-black mb-4 border-b border-gray-200 pb-2 text-lg">Details</h3>
                    <ul class="space-y-4 text-sm text-gray-800">
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-600 mb-1">Hours</span>
                            {{ str_pad($facility->opening_hour, 2, '0', STR_PAD_LEFT) }}:00 – {{ str_pad($facility->closing_hour, 2, '0', STR_PAD_LEFT) }}:00
                        </li>
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-600 mb-1">Address</span>
                            {{ $facility->address }}<br>{{ $facility->city }}
                        </li>
                        <li class="flex flex-col">
                            <span class="font-bold text-xs uppercase text-gray-600 mb-1">Total Courts</span>
                            {{ $facility->courts->count() }} available
                        </li>
                    </ul>
                    <a href="#availability" class="mt-6 flex h-12 w-full items-center justify-center bg-black text-sm font-bold text-white hover:bg-gray-800 transition-colors">See free times</a>
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
                    <button wire:click="$set('showBookingModal', false)" class="-mr-2 w-10 h-10 flex items-center justify-center text-gray-600 hover:text-black" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto">
                    <div class="mb-6">
                        <p class="font-bold text-black text-lg mb-1">{{ $selectedCourt?->name }}</p>
                        <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($selectedDate)->format('D, j M Y') }} · {{ $selectedSlot['start'] ?? '' }} - {{ $selectedSlot['end'] ?? '' }}</p>
                    </div>

                    @if(count($rentals) > 0)
                    <div class="mb-6">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 border-b border-gray-100 pb-1">Extras</p>
                        <div class="space-y-2">
                            @foreach($rentals as $rental)
                                <label class="flex items-center justify-between p-3 border border-gray-200 cursor-pointer hover:border-black transition-colors {{ in_array($rental->id, $selectedRentals) ? 'border-black bg-gray-50' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" wire:click="toggleRental(@js($rental->id))" class="w-4 h-4 border-gray-300 rounded-none text-black focus:ring-black" {{ in_array($rental->id, $selectedRentals) ? 'checked' : '' }}>
                                        <span class="text-sm font-medium text-black">{{ $rental->name }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-black">+{{ number_format($rental->price / 100, 0) }} MKD</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div>
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 border-b border-gray-100 pb-1">Summary</p>
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
                        <button wire:click="loginToBook" class="flex w-full h-12 bg-black text-white font-bold text-sm hover:bg-gray-800 transition-colors items-center justify-center">Sign in to Book</button>
                    @endauth
                </div>
            </div>
        </div>
    @endif
</div>
