<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Bookings</h1>
    <p class="text-gray-500 mb-8">Manage your upcoming and past reservations.</p>

    @if(session('success'))
        <div class="flash-message mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="flash-message mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 mb-6">
        <button
            wire:click="$set('tab', 'upcoming')"
            class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px {{ $tab === 'upcoming' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            Upcoming
            @if($upcomingCount > 0)
                <span class="ml-1.5 bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $upcomingCount }}</span>
            @endif
        </button>
        <button
            wire:click="$set('tab', 'past')"
            class="px-5 py-3 text-sm font-medium border-b-2 transition -mb-px {{ $tab === 'past' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            Past & Cancelled
            @if($pastCount > 0)
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $pastCount }}</span>
            @endif
        </button>
    </div>

    @if($bookings->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            @if($tab === 'upcoming')
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-lg font-semibold text-gray-600 mb-2">No upcoming bookings</p>
                <p class="text-gray-400 mb-6">Book a court and it will appear here.</p>
                <a href="/" class="inline-block bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition font-medium">
                    Browse Facilities
                </a>
            @else
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-lg font-semibold text-gray-600 mb-2">No past bookings yet</p>
                <p class="text-gray-400">Your completed and cancelled bookings will appear here.</p>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach($bookings as $booking)
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="font-bold text-lg text-gray-900">{{ $booking->court->name }}</h3>
                                @php
                                    $statusColors = [
                                        'confirmed'  => 'bg-green-100 text-green-800',
                                        'cancelled'  => 'bg-red-100 text-red-800',
                                        'completed'  => 'bg-blue-100 text-blue-800',
                                        'checked_in' => 'bg-amber-100 text-amber-800',
                                        'pending'    => 'bg-yellow-100 text-yellow-800',
                                    ];
                                    $color = $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $color }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                            </div>
                            <p class="text-sm text-gray-500 mb-2">
                                <a href="/facility/{{ $booking->court->facility->id }}" class="hover:text-indigo-600 transition">
                                    {{ $booking->court->facility->name }}
                                </a>
                            </p>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p><span class="font-medium">Date:</span> {{ $booking->start_time->format('D, M j, Y') }}</p>
                                <p><span class="font-medium">Time:</span> {{ $booking->start_time->format('H:i') }} – {{ $booking->end_time->format('H:i') }}</p>
                                <p><span class="font-medium">Total:</span> {{ number_format($booking->total_price / 100, 0) }} MKD</p>
                            </div>
                        </div>

                        @if($booking->status !== 'cancelled')
                            <div class="flex flex-col items-center gap-2">
                                <div class="bg-white p-2 rounded-lg border">
                                    {!! QrCode::size(100)->generate($booking->qr_code) !!}
                                </div>
                                <span class="text-xs text-gray-400 font-mono">{{ Str::limit($booking->qr_code, 13) }}</span>
                            </div>
                        @endif

                        <div class="flex flex-col gap-2 min-w-[130px]">
                            @if($booking->status === 'confirmed' && $booking->start_time->gt(now()->addHours(24)))
                                <button
                                    wire:click="cancelBooking('{{ $booking->id }}')"
                                    wire:loading.attr="disabled"
                                    wire:confirm="Are you sure you want to cancel this booking?"
                                    class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 disabled:opacity-50 transition text-sm font-medium flex items-center justify-center gap-1"
                                >
                                    <svg wire:loading wire:target="cancelBooking('{{ $booking->id }}')" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Cancel Booking
                                </button>
                            @endif
                            <a href="/facility/{{ $booking->court->facility->id }}" class="text-center text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-md hover:bg-indigo-50 transition font-medium">
                                Book Again
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
