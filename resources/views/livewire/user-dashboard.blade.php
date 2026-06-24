<div class="max-w-7xl mx-auto px-6 pt-16 pb-16">
    
    {{-- Header --}}
    <div class="flex justify-between items-end mb-8">
        <div>
            <p class="text-[10px] font-bold text-gray-500 tracking-[0.1em] uppercase mb-4">
                Account &bull; {{ Auth::user()->hasAdminPanelAccess() ? 'Admin User' : 'Standard User' }}
            </p>
            <h1 class="text-[52px] leading-none font-bold text-[#111] tracking-tight mb-3">
                Bookings
            </h1>
            <p class="text-[15px] text-gray-600">
                Manage your upcoming and past reservations.
            </p>
        </div>
        
        <div class="text-right flex flex-col items-end pb-2">
            <span class="text-[40px] leading-none font-bold text-black">{{ $upcomingCount }}</span>
            <span class="text-[9px] font-bold text-gray-500 tracking-[0.1em] uppercase mt-1">Upcoming</span>
        </div>
    </div>
    
    <div class="w-full h-px bg-black mb-8"></div>

    {{-- Tabs --}}
    <div class="flex gap-8 mb-4">
        <button wire:click="$set('tab', 'upcoming')"
                class="pb-3 text-[13px] font-bold border-b-2 transition-colors {{ $tab === 'upcoming' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black' }}">
            Upcoming
        </button>
        <button wire:click="$set('tab', 'past')"
                class="pb-3 text-[13px] font-bold border-b-2 transition-colors {{ $tab === 'past' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black' }}">
            Past & cancelled
        </button>
    </div>
    
    <div class="w-full h-px bg-gray-300 mb-12"></div>

    {{-- Messages --}}
    @if(session('success'))
        <div class="mb-8 border border-black bg-white p-4">
            <p class="text-[13px] font-bold text-black">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-8 border border-[#d13a3a] bg-white p-4">
            <p class="text-[13px] font-bold text-[#d13a3a]">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Content --}}
    @if($bookings->isEmpty())
        <div class="bg-white border border-gray-200 p-24 flex flex-col items-center text-center">
            <div class="w-12 h-12 border border-black flex items-center justify-center mb-6">
                <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-[20px] font-bold text-black mb-2">No bookings yet</h3>
            <p class="text-[14px] text-gray-600 mb-8 max-w-sm">
                You don't have any {{ $tab }} reservations. Find a court and lock in your next session.
            </p>
            @if($tab === 'upcoming')
                <a href="/" class="bg-[#111] text-white px-8 py-3 text-[13px] font-bold hover:bg-black transition-colors">
                    Browse facilities
                </a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($bookings as $booking)
                <div class="bg-white border border-gray-200 p-6 flex flex-col md:flex-row gap-6">
                    <div class="flex-1 flex flex-col">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-[18px] text-black tracking-tight mb-1">{{ $booking->court->name }}</h3>
                                <a href="/facility/{{ $booking->court->facility->id }}" class="text-[13px] text-gray-500 hover:text-black hover:underline transition-colors">
                                    {{ $booking->court->facility->name }}
                                </a>
                            </div>
                            <div class="px-2 py-1 border border-black text-[9px] font-bold uppercase tracking-widest text-black">
                                {{ $booking->status }}
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Date</p>
                                <p class="text-[14px] font-bold text-black">{{ $booking->start_time->format('d M Y') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Time</p>
                                <p class="text-[14px] font-bold text-black">{{ $booking->start_time->format('H:i') }} &mdash; {{ $booking->end_time->format('H:i') }}</p>
                            </div>
                        </div>

                        <div class="w-full h-px bg-gray-200 mb-6 mt-auto"></div>
                        
                        <div class="flex justify-between items-end">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Total</p>
                                <p class="text-[16px] font-bold text-black">{{ explode(' ', $booking->formatted_total_price)[0] }} <span class="text-[10px] tracking-widest uppercase">MKD</span></p>
                            </div>

                            <div class="flex gap-3">
                                @if($booking->status === 'confirmed')
                                    <button wire:click="cancelBooking('{{ $booking->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Are you sure you want to cancel this reservation?"
                                            class="text-[11px] font-bold text-black uppercase tracking-widest hover:underline transition-all py-2">
                                        Cancel
                                    </button>
                                @endif
                                <a href="/facility/{{ $booking->court->facility->id }}" class="bg-[#111] text-white px-5 py-2 text-[11px] font-bold uppercase tracking-widest hover:bg-black transition-colors flex items-center">
                                    Book again
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- QR Code Column --}}
                    @if($booking->status !== 'cancelled')
                    <div class="flex flex-col items-center justify-center border-l border-gray-200 pl-6 shrink-0 hidden md:flex">
                        <div class="p-2 border border-black bg-white">
                            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->color(17, 17, 17)->generate($booking->qr_code) !!}
                        </div>
                        <span class="text-[9px] font-mono font-bold text-gray-500 uppercase mt-3 tracking-[0.2em]">{{ Str::limit($booking->qr_code, 8, '') }}</span>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
