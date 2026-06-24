<?php

namespace App\Livewire;

use App\Models\Facility;
use App\Models\Booking;
use App\Models\Court;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;

class Chatbot extends Component
{
    public bool $isOpen = false;
    public string $message = "";
    public array $messages = [];
    public ?array $pendingBooking = null;

    public array $suggestions = [
        "🏸 Where can I play Padel?",
        "🎾 Do you have Tennis courts?",
        "📅 What are the cancellation rules?",
        "⚽ Football courts & prices",
    ];

    public function mount()
    {
        // Add welcome message
        $this->messages[] = [
            'sender' => 'bot',
            'text' => "👋 Hi! I'm **Sporty**, your sports facility assistant. Ask me anything about court availability, rentals, pricing, or locations!\n\nI can check real-time availability and prepare bookings for you directly in our chat!",
            'timestamp' => now()->format('H:i'),
        ];
    }

    public function toggleChat()
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->dispatch('scroll-to-bottom');
        }
    }

    public function sendPreset(string $text)
    {
        $this->message = $text;
        $this->sendMessage();
    }

    public function clearChat()
    {
        $this->messages = [];
        $this->messages[] = [
            'sender' => 'bot',
            'text' => "👋 Chat cleared! I'm ready for new questions. Ask me anything about our facilities and courts!",
            'timestamp' => now()->format('H:i'),
        ];
        $this->pendingBooking = null;
        $this->dispatch('scroll-to-bottom');
    }

    public function cancelPendingBooking()
    {
        $this->pendingBooking = null;
        $this->messages[] = [
            'sender' => 'bot',
            'text' => "❌ Booking proposal cancelled. Let me know if you want to search for other options!",
            'timestamp' => now()->format('H:i'),
        ];
        $this->dispatch('scroll-to-bottom');
    }

    public function confirmPendingBooking()
    {
        if (!$this->pendingBooking) {
            return;
        }

        if (!auth()->check()) {
            $this->messages[] = [
                'sender' => 'bot',
                'text' => "🔒 **Sign In Required:** Please [Sign In](/login) or [Register](/register) to complete your booking reservation.",
                'timestamp' => now()->format('H:i'),
            ];
            $this->pendingBooking = null;
            $this->dispatch('scroll-to-bottom');
            return;
        }

        $courtId = $this->pendingBooking['court_id'];
        $date = $this->pendingBooking['date'];
        $startTime = $this->pendingBooking['start_time'];
        $endTime = $this->pendingBooking['end_time'];

        $court = Court::find($courtId);
        if (!$court) {
            $this->messages[] = [
                'sender' => 'bot',
                'text' => "❌ **Error:** The selected court could not be found.",
                'timestamp' => now()->format('H:i'),
            ];
            $this->pendingBooking = null;
            $this->dispatch('scroll-to-bottom');
            return;
        }

        $start = Carbon::parse($date . ' ' . $startTime);
        $end = Carbon::parse($date . ' ' . $endTime);

        try {
            $booking = DB::transaction(function () use ($court, $start, $end) {
                // Double-booking check inside lock
                $isBooked = Booking::where('court_id', $court->id)
                    ->where('status', '!=', 'cancelled')
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start)
                    ->lockForUpdate()
                    ->exists();

                if ($isBooked) {
                    throw new \RuntimeException('slot_taken');
                }

                $totalPrice = Booking::calculatePrice($court, $start, $end, []);

                return Booking::create([
                    'user_id' => auth()->id(),
                    'court_id' => $court->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => 'confirmed',
                    'total_price' => $totalPrice,
                    'qr_code' => Str::uuid()->toString(),
                ]);
            });

            $this->messages[] = [
                'sender' => 'bot',
                'text' => "🎉 **Booking Confirmed!**\n\nYour slot at **{$this->pendingBooking['court_name']}** (at *{$this->pendingBooking['facility_name']}*) is reserved for **{$date}** from **{$startTime} to {$endTime}**.\n\nYou can view the details, QR code, or cancel your booking on your [Bookings Dashboard](/dashboard).",
                'timestamp' => now()->format('H:i'),
            ];

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'slot_taken') {
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "❌ **Slot Taken:** This slot was just booked by another user. Please check availability again.",
                    'timestamp' => now()->format('H:i'),
                ];
            } else {
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "❌ **Error:** Could not save your booking. Please try again.",
                    'timestamp' => now()->format('H:i'),
                ];
            }
        } catch (\Exception $e) {
            Log::error("Chatbot booking error: " . $e->getMessage());
            $this->messages[] = [
                'sender' => 'bot',
                'text' => "❌ **System Error:** Something went wrong during booking. Please try again.",
                'timestamp' => now()->format('H:i'),
            ];
        }

        $this->pendingBooking = null;
        $this->dispatch('scroll-to-bottom');
    }

    public function sendMessage()
    {
        $userMsg = trim($this->message);
        if (empty($userMsg)) {
            return;
        }

        // Reset pending booking proposal when user pivots to new input
        $this->pendingBooking = null;

        // Add user message to history
        $this->messages[] = [
            'sender' => 'user',
            'text' => $userMsg,
            'timestamp' => now()->format('H:i'),
        ];

        $this->message = "";
        $this->dispatch('scroll-to-bottom');

        // Add temporary 'typing' indicator
        $this->messages[] = [
            'sender' => 'bot',
            'text' => 'typing...',
            'timestamp' => now()->format('H:i'),
        ];

        // Trigger bot response processing asynchronously on the next request cycle
        $this->dispatch('process-bot-response', userMsg: $userMsg);
    }

    #[On('process-bot-response')]
    public function handleBotResponse(string $userMsg)
    {
        // Process response
        $botResponse = $this->getBotResponse($userMsg);

        // Remove the 'typing...' message
        if (end($this->messages)['text'] === 'typing...') {
            array_pop($this->messages);
        }

        // Add bot message
        $this->messages[] = [
            'sender' => 'bot',
            'text' => $botResponse,
            'timestamp' => now()->format('H:i'),
        ];

        $this->dispatch('scroll-to-bottom');
    }

    private function getBotResponse(string $userMsg): string
    {
        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            return $this->getMockResponse($userMsg);
        }

        // Prepare conversation contents for Gemini multi-turn format
        $contents = [];
        
        // Take last 10 messages (excluding typing indicator if any) for context history
        $history = array_filter($this->messages, fn($m) => $m['text'] !== 'typing...');
        $history = array_slice($history, -10);

        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['sender'] === 'user' ? 'user' : 'model',
                'parts' => [
                    ['text' => $msg['text']]
                ]
            ];
        }

        // Define functions tool declaration
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'checkAvailability',
                        'description' => 'Checks for available court slots on a specific date, optionally filtered by court type and city.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'date' => [
                                    'type' => 'STRING',
                                    'description' => 'The date to check in YYYY-MM-DD format. Must be today or in the future.'
                                ],
                                'court_type' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional type of court. Must be one of: Football, Tennis, Swimming, Padel.'
                                ],
                                'city' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional city name to filter, e.g., Skopje, Ohrid, Bitola.'
                                ]
                            ],
                            'required' => ['date']
                        ]
                    ],
                    [
                        'name' => 'prepareBooking',
                        'description' => 'Prepares a booking proposal for a specific court at a given date and time slot. This will trigger a physical confirmation card in the chat.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'court_id' => [
                                    'type' => 'STRING',
                                    'description' => 'The UUID of the court to book.'
                                ],
                                'date' => [
                                    'type' => 'STRING',
                                    'description' => 'The booking date in YYYY-MM-DD format.'
                                ],
                                'start_time' => [
                                    'type' => 'STRING',
                                    'description' => 'The start time of the 1-hour slot in HH:00 format (e.g. "18:00").'
                                ],
                                'end_time' => [
                                    'type' => 'STRING',
                                    'description' => 'The end time of the slot in HH:00 format (e.g. "19:00").'
                                ]
                            ],
                            'required' => ['court_id', 'date', 'start_time', 'end_time']
                        ]
                    ]
                ]
            ]
        ];

        try {
            $maxLoops = 3;
            $loopCount = 0;

            while ($loopCount < $maxLoops) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={$apiKey}", [
                    'contents' => $contents,
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $this->getSystemPrompt()]
                        ]
                    ],
                    'tools' => $tools,
                    'generationConfig' => [
                        'maxOutputTokens' => 600,
                        'temperature' => 0.7,
                    ]
                ]);

                if (!$response->successful()) {
                    Log::error("Gemini API Error: " . $response->body());
                    return "⚠️ I had trouble connecting to my AI processor (Status: " . $response->status() . "). Here is a quick query fallback:\n\n" . $this->getMockResponse($userMsg);
                }

                $data = $response->json();
                $firstPart = $data['candidates'][0]['content']['parts'][0] ?? null;

                if (isset($firstPart['functionCall'])) {
                    $call = $firstPart['functionCall'];
                    $name = $call['name'];
                    $args = $call['args'] ?? [];

                    // Run PHP implementation
                    $result = $this->executeFunction($name, $args);

                    // Add function call & output to the API history trace for context completion
                    $contents[] = [
                        'role' => 'model',
                        'parts' => [
                            $firstPart
                        ]
                    ];
                    $contents[] = [
                        'role' => 'function',
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $name,
                                    'response' => ['output' => $result]
                                ]
                            ]
                        ]
                    ];

                    $loopCount++;
                    continue; // Loop again to let Gemini read the result and construct its natural response
                }

                // Normal text response
                return $firstPart['text'] ?? 'Done!';
            }

            return "I completed checking that information. Let me know what you'd like to do next!";

        } catch (\Exception $e) {
            Log::error("Gemini Request Exception: " . $e->getMessage());
            return "⚠️ Connection lost. Here is what I can find from my offline system database:\n\n" . $this->getMockResponse($userMsg);
        }
    }

    private function executeFunction(string $name, array $args): string
    {
        if ($name === 'checkAvailability') {
            $date = $args['date'] ?? now()->format('Y-m-d');
            $courtType = $args['court_type'] ?? null;
            $city = $args['city'] ?? null;
            
            try {
                $slots = $this->checkAvailabilityInternal($date, $courtType, $city);
                if (empty($slots)) {
                    return "No slots are available on {$date} for the specified criteria.";
                }
                return json_encode(array_slice($slots, 0, 15));
            } catch (\Exception $e) {
                return "Error checking availability: " . $e->getMessage();
            }
        }
        
        if ($name === 'prepareBooking') {
            $courtId = $args['court_id'] ?? null;
            $date = $args['date'] ?? null;
            $startTime = $args['start_time'] ?? null;
            $endTime = $args['end_time'] ?? null;
            
            if (!$courtId || !$date || !$startTime || !$endTime) {
                return "Missing required booking details.";
            }
            
            try {
                return $this->prepareBookingInternal($courtId, $date, $startTime, $endTime);
            } catch (\Exception $e) {
                return "Error preparing booking: " . $e->getMessage();
            }
        }
        
        return "Unknown function: {$name}";
    }

    private function checkAvailabilityInternal(string $date, ?string $courtType, ?string $city): array
    {
        $query = Facility::with(['courts']);
        if ($city) {
            $query->where('city', 'like', "%{$city}%");
        }
        $facilities = $query->get();

        $availableSlots = [];
        $searchDate = Carbon::parse($date);
        
        foreach ($facilities as $facility) {
            $opening = (int)$facility->opening_hour;
            $closing = (int)$facility->closing_hour;
            
            $courts = $facility->courts;
            if ($courtType) {
                $courts = $courts->filter(fn($c) => strtolower($c->type) === strtolower($courtType));
            }
            
            foreach ($courts as $court) {
                // Find existing bookings for this court on this date
                $bookings = Booking::where('court_id', $court->id)
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('start_time', $searchDate)
                    ->get();
                    
                for ($hour = $opening; $hour < $closing; $hour++) {
                    $slotStart = Carbon::parse($date . ' ' . sprintf("%02d:00", $hour));
                    $slotEnd = Carbon::parse($date . ' ' . sprintf("%02d:00", $hour + 1));
                    
                    if ($slotStart->isPast()) {
                        continue; // Skip past slots
                    }
                    
                    // Check overlap
                    $overlap = $bookings->first(function ($b) use ($slotStart, $slotEnd) {
                        return $b->start_time < $slotEnd && $b->end_time > $slotStart;
                    });
                    
                    if (!$overlap) {
                        $price = Booking::calculatePrice($court, $slotStart, $slotEnd, []);
                        $availableSlots[] = [
                            'court_id' => $court->id,
                            'court_name' => $court->name,
                            'court_type' => $court->type,
                            'facility_name' => $facility->name,
                            'city' => $facility->city,
                            'start_time' => $slotStart->format('H:i'),
                            'end_time' => $slotEnd->format('H:i'),
                            'price' => number_format($price / 100, 0) . ' MKD'
                        ];
                    }
                }
            }
        }
        
        return $availableSlots;
    }

    private function prepareBookingInternal(string $courtId, string $date, string $startTime, string $endTime): string
    {
        $court = Court::with('facility')->find($courtId);
        if (!$court) {
            return "Court not found.";
        }
        
        $start = Carbon::parse($date . ' ' . $startTime);
        $end = Carbon::parse($date . ' ' . $endTime);
        
        if ($start->isPast()) {
            return "Cannot book a time slot in the past.";
        }
        
        // Check if already booked
        $isBooked = Booking::where('court_id', $courtId)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
            
        if ($isBooked) {
            return "This slot is no longer available.";
        }
        
        // Calculate price
        $price = Booking::calculatePrice($court, $start, $end, []);
        
        // Set pending booking state
        $this->pendingBooking = [
            'court_id' => $courtId,
            'court_name' => $court->name,
            'court_type' => $court->type,
            'facility_id' => $court->facility->id,
            'facility_name' => $court->facility->name,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $price,
            'price_formatted' => number_format($price / 100, 0) . ' MKD'
        ];
        
        return json_encode([
            'status' => 'prepared',
            'message' => "Booking prepared for {$court->name} at {$court->facility->name} on {$date} from {$startTime} to {$endTime}. Total cost: " . number_format($price / 100, 0) . " MKD. The user must now click the 'Confirm Booking' button in the chat interface to finalize the reservation.",
            'pending_booking' => $this->pendingBooking
        ]);
    }

    private function getSystemPrompt(): string
    {
        $facilities = Facility::with(['courts', 'amenities', 'rentals'])->get();
        
        $facilitiesText = "";
        foreach ($facilities as $facility) {
            $courtsInfo = $facility->courts->map(fn($c) => "- {$c->name} ({$c->type}): ID `{$c->id}`, Base price " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour")->join("\n");
            $rentalsInfo = $facility->rentals->map(fn($r) => "- {$r->name}: " . number_format($r->price / 100, 0) . " MKD" . ($r->suitable_for ? " (suitable for " . implode(', ', $r->suitable_for) . ")" : ""))->join("\n");
            $amenitiesInfo = $facility->amenities->pluck('name')->join(', ');
            
            $facilitiesText .= "Facility Name: {$facility->name}\n";
            $facilitiesText .= "City/Location: {$facility->city}, {$facility->address}\n";
            $facilitiesText .= "Working Hours: " . sprintf("%02d:00 - %02d:00", $facility->opening_hour, $facility->closing_hour) . "\n";
            $facilitiesText .= "Amenities: " . ($amenitiesInfo ?: 'None') . "\n";
            $facilitiesText .= "Courts:\n" . ($courtsInfo ?: 'No courts registered') . "\n";
            if ($rentalsInfo) {
                $facilitiesText .= "Rentals available:\n{$rentalsInfo}\n";
            }
            $facilitiesText .= "---------------------------------------\n";
        }
        
        $userText = "";
        if (auth()->check()) {
            $user = auth()->user();
            $userText .= "User Status: Authenticated\n";
            $userText .= "Name: {$user->name}\n";
            $userText .= "Email: {$user->email}\n";
            
            $bookings = Booking::where('user_id', $user->id)
                ->with(['court.facility'])
                ->latest()
                ->take(5)
                ->get();
                
            if ($bookings->isNotEmpty()) {
                $userText .= "Recent User Bookings:\n";
                foreach ($bookings as $booking) {
                    $rentalsText = $booking->rentals->map(fn($r) => "{$r->name} (Qty: {$r->pivot->quantity})")->join(', ');
                    $userText .= "- Booking ID: {$booking->id}\n";
                    $userText .= "  Court: {$booking->court->name} at {$booking->court->facility->name}\n";
                    $userText .= "  Time: {$booking->start_time->format('Y-m-d H:i')} to {$booking->end_time->format('Y-m-d H:i')}\n";
                    $userText .= "  Status: {$booking->status}\n";
                    $userText .= "  Total Price: " . number_format($booking->total_price / 100, 0) . " MKD\n";
                    if ($rentalsText) {
                        $userText .= "  Rentals: {$rentalsText}\n";
                    }
                }
            } else {
                $userText .= "User has no prior bookings.\n";
            }
        } else {
            $userText .= "User Status: Guest / Unauthenticated\n";
        }

        $todayDate = now()->format('Y-m-d');
        $currentTime = now()->format('H:i:s');
        $dayOfWeek = now()->format('l');

        return "You are 'Sporty', the official AI chatbot of Sportsalit (sports court booking platform in Macedonia).\n" .
               "Your character: Helpful, concise, uses emojis where appropriate, talks like a helpful sport facility manager. Keep answers brief (under 3 paragraphs if possible). Use markdown formatting.\n\n" .
               "CURRENT DATETIME INFO (Extremely Important):\n" .
               "- Today's Date: {$todayDate}\n" .
               "- Current Local Time: {$currentTime}\n" .
               "- Day of the week: {$dayOfWeek}\n" .
               "- Timezone: Europe/Skopje\n\n" .
               "IMPORTANT FOR BOOKINGS:\n" .
               "1. You have tools: checkAvailability and prepareBooking. Use them when users ask to search courts, find available slots, or request a booking.\n" .
               "2. NEVER make up court UUIDs. First call `checkAvailability` to get the list of available courts, their IDs, and times.\n" .
               "3. To prepare a booking, call `prepareBooking(court_id, date, start_time, end_time)`. This will show a physical confirmation card to the user so they can finalize the booking.\n" .
               "4. Explain to the user that they must click the 'Confirm Booking' button that just appeared in the chat window to finalize it.\n" .
               "5. Surcharges: 20% peak (18:00-22:00), 10% weekend. Stacking: peak is applied first, then weekend. Minimum 1 hour.\n" .
               "6. Cancellation: allowed only if start time is > 24 hours in the future.\n\n" .
               "CURRENT DATABASE FACILITIES DATA:\n" .
               $facilitiesText . "\n" .
               "CURRENT USER CONTEXT:\n" .
               $userText;
    }

    private function getMockResponse(string $userMessage): string
    {
        $msg = strtolower($userMessage);
        $facilities = Facility::with(['courts', 'rentals'])->get();
        
        if (str_contains($msg, 'padel')) {
            $matching = $facilities->filter(fn($f) => $f->courts->contains('type', 'Padel'));
            if ($matching->isEmpty()) {
                return "🏸 **Padel Courts:** Currently, we don't have any facilities with Padel courts registered in the system.";
            }
            $res = "🏸 **Padel Courts Available:**\n\n";
            foreach ($matching as $f) {
                $res .= "- **{$f->name}** in {$f->city} ({$f->address})\n";
                $padelCourts = $f->courts->where('type', 'Padel');
                foreach ($padelCourts as $c) {
                    $res .= "  - {$c->name}: " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour\n";
                }
            }
            $res .= "\n*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";
            return $res;
        }
        
        if (str_contains($msg, 'tennis')) {
            $matching = $facilities->filter(fn($f) => $f->courts->contains('type', 'Tennis'));
            if ($matching->isEmpty()) {
                return "🎾 **Tennis Courts:** No facilities with Tennis courts registered.";
            }
            $res = "🎾 **Tennis Courts Available:**\n\n";
            foreach ($matching as $f) {
                $res .= "- **{$f->name}** in {$f->city} ({$f->address})\n";
                $tennisCourts = $f->courts->where('type', 'Tennis');
                foreach ($tennisCourts as $c) {
                    $res .= "  - {$c->name}: " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour\n";
                }
            }
            $res .= "\n*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";
            return $res;
        }

        if (str_contains($msg, 'football') || str_contains($msg, 'soccer')) {
            $matching = $facilities->filter(fn($f) => $f->courts->contains('type', 'Football'));
            if ($matching->isEmpty()) {
                return "⚽ **Football Courts:** No facilities with Football courts registered.";
            }
            $res = "⚽ **Football Courts Available:**\n\n";
            foreach ($matching as $f) {
                $res .= "- **{$f->name}** in {$f->city} ({$f->address})\n";
                $footballCourts = $f->courts->where('type', 'Football');
                foreach ($footballCourts as $c) {
                    $res .= "  - {$c->name}: " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour\n";
                }
            }
            $res .= "\n*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";
            return $res;
        }

        if (str_contains($msg, 'pool') || str_contains($msg, 'swim')) {
            $matching = $facilities->filter(fn($f) => $f->courts->contains('type', 'Swimming'));
            if ($matching->isEmpty()) {
                return "🏊 **Swimming Pools:** No facilities with Swimming pools registered.";
            }
            $res = "🏊 **Swimming Pools Available:**\n\n";
            foreach ($matching as $f) {
                $res .= "- **{$f->name}** in {$f->city} ({$f->address})\n";
                $poolCourts = $f->courts->where('type', 'Swimming');
                foreach ($poolCourts as $c) {
                    $res .= "  - {$c->name}: " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour\n";
                }
            }
            $res .= "\n*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";
            return $res;
        }

        if (str_contains($msg, 'cancel') || str_contains($msg, 'rules') || str_contains($msg, 'cancellation')) {
            return "📅 **Booking & Cancellation Rules:**\n\n" .
                   "- **Slots:** 1-hour increments during facility opening hours.\n" .
                   "- **Cancellations:** Only allowed if the booking start time is **more than 24 hours in the future**.\n" .
                   "- **Surcharges:** +20% peak hours (18:00 - 22:00), +10% weekends. Stacks: peak is applied first, then weekend.\n\n" .
                   "*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";
        }

        if (str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'hey')) {
            return "👋 **Hello! I am Sporty, your AI assistant.**\n\n" .
                   "I can help you find facilities, courts, check prices, rentals, and understand cancellation rules.\n\n" .
                   "To enable the full AI experience with real-time conversations, please set the `GEMINI_API_KEY` key in your `.env` file.\n\n" .
                   "Ask me about: **Padel, Tennis, Football, Swimming, or Booking Rules**!";
        }

        return "🤖 **Sporty (Demo Mode)**\n\n" .
               "I received your message: *\"" . e($userMessage) . "\"*\n\n" .
               "To get a real AI response using LLMs, please add your `GEMINI_API_KEY` to the `.env` file in the project root.\n\n" .
               "You can test me by typing keywords like: **Padel**, **Tennis**, **Football**, **Swimming**, or **Cancel**!";
    }

    public static function parseMarkdown(string $text): string
    {
        $safeText = e($text);
        
        $safeText = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold">$1</strong>', $safeText);
        
        $safeText = preg_replace('/\*(.*?)\*/', '<em class="italic">$1</em>', $safeText);
        
        $safeText = preg_replace('/^\-\s+(.*)$/m', '<span class="block pl-3 text-gray-700 font-normal">• $1</span>', $safeText);
        
        $safeText = nl2br($safeText);
        
        return $safeText;
    }

    public function render()
    {
        return view('livewire.chatbot');
    }
}
