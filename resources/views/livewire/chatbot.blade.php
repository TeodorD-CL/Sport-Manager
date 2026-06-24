<div x-data="{ 
    scrollToBottom() { 
        const container = this.$refs.messagesContainer;
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
}" 
x-init="
    $watch('$wire.messages', () => { 
        $nextTick(() => scrollToBottom());
    });
    scrollToBottom();
"
@scroll-to-bottom.window="$nextTick(() => scrollToBottom())"
class="fixed bottom-6 right-6 z-50 font-sans">

    <!-- Floating Chat Toggle Button -->
    <button wire:click="toggleChat" 
            class="flex items-center justify-center w-14 h-14 bg-black text-[#f4f3ed] border-[3px] border-black rounded-none shadow-[4px_4px_0_rgba(0,0,0,1)] hover:shadow-[2px_2px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[4px] active:translate-y-[4px] active:shadow-none transition-all duration-150 focus:outline-none">
        @if($isOpen)
            <!-- Close Icon -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        @else
            <!-- Chat Bubble Icon -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        @endif
    </button>

    <!-- Chat Box Widget -->
    <div x-cloak 
         x-show="$wire.isOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="absolute bottom-20 right-0 w-[440px] max-w-[calc(100vw-2rem)] h-[620px] max-h-[calc(100vh-7rem)] bg-[#f4f3ed] border-[3px] border-black shadow-[6px_6px_0_rgba(0,0,0,1)] flex flex-col z-50">
        
        <!-- Header -->
        <div class="bg-black text-white px-4 py-3.5 flex justify-between items-center border-b-[3px] border-black">
            <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 bg-green-400 border border-black animate-pulse"></div>
                <span class="font-bold tracking-wider text-xs uppercase">Sporty Assistant</span>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Clear Button -->
                <button wire:click="clearChat" 
                        title="Clear history"
                        class="text-gray-400 hover:text-white transition-colors duration-150 focus:outline-none">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
                
                <!-- Close Button -->
                <button wire:click="toggleChat" 
                        class="text-gray-400 hover:text-white transition-colors duration-150 focus:outline-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div x-ref="messagesContainer" 
             class="flex-1 p-4 overflow-y-auto space-y-4 flex flex-col bg-[#faf9f5]">
            @foreach($messages as $msg)
                @if($msg['sender'] === 'user')
                    <!-- User Message -->
                    <div class="self-end max-w-[85%] bg-black text-white px-3.5 py-2.5 border-[2px] border-black shadow-[2px_2px_0_rgba(0,0,0,0.15)] rounded-none">
                        <p class="text-xs leading-relaxed font-medium select-text break-words">
                            {{ $msg['text'] }}
                        </p>
                        <span class="block text-[9px] text-gray-400 text-right mt-1.5 select-none">
                            {{ $msg['timestamp'] }}
                        </span>
                    </div>
                @else
                    <!-- Bot Message -->
                    <div class="self-start max-w-[85%] bg-white text-black px-3.5 py-2.5 border-[2px] border-black shadow-[2px_2px_0_rgba(0,0,0,0.1)] rounded-none">
                        @if($msg['text'] === 'typing...')
                            <!-- Typing Indicator -->
                            <div class="flex items-center gap-1 py-1 px-2">
                                <span class="w-1.5 h-1.5 bg-black rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-1.5 h-1.5 bg-black rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-1.5 h-1.5 bg-black rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        @else
                            <div class="text-xs leading-relaxed prose prose-sm select-text break-words">
                                {!! \App\Livewire\Chatbot::parseMarkdown($msg['text']) !!}
                            </div>
                            <span class="block text-[9px] text-gray-400 text-left mt-1.5 select-none">
                                {{ $msg['timestamp'] }}
                            </span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        @if($pendingBooking)
            <!-- Pending Booking Confirmation Card -->
            <div class="px-4 py-3 bg-[#e8e5dc] border-t-[2px] border-black flex flex-col gap-2">
                <div class="border-[2px] border-black bg-white p-3 shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-none">
                    <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Confirmation Request</div>
                    <div class="text-xs font-bold text-black mt-1 uppercase">{{ $pendingBooking['court_name'] }} ({{ $pendingBooking['court_type'] }})</div>
                    <div class="text-[11px] text-gray-700 font-semibold mt-0.5">{{ $pendingBooking['facility_name'] }}</div>
                    
                    <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-black/10 select-none">
                        <div>
                            <span class="text-[8px] text-gray-500 block uppercase font-bold tracking-wider">Date</span>
                            <span class="text-[11px] font-bold">{{ $pendingBooking['date'] }}</span>
                        </div>
                        <div>
                            <span class="text-[8px] text-gray-500 block uppercase font-bold tracking-wider">Time</span>
                            <span class="text-[11px] font-bold">{{ $pendingBooking['start_time'] }} - {{ $pendingBooking['end_time'] }}</span>
                        </div>
                    </div>
                    
                    <div class="mt-2 pt-2 border-t border-black/15 flex justify-between items-center select-none">
                        <span class="text-[11px] font-bold">Total Price:</span>
                        <span class="text-xs font-black text-black bg-yellow-200 border border-black px-1.5 py-0.5">{{ $pendingBooking['price_formatted'] }}</span>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button wire:click="confirmPendingBooking" 
                            class="flex-1 bg-black hover:bg-gray-800 text-white font-bold text-xs py-2 border-[2px] border-black shadow-[2px_2px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all duration-150 rounded-none">
                        Confirm Booking
                    </button>
                    <button wire:click="cancelPendingBooking" 
                            class="bg-white hover:bg-gray-100 text-black font-bold text-xs py-2 px-3.5 border-[2px] border-black shadow-[2px_2px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all duration-150 rounded-none">
                        Cancel
                    </button>
                </div>
            </div>
        @endif

        <!-- Suggestion Chips -->
        <div class="px-4 py-2 bg-[#f4f3ed] border-t-[2px] border-black flex flex-wrap gap-1.5 select-none">
            @foreach($suggestions as $suggestion)
                <button wire:click="sendPreset('{{ addslashes($suggestion) }}')"
                        class="bg-white hover:bg-black hover:text-white text-black font-semibold text-[10px] py-1.5 px-2.5 border border-black transition-all duration-150 active:translate-y-0.5">
                    {{ $suggestion }}
                </button>
            @endforeach
        </div>

        <!-- Input Form -->
        <form wire:submit.prevent="sendMessage" 
              class="p-3 bg-white border-t-[2px] border-black flex gap-2">
            <input type="text" 
                   wire:model="message" 
                   placeholder="Ask Sporty about courts, rules, prices..." 
                   class="flex-1 px-3 py-2 text-xs border-[2px] border-black bg-[#f4f3ed] text-black placeholder-gray-500 focus:bg-white focus:outline-none rounded-none" 
                   required>
            <button type="submit" 
                    class="bg-black hover:bg-gray-800 text-white font-bold text-xs px-4 py-2 border-[2px] border-black hover:shadow-[1px_1px_0_rgba(0,0,0,1)] active:translate-y-0.5 transition-all rounded-none">
                Send
            </button>
        </form>

        <!-- API status info -->
        @if(!config('services.gemini.key') && !env('GEMINI_API_KEY'))
            <div class="bg-amber-100 text-amber-900 border-t border-black px-3 py-1.5 text-[9px] font-semibold text-center select-none">
                💡 Demo Mode active. Set <code class="bg-amber-200 px-1 py-0.5 rounded">GEMINI_API_KEY</code> in `.env` to enable full smart AI.
            </div>
        @endif
    </div>
</div>
