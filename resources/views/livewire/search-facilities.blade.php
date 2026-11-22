@push('head')
    <meta name="description" content="Book sports courts across North Macedonia by the hour: live availability for football, tennis, padel and swimming in Skopje, Bitola, Ohrid and more.">
@endpush

<div>
    <div class="max-w-7xl mx-auto px-6 pt-6 md:pt-16 pb-12">
        {{-- Hero --}}
        <div class="mb-6 md:mb-12">
            <p class="text-xs font-bold text-gray-600 tracking-[0.1em] uppercase mb-3 md:mb-6">
                Sports Facilities &bull; North Macedonia
            </p>
            <h1 class="text-[32px] md:text-[52px] leading-[1.05] font-bold text-[#111] tracking-[-0.03em] max-w-2xl mb-3 md:mb-6">
                Sports courts,<br>bookable by the hour.
            </h1>
            <p class="text-[15px] text-gray-600 leading-relaxed max-w-lg">
                Live availability for venues in {{ $cities->count() > 5 ? $cities->take(5)->join(', ') . ' and more' : $cities->join(', ', ' and ') }}.<br class="hidden md:block">
                Choose a slot, reserve it, and play &mdash; no calls, no waiting.
            </p>
        </div>

        {{-- Search Form --}}
        <div class="mb-8 md:mb-12 border-y border-black">
            <form wire:submit="search" class="grid grid-cols-2 md:grid-cols-[1fr_1fr_1fr_0.7fr_auto] md:divide-x divide-black [&>*]:border-black max-md:[&>*:not(:last-child)]:border-b max-md:[&>*:nth-child(odd):not(:last-child)]:border-r">
                
                {{-- City --}}
                <div class="relative py-2 md:py-3 px-4 flex flex-col justify-center" x-data="{ open: false }">
                    <label for="search-city" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">City</label>
                    <button type="button" id="search-city" @click="open = !open" @click.outside="open = false" class="w-full flex justify-between items-center bg-transparent text-[14px] font-bold text-black border-none p-0 text-left focus:outline-none">
                        <span>{{ $city ?: 'All cities' }}</span>
                        <svg class="w-2.5 h-2.5 text-black transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" x-cloak x-transition.opacity class="absolute top-full left-0 mt-0 w-full min-w-[200px] bg-[#f4f3ed] border border-black z-50">
                        <button type="button" wire:click="$set('city', '')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $city === '' ? 'bg-black text-white' : 'text-black' }}">All cities</button>
                        @foreach($cities as $c)
                            <button type="button" wire:click="$set('city', @js($c))" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black last:border-b-0 hover:bg-black hover:text-white transition-colors {{ $city === $c ? 'bg-black text-white' : 'text-black' }}">{{ $c }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Sport --}}
                <div class="relative py-2 md:py-3 px-4 flex flex-col justify-center" x-data="{ open: false }">
                    <label for="search-sport" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">Sport</label>
                    <button type="button" id="search-sport" @click="open = !open" @click.outside="open = false" class="w-full flex justify-between items-center bg-transparent text-[14px] font-bold text-black border-none p-0 text-left focus:outline-none">
                        <span>{{ $type ?: 'All sports' }}</span>
                        <svg class="w-2.5 h-2.5 text-black transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" x-cloak x-transition.opacity class="absolute top-full left-0 mt-0 w-full min-w-[200px] bg-[#f4f3ed] border border-black z-50">
                        <button type="button" wire:click="$set('type', '')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $type === '' ? 'bg-black text-white' : 'text-black' }}">All sports</button>
                        @foreach(\App\Enums\CourtType::cases() as $sport)
                            <button type="button" wire:click="$set('type', @js($sport->value))" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black last:border-b-0 hover:bg-black hover:text-white transition-colors {{ $type === $sport->value ? 'bg-black text-white' : 'text-black' }}">{{ $sport->value }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Date --}}
                <div class="relative py-2 md:py-3 px-4 flex flex-col justify-center" wire:ignore>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">Date</label>
                    <input type="text" data-search-date
                           x-data="{ value: @entangle('date').live }" 
                           x-init="flatpickr($el, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'D, j M Y', minDate: 'today', maxDate: @js(\App\Models\Booking::horizonEnd()->format('Y-m-d')), defaultDate: value, onChange: function(selectedDates, dateStr) { value = dateStr; } }); $el._flatpickr.altInput.setAttribute('aria-label', 'Date')" 
                           class="w-full bg-transparent text-[14px] font-bold text-black border-none p-0 focus:ring-0 cursor-pointer placeholder-black" 
                           placeholder="Select date">
                </div>

                {{-- Time: optional; without a date it means today. --}}
                <div class="relative py-2 md:py-3 px-4 flex flex-col justify-center">
                    <label for="search-time" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">Time</label>
                    <select id="search-time" wire:model.live="time" class="w-full bg-transparent text-[14px] font-bold text-black border-none p-0 focus:ring-0 cursor-pointer">
                        <option value="">Any time</option>
                        @for($hour = 6; $hour <= 23; $hour++)
                            <option value="{{ sprintf('%02d:00', $hour) }}">{{ sprintf('%02d:00', $hour) }}</option>
                        @endfor
                    </select>
                </div>

                {{-- Submit --}}
                <button type="submit" class="col-span-2 md:col-span-1 bg-[#111] text-white px-10 py-3 md:py-4 font-bold text-[13px] hover:bg-black transition-colors flex items-center justify-center">
                    Search
                </button>
            </form>
        </div>

        {{-- Filters & Sort --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
            <div class="flex items-center gap-4 flex-wrap">
                <span class="text-xs font-bold text-gray-600 uppercase tracking-widest">Amenities</span>
                <div class="flex flex-wrap gap-2">
                    @foreach($amenities as $amenity)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model.live="amenityFilters" value="{{ $amenity->id }}" class="sr-only peer">
                            <div class="px-3 py-1.5 text-[13px] border border-gray-300 text-gray-700 peer-checked:bg-[#111] peer-checked:text-white peer-checked:border-[#111] transition-colors">
                                {{ $amenity->name }}
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
            
            <div class="flex items-center gap-2" x-data="{ open: false }">
                <span class="text-xs font-bold text-gray-600 uppercase tracking-widest">Sort</span>
                <div class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 bg-transparent text-[13px] font-bold text-black border-none px-0 py-2 focus:outline-none">
                        @php
                            $sortLabels = [
                                '' => $searchTime ? 'Best match' : 'Name (A–Z)',
                                'rating' => 'Highest Rated',
                                'reviews' => 'Most Reviewed',
                                'price_asc' => 'Price: Low to High',
                                'price_desc' => 'Price: High to Low',
                            ];
                        @endphp
                        <span>{{ $sortLabels[$sort] ?? 'Recommended' }}</span>
                        <svg class="w-2.5 h-2.5 text-black transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-cloak x-transition.opacity class="absolute top-full right-0 mt-2 w-48 bg-[#f4f3ed] border border-black z-50 shadow-xl">
                        @foreach($sortLabels as $val => $label)
                            <button type="button" wire:click="$set('sort', @js($val))" @click="open = false" class="w-full text-left px-4 py-2 text-[12px] font-bold border-b border-black last:border-b-0 hover:bg-black hover:text-white transition-colors {{ $sort === $val ? 'bg-black text-white' : 'text-black' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Filters --}}
        @php
            $activeAmenities = $amenities->whereIn('id', $amenityFilters);
            $chip = 'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold bg-[#111] text-white hover:bg-gray-700 transition-colors';
        @endphp
        @if($city || $type || $date || $time || $activeAmenities->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 mb-8">
                <span class="text-xs font-bold text-gray-600 uppercase tracking-widest mr-2">Filters</span>
                @if($city)
                    <button type="button" wire:click="$set('city', '')" class="{{ $chip }}" aria-label="Remove filter {{ $city }}">{{ $city }} <span aria-hidden="true">&times;</span></button>
                @endif
                @if($type)
                    <button type="button" wire:click="$set('type', '')" class="{{ $chip }}" aria-label="Remove filter {{ $type }}">{{ $type }} <span aria-hidden="true">&times;</span></button>
                @endif
                @if($date)
                    <button type="button" x-on:click="document.querySelector('[data-search-date]')._flatpickr.clear(); $wire.set('date', '')" class="{{ $chip }}" aria-label="Remove filter {{ $dateLabel = rescue(fn () => \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('D, j M'), $date, false) }}">{{ $dateLabel }} <span aria-hidden="true">&times;</span></button>
                @endif
                @if($time)
                    <button type="button" wire:click="$set('time', '')" class="{{ $chip }}" aria-label="Remove filter {{ $time }}">{{ $date ? '' : 'Today ' }}{{ $time }} <span aria-hidden="true">&times;</span></button>
                @endif
                @foreach($activeAmenities as $amenity)
                    <button type="button" wire:click="$set('amenityFilters', @js(array_values(array_diff($amenityFilters, [$amenity->id]))))" class="{{ $chip }}" aria-label="Remove filter {{ $amenity->name }}">{{ $amenity->name }} <span aria-hidden="true">&times;</span></button>
                @endforeach
                <button type="button" x-on:click="document.querySelector('[data-search-date]')._flatpickr.clear(); $wire.clearFilters()" class="ml-2 text-xs font-bold text-black underline hover:no-underline">Clear all</button>
            </div>
        @endif

        {{-- Results Header --}}
        <div class="flex items-end gap-3 mb-4">
            <h2 class="text-xl font-bold text-black tracking-tight">Facilities</h2>
            <span class="text-xs text-gray-600 pb-0.5">{{ $facilities->total() }} {{ Str::plural('facility', $facilities->total()) }}</span>
        </div>
        <div class="w-full h-px bg-black mb-6"></div>

        {{-- Loading State Overlay --}}
        <div class="relative min-h-[400px]">
            <div wire:loading.flex class="absolute inset-0 bg-stone-bg/50 z-10 flex items-start justify-center pt-20">
                <span class="text-xs font-bold uppercase tracking-widest text-black">Updating...</span>
            </div>

            {{-- Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10" wire:loading.class="opacity-50">
                @forelse($facilities as $index => $facility)
                    @php
                        // With a sport chosen, the photo, price and free times are about that sport's courts.
                        $courts = $selectedSport ? $facility->courts->where('type', $selectedSport) : $facility->courts;
                        $image = ($selectedSport ? $courts->first(fn ($court) => $court->image_path)?->image_path : null)
                            ?? $facility->courts->first()?->image_path ?? $facility->image_path ?? '/images/sports-hall.jpg';
                        if ($image && !str_starts_with($image, '/') && !str_starts_with($image, 'http')) {
                            $image = '/storage/' . $image;
                        }
                        $avg = round($facility->reviews_avg_rating ?? 0, 1);
                        $minPrice = $courts->min('base_price_per_hour');
                        $sports = collect(\App\Enums\CourtType::cases())->pluck('value')->intersect($facility->courts->pluck('type'));
                        $free = $availability[$facility->id] ?? null;
                        $context = array_filter([
                            'date' => $searchDay?->format('Y-m-d'),
                            'sport' => $selectedSport,
                            'time' => $searchTime,
                        ]);
                    @endphp
                    <a href="/facility/{{ $facility->id }}{{ $context ? '?' . http_build_query($context) : '' }}" class="group block">
                        {{-- Image Box --}}
                        <div class="relative aspect-[4/3] bg-[#e8e5dc] mb-4 overflow-hidden border border-gray-200">
                            <img src="{{ str_replace('w=1600', 'w=800', $image) }}" alt="{{ $facility->name }}" width="800" height="600" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" class="w-full h-full object-cover">
                        </div>
                        
                        {{-- Content --}}
                        <div>
                            <div class="flex justify-between items-start mb-1">
                                <h3 class="font-bold text-[15px] text-black tracking-tight group-hover:underline">{{ $facility->name }}</h3>
                                @if($avg > 0)
                                    <div class="flex items-center gap-1 text-xs font-bold text-black">
                                        <svg class="w-3 h-3 text-[#d13a3a]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        {{ number_format($avg, 1) }} <span class="text-gray-600 font-normal">({{ $facility->reviews_count }})</span>
                                    </div>
                                @endif
                            </div>
                            
                            <p class="text-[13px] text-gray-600 mb-2">{{ $facility->city }}</p>
                            
                            <p class="text-xs text-gray-600 mb-4 uppercase tracking-wider">{{ $sports->join(' · ') }}</p>

                            @if($free && $searchTime)
                                <p class="text-[13px] font-bold text-black mb-3">
                                    @if($free['exact'] !== null)
                                        {{ $searchTime }} free &middot; {{ number_format($free['exact'] / 100, 0) }} MKD
                                    @else
                                        <span class="text-gray-600 font-normal">Not at {{ $searchTime }} &middot;</span> {{ implode(', ', $free['near']) }} free
                                    @endif
                                </p>
                            @elseif($free)
                                <p class="text-[13px] font-bold text-black mb-3">Free times on {{ $searchDay->format('D, j M') }}</p>
                            @endif

                            <div class="w-full h-px bg-gray-200 mb-3"></div>

                            <div class="flex justify-between items-baseline">
                                <div>
                                    @if($minPrice)
                                        <span class="text-xs font-bold text-gray-600 uppercase tracking-widest mr-1">{{ $selectedSport ?? 'Courts' }} from</span>
                                        <span class="text-[16px] font-bold text-black">{{ number_format($minPrice / 100, 0) }}</span>
                                        <span class="text-xs font-bold text-gray-600 uppercase tracking-widest ml-1">MKD / hr</span>
                                    @endif
                                </div>
                                <span class="text-xs font-bold text-black uppercase tracking-widest group-hover:tracking-[0.15em] transition-all">
                                    Book &rarr;
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full py-16 border-y border-black text-center">
                        <p class="text-sm font-bold text-black uppercase tracking-widest mb-2">No facilities found</p>
                        @if(($date || $time) && !$searchDay)
                            <p class="text-xs text-gray-600 mb-6">Bookings are open from today up to {{ \App\Models\Booking::horizonDays() }} days ahead. Pick a date in that range.</p>
                        @elseif($time)
                            <p class="text-xs text-gray-600 mb-6">Nothing is free at or within two hours of {{ $time }}. Try another time or date.</p>
                        @else
                            <p class="text-xs text-gray-600 mb-6">Adjust your search parameters to find available courts.</p>
                        @endif
                        <button x-on:click="document.querySelector('[data-search-date]')._flatpickr.clear(); $wire.clearFilters()" class="text-xs font-bold text-black underline hover:no-underline">
                            Clear filters
                        </button>
                    </div>
                @endforelse

                @if($facilities->count() % 3)
                    <div class="hidden lg:flex flex-col justify-center items-start border border-black p-8">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-widest mb-3">Not sure where to play?</p>
                        <p class="text-[20px] font-bold text-black tracking-tight leading-snug mb-6">Ask the assistant for free courts, prices and rules.</p>
                        <button type="button" x-on:click="$dispatch('open-chat')" class="bg-[#111] text-white px-6 py-3 font-bold text-[13px] hover:bg-black transition-colors">Open chat</button>
                    </div>
                @endif
            </div>
        </div>

        @if($facilities->hasPages())
            <div class="mt-12 pt-8 border-t border-black">
                {{ $facilities->links() }}
            </div>
        @endif
    </div>
</div>
