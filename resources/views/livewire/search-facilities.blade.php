<div>
    <div class="max-w-7xl mx-auto px-6 pt-16 pb-12">
        {{-- Hero --}}
        <div class="mb-12">
            <p class="text-[10px] font-bold text-gray-500 tracking-[0.1em] uppercase mb-6">
                Sports Facilities &bull; North Macedonia
            </p>
            <h1 class="text-[52px] leading-[1.05] font-bold text-[#111] tracking-[-0.03em] max-w-2xl mb-6">
                Every court in the country,<br>bookable by the hour.
            </h1>
            <p class="text-[15px] text-gray-600 leading-relaxed max-w-lg">
                Live availability across arenas, halls and pools. Choose a slot,<br>
                reserve it, and play &mdash; no calls, no waiting.
            </p>
        </div>

        {{-- Search Form --}}
        <div class="mb-12 border-y border-black">
            <form wire:submit="search" class="grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] divide-y md:divide-y-0 md:divide-x divide-black">
                
                {{-- City --}}
                <div class="relative py-3 px-4 flex flex-col justify-center" x-data="{ open: false }">
                    <label class="block text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">City</label>
                    <button type="button" @click="open = !open" @click.outside="open = false" class="w-full flex justify-between items-center bg-transparent text-[14px] font-bold text-black border-none p-0 text-left focus:outline-none">
                        <span>{{ $city ?: 'All cities' }}</span>
                        <svg class="w-2.5 h-2.5 text-black transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" x-cloak x-transition.opacity class="absolute top-full left-0 mt-0 w-full min-w-[200px] bg-[#f4f3ed] border border-black z-50">
                        <button type="button" wire:click="$set('city', '')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $city === '' ? 'bg-black text-white' : 'text-black' }}">All cities</button>
                        @foreach($cities as $c)
                            <button type="button" wire:click="$set('city', '{{ $c }}')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black last:border-b-0 hover:bg-black hover:text-white transition-colors {{ $city === $c ? 'bg-black text-white' : 'text-black' }}">{{ $c }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Sport --}}
                <div class="relative py-3 px-4 flex flex-col justify-center" x-data="{ open: false }">
                    <label class="block text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Sport</label>
                    <button type="button" @click="open = !open" @click.outside="open = false" class="w-full flex justify-between items-center bg-transparent text-[14px] font-bold text-black border-none p-0 text-left focus:outline-none">
                        <span>{{ $type ?: 'All sports' }}</span>
                        <svg class="w-2.5 h-2.5 text-black transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" x-cloak x-transition.opacity class="absolute top-full left-0 mt-0 w-full min-w-[200px] bg-[#f4f3ed] border border-black z-50">
                        <button type="button" wire:click="$set('type', '')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $type === '' ? 'bg-black text-white' : 'text-black' }}">All sports</button>
                        <button type="button" wire:click="$set('type', 'Football')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $type === 'Football' ? 'bg-black text-white' : 'text-black' }}">Football</button>
                        <button type="button" wire:click="$set('type', 'Tennis')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $type === 'Tennis' ? 'bg-black text-white' : 'text-black' }}">Tennis</button>
                        <button type="button" wire:click="$set('type', 'Padel')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold border-b border-black hover:bg-black hover:text-white transition-colors {{ $type === 'Padel' ? 'bg-black text-white' : 'text-black' }}">Padel</button>
                        <button type="button" wire:click="$set('type', 'Swimming')" @click="open = false" class="w-full text-left px-4 py-3 text-[13px] font-bold hover:bg-black hover:text-white transition-colors {{ $type === 'Swimming' ? 'bg-black text-white' : 'text-black' }}">Swimming</button>
                    </div>
                </div>

                {{-- Date --}}
                <div class="relative py-3 px-4" wire:ignore>
                    <label class="block text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Date</label>
                    <input type="text" 
                           x-data="{ value: @entangle('date') }" 
                           x-init="flatpickr($el, { dateFormat: 'Y-m-d', minDate: 'today', defaultDate: value, onChange: function(selectedDates, dateStr) { value = dateStr; } })" 
                           class="w-full bg-transparent text-[14px] font-bold text-black border-none p-0 focus:ring-0 cursor-pointer placeholder-black" 
                           placeholder="Select date">
                </div>

                {{-- Submit --}}
                <button type="submit" class="bg-[#111] text-white px-10 py-4 font-bold text-[13px] hover:bg-black transition-colors flex items-center justify-center">
                    Search
                </button>
            </form>
        </div>

        {{-- Filters & Sort --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
            <div class="flex items-center gap-4 flex-wrap">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Amenities</span>
                <div class="flex flex-wrap gap-2">
                    @foreach($amenities as $amenity)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model.live="amenityFilters" value="{{ $amenity->id }}" class="sr-only peer">
                            <div class="px-3 py-1 text-[11px] border border-gray-300 text-gray-700 peer-checked:bg-[#111] peer-checked:text-white peer-checked:border-[#111] transition-colors">
                                {{ $amenity->name }}
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
            
            <div class="flex items-center gap-2" x-data="{ open: false }">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Sort</span>
                <div class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 bg-transparent text-[13px] font-bold text-black border-none p-0 focus:outline-none">
                        @php
                            $sortLabels = [
                                '' => 'Recommended',
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
                            <button type="button" wire:click="$set('sort', '{{ $val }}')" @click="open = false" class="w-full text-left px-4 py-2 text-[12px] font-bold border-b border-black last:border-b-0 hover:bg-black hover:text-white transition-colors {{ $sort === $val ? 'bg-black text-white' : 'text-black' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Results Header --}}
        <div class="flex items-end gap-3 mb-4">
            <h2 class="text-xl font-bold text-black tracking-tight">Facilities</h2>
            <span class="text-[11px] text-gray-500 pb-0.5">{{ str_pad($facilities->total(), 2, '0', STR_PAD_LEFT) }} available</span>
        </div>
        <div class="w-full h-px bg-black mb-6"></div>

        {{-- Loading State Overlay --}}
        <div class="relative min-h-[400px]">
            <div wire:loading.flex class="absolute inset-0 bg-stone-bg/50 z-10 flex items-start justify-center pt-20">
                <span class="text-[11px] font-bold uppercase tracking-widest text-black">Updating...</span>
            </div>

            {{-- Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10" wire:loading.class="opacity-50">
                @forelse($facilities as $index => $facility)
                    @php
                        $image = $facility->courts->first()?->image_path ?? $facility->image_path ?? '/images/sports-hall.jpg';
                        if ($image && !str_starts_with($image, '/') && !str_starts_with($image, 'http')) {
                            $image = '/storage/' . $image;
                        }
                        $avg = round($facility->reviews_avg_rating ?? 0, 1);
                        
                    @endphp
                    <a href="/facility/{{ $facility->id }}" class="group block">
                        {{-- Image Box --}}
                        <div class="relative aspect-[4/3] bg-[#e8e5dc] mb-4 overflow-hidden border border-gray-200">
                            <img src="{{ $image }}" alt="{{ $facility->name }}" class="w-full h-full object-cover">
                            


                        </div>
                        
                        {{-- Content --}}
                        <div>
                            <div class="flex justify-between items-start mb-1">
                                <h3 class="font-bold text-[15px] text-black tracking-tight group-hover:underline">{{ $facility->name }}</h3>
                                @if($avg > 0)
                                    <div class="flex items-center gap-1 text-[11px] font-bold text-black">
                                        <svg class="w-3 h-3 text-[#d13a3a]" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        {{ number_format($avg, 1) }} <span class="text-gray-400 font-normal">({{ $facility->reviews_count }})</span>
                                    </div>
                                @endif
                            </div>
                            
                            <p class="text-[12px] text-gray-500 mb-2">{{ $facility->city }}</p>
                            
                            <div class="flex flex-wrap gap-2 text-[10px] text-gray-500 mb-4 uppercase tracking-widest">
                                @php $amenitiesStr = $facility->amenities->take(3)->pluck('name')->join(' &nbsp;&bull;&nbsp; ') @endphp
                                {!! $amenitiesStr !!}
                                @if($facility->amenities->count() > 3)
                                    &nbsp;&bull;&nbsp; +{{ $facility->amenities->count() - 3 }}
                                @endif
                            </div>

                            <div class="w-full h-px bg-gray-200 mb-3"></div>

                            <div class="flex justify-between items-baseline">
                                <div>
                                    <span class="text-[16px] font-bold text-black">{{ explode(' ', $facility->lowest_price_formatted)[0] }}</span>
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest ml-1">MKD / hr</span>
                                </div>
                                <span class="text-[11px] font-bold text-black uppercase tracking-widest group-hover:tracking-[0.15em] transition-all">
                                    Book &rarr;
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full py-16 border-y border-black text-center">
                        <p class="text-sm font-bold text-black uppercase tracking-widest mb-2">No facilities found</p>
                        <p class="text-xs text-gray-500 mb-6">Adjust your search parameters to find available courts.</p>
                        <button wire:click="clearFilters" class="text-xs font-bold text-black underline hover:no-underline">
                            Clear filters
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        @if($facilities->hasPages())
            <div class="mt-12 pt-8 border-t border-black">
                {{ $facilities->links() }}
            </div>
        @endif
    </div>
</div>
