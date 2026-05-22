<div>
    <section class="relative bg-gray-900 py-24">
        <div class="absolute inset-0">
            <img src="/images/hero-bg.jpg" alt="Sports Center" class="w-full h-full object-cover opacity-40">
        </div>
        <div class="relative max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-8">Sports Center Booking</h1>
            <div class="bg-white rounded-lg shadow-lg p-4 md:p-6 max-w-4xl mx-auto">
                <form wire:submit="search" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <select wire:model.live="city" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-3">
                        <option value="">Select Town</option>
                        @foreach($cities as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-3">
                        <option value="">All Types</option>
                        <option value="Football">Football</option>
                        <option value="Tennis">Tennis</option>
                        <option value="Padel">Padel</option>
                        <option value="Swimming">Swimming</option>
                    </select>
                    <input type="date" wire:model="date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-3">
                    <button type="submit" class="w-full bg-gray-900 text-white rounded-md px-6 py-3 font-semibold hover:bg-gray-800 transition flex items-center justify-center gap-2">
                        <svg wire:loading wire:target="search" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span>Search</span>
                    </button>
                </form>

                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex flex-wrap gap-2 items-center">
                        @if($amenities->isNotEmpty())
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Amenities:</span>
                            @foreach($amenities as $amenity)
                                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        wire:model.live="amenityFilters"
                                        value="{{ $amenity->id }}"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    <span class="text-sm text-gray-700 {{ in_array($amenity->id, $amenityFilters) ? 'font-semibold' : '' }}">
                                        {{ $amenity->name }}
                                    </span>
                                </label>
                            @endforeach
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <select wire:model.live="sort" class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 pl-3 pr-8">
                            <option value="">Sort: Default</option>
                            <option value="rating">Highest Rated</option>
                            <option value="reviews">Most Reviewed</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                        </select>

                        @if($city || $type || $date || !empty($amenityFilters) || $sort)
                            <button wire:click="clearFilters" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                                Clear all
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900">
                @if($city || $type || $date || !empty($amenityFilters))
                    Search Results
                    <span class="text-lg font-normal text-gray-500">({{ $facilities->total() }})</span>
                @else
                    Popular Facilities
                @endif
            </h2>
            <div wire:loading.flex class="items-center gap-2 text-sm text-gray-500">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Loading...
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" wire:loading.class="opacity-50">
            @forelse($facilities as $facility)
                <a href="/facility/{{ $facility->id }}" class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition group">
                    @php
                        $image = $facility->courts->first()?->image_path ?? $facility->image_path ?? '/images/sports-hall.jpg';
                        if ($image && !str_starts_with($image, '/') && !str_starts_with($image, 'http')) {
                            $image = '/storage/' . $image;
                        }
                    @endphp
                    <div class="aspect-video overflow-hidden">
                        <img src="{{ $image }}" alt="{{ $facility->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 text-lg">{{ $facility->name }}</h3>
                        <p class="text-gray-500 text-sm mt-1">{{ $facility->city }}</p>
                        <div class="flex items-center mt-2">
                            @php
                                $avg = round($facility->reviews_avg_rating ?? 0, 1);
                                $count = $facility->reviews_count ?? 0;
                            @endphp
                            <div class="flex items-center">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= round($avg) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                                <span class="ml-1 text-sm text-gray-600">{{ $avg > 0 ? $avg : 'No reviews' }} {{ $count > 0 ? "($count)" : '' }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1 mt-3">
                            @foreach($facility->amenities->take(3) as $amenity)
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">{{ $amenity->name }}</span>
                            @endforeach
                        </div>
                        <div class="mt-3 pt-3 border-t">
                            @php
                                $lowestPrice = $facility->courts->min('base_price_per_hour');
                            @endphp
                            <span class="text-lg font-bold text-indigo-600">{{ $lowestPrice ? number_format($lowestPrice / 100, 0) . ' MKD' : 'N/A' }}</span>
                            <span class="text-sm text-gray-500">/hr</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-16 flex flex-col items-center text-center">
                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                    <p class="text-xl font-semibold text-gray-600 mb-2">No facilities found</p>
                    <p class="text-gray-400 mb-6">Try adjusting your filters or searching in a different city.</p>
                    <button wire:click="clearFilters" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition font-medium">
                        Clear Filters
                    </button>
                </div>
            @endforelse
        </div>

        @if($facilities->hasPages())
            <div class="mt-8">
                {{ $facilities->links() }}
            </div>
        @endif
    </section>
</div>
