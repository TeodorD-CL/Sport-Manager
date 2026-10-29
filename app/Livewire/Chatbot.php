<?php

namespace App\Livewire;

use App\Enums\CourtType;
use App\Exceptions\BookingException;
use App\Models\Facility;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Rental;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;

class Chatbot extends Component
{
    private const MAX_MESSAGE_LENGTH = 500;
    private const MAX_HISTORY = 50;
    private const REPLIES_PER_MINUTE = 10;

    public string $message = "";

    // Locked: the history is sent to the model and the pending booking is booked as-is, so the browser must not edit them.
    #[Locked]
    public array $messages = [];

    #[Locked]
    public ?array $pendingBooking = null;

    public array $suggestions = [
        "Where can I play Padel?",
        "Do you have Tennis courts?",
        "What are the cancellation rules?",
        "Football courts & prices",
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
        $this->addBotMessage("❌ Booking proposal cancelled. Let me know if you want to search for other options!");
    }

    public function confirmPendingBooking()
    {
        if (!$this->pendingBooking) {
            return;
        }

        if (!auth()->check()) {
            $this->pendingBooking = null;
            $this->addBotMessage("🔒 **Sign In Required:** Please [Sign In](/login) or [Register](/register) to complete your booking reservation.");
            return;
        }

        $pending = $this->pendingBooking;
        $this->pendingBooking = null;

        $court = Court::with('facility')->find($pending['court_id']);
        $slot = Booking::parseSlot($pending['date'], $pending['start_time'], $pending['end_time']);

        try {
            if (!$court || !$slot) {
                throw new BookingException('The selected court could not be found.');
            }

            // Checked again here: the slot may have passed or been taken since it was proposed.
            Booking::book($court, $slot[0], $slot[1], auth()->user());
        } catch (BookingException $e) {
            $this->addBotMessage("❌ **Booking failed:** {$e->getMessage()} Please check availability again.");
            return;
        } catch (\Exception $e) {
            Log::error("Chatbot booking error: " . $e->getMessage());
            $this->addBotMessage("❌ **System Error:** Something went wrong during booking. Please try again.");
            return;
        }

        $this->addBotMessage("🎉 **Booking Confirmed!**\n\nYour slot at **{$pending['court_name']}** (at *{$pending['facility_name']}*) is reserved for **{$pending['date']}** from **{$pending['start_time']} to {$pending['end_time']}**.\n\nYou can view the details, QR code, or cancel your booking on your [Bookings Dashboard](/dashboard).");
    }

    public function sendMessage()
    {
        $this->validate(['message' => ['required', 'string', 'max:' . self::MAX_MESSAGE_LENGTH]]);

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

        // Add temporary 'typing' indicator (only one, even if the previous reply is still pending)
        $this->removeTypingIndicator();
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
        // This is the expensive call (up to 3 LLM requests), and the browser can trigger it directly.
        $limiterKey = 'chatbot:' . (auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($limiterKey, self::REPLIES_PER_MINUTE)) {
            $botResponse = "⏳ You're sending messages too quickly. Please wait a minute and try again.";
        } else {
            RateLimiter::hit($limiterKey);
            $botResponse = $this->getBotResponse(mb_substr($userMsg, 0, self::MAX_MESSAGE_LENGTH));
        }

        $this->removeTypingIndicator();
        $this->addBotMessage($botResponse);
    }

    private function removeTypingIndicator(): void
    {
        $this->messages = array_values(array_filter($this->messages, fn ($m) => $m['text'] !== 'typing...'));
    }

    private function addBotMessage(string $text): void
    {
        $this->messages[] = [
            'sender' => 'bot',
            'text' => $text,
            'timestamp' => now()->format('H:i'),
        ];
        $this->messages = array_slice($this->messages, -self::MAX_HISTORY);

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
            $role = $msg['sender'] === 'user' ? 'user' : 'model';
            // Gemini expects user and model turns to alternate, so back-to-back messages share one turn.
            if ($contents && end($contents)['role'] === $role) {
                $contents[array_key_last($contents)]['parts'][] = ['text' => $msg['text']];
                continue;
            }
            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['text']]]];
        }

        // Define functions tool declaration
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'checkAvailability',
                        'description' => 'Lists free one-hour slots per court on a date, optionally filtered by sport, city, facility and start time. Returns every matching court with all of its free start times.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'date' => [
                                    'type' => 'STRING',
                                    'description' => 'The date to check in YYYY-MM-DD format. Must be today or in the future.'
                                ],
                                'court_type' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional type of court. Must be one of: ' . implode(', ', CourtType::options()) . '.'
                                ],
                                'city' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional city name to filter, e.g., Skopje, Ohrid, Bitola.'
                                ],
                                'facility_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional facility name or part of it, e.g. "Prilep Professional Arena" or "Arena".'
                                ],
                                'start_time' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional start time in HH:00 format to only return courts free at that hour, e.g. "20:00".'
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
            $model = config('services.gemini.model');
            // PHP kills a request after 30s, so every Gemini call must finish within 25s of the first one.
            $deadline = now()->addSeconds(25);

            while ($loopCount < $maxLoops) {
                $left = now()->diffInSeconds($deadline);
                if ($left < 2) {
                    return "⏳ That took too long. Here is what I can find from my offline system database:\n\n" . $this->getMockResponse($userMsg);
                }
                $timeout = min(20, $left);

                // The key goes in a header: URLs end up in exception messages and logs.
                // Gemini usually answers in about 1s but often spikes past 10s or returns 503 "high demand",
                // so allow up to 20s and retry once after a short pause if the retry still fits before the deadline.
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])->connectTimeout(min(5, $timeout))->timeout($timeout)->retry(2, 1000, fn ($e) => ($e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())))
                    && now()->diffInSeconds($deadline) > $timeout + 1, throw: false
                )->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
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

                // A reply can mix text and function calls in any order, so look at every part.
                $parts = $response->json('candidates.0.content.parts') ?? [];
                $calls = array_values(array_filter($parts, fn ($part) => isset($part['functionCall'])));

                if ($calls) {
                    // Send the model's turn back unchanged (it carries thought signatures), then one response per call.
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                    $contents[] = [
                        'role' => 'function',
                        'parts' => array_map(fn ($part) => ['functionResponse' => array_filter([
                            'id' => $part['functionCall']['id'] ?? null,
                            'name' => $part['functionCall']['name'],
                            'response' => ['output' => $this->executeFunction($part['functionCall']['name'], $part['functionCall']['args'] ?? [])],
                        ])], $calls),
                    ];

                    $loopCount++;
                    continue; // Loop again to let Gemini read the result and construct its natural response
                }

                $text = collect($parts)->reject(fn ($part) => !empty($part['thought']))->pluck('text')->filter()->implode("\n\n");

                return $text !== '' ? $text : 'Done!';
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
            $filter = fn (string $key) => is_string($args[$key] ?? null) && $args[$key] !== '' ? $args[$key] : null;

            try {
                $courts = $this->checkAvailabilityInternal((string) $date, $filter('court_type'), $filter('city'), $filter('facility_name'), $filter('start_time'));
                if (empty($courts)) {
                    return "No slots are available on {$date} for the specified criteria.";
                }
                // ponytail: capped so a wide search can't blow up the prompt; ask for a city or facility if it's hit.
                $result = ['courts' => array_slice($courts, 0, 40)];
                if (count($courts) > 40) {
                    $result['note'] = 'Only the first 40 of ' . count($courts) . ' courts are listed. Ask the user to narrow down by city, facility or sport.';
                }
                return json_encode($result);
            } catch (\Exception $e) {
                return "Error checking availability: " . $e->getMessage();
            }
        }

        if ($name === 'prepareBooking') {
            $courtId = $args['court_id'] ?? null;
            $date = $args['date'] ?? null;
            $startTime = $args['start_time'] ?? null;
            $endTime = $args['end_time'] ?? null;

            if (!is_string($courtId) || !is_string($date) || !is_string($startTime) || !is_string($endTime)) {
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

    /** Courts with at least one free slot, each with all of its free start times and prices. */
    private function checkAvailabilityInternal(string $date, ?string $courtType, ?string $city, ?string $facilityName = null, ?string $startTime = null): array
    {
        $day = Booking::parseSlot($date, '00:00', '00:00');
        if (!$day) {
            return [];
        }

        $query = Facility::with(['courts']);
        if ($city) {
            $query->whereLike('city', "%{$city}%");
        }
        if ($facilityName) {
            $query->whereLike('name', "%{$facilityName}%");
        }

        $courts = $query->get()->flatMap(fn (Facility $facility) => $facility->courts->each->setRelation('facility', $facility));
        if ($courtType) {
            $courts = $courts->filter(fn($c) => strtolower($c->type) === strtolower($courtType));
        }

        $slotsByCourt = Court::slotsOn($courts, $day[0]);

        $result = [];
        foreach ($courts as $court) {
            $free = collect($slotsByCourt[$court->id])
                ->filter(fn ($slot) => $slot['available'] && (!$startTime || $slot['start'] === $startTime))
                ->map(fn ($slot) => "{$slot['start']}-{$slot['end']} (" . number_format($slot['price'] / 100, 0) . " MKD)")
                ->values();

            if ($free->isNotEmpty()) {
                $result[] = [
                    'court_id' => $court->id,
                    'court_name' => $court->name,
                    'court_type' => $court->type,
                    'facility_name' => $court->facility->name,
                    'city' => $court->facility->city,
                    'free_slots' => $free->all(),
                ];
            }
        }

        return $result;
    }

    private function prepareBookingInternal(string $courtId, string $date, string $startTime, string $endTime): string
    {
        $court = Str::isUuid($courtId) ? Court::with('facility')->find($courtId) : null;
        if (!$court) {
            return "Court not found.";
        }

        $slot = Booking::parseSlot($date, $startTime, $endTime);
        if (!$slot) {
            return "Dates must be YYYY-MM-DD and times HH:00.";
        }
        [$start, $end] = $slot;

        if ($error = Booking::bookingError($court, $start, $end)) {
            return $error;
        }

        if (Booking::overlapping($court->id, $start, $end)->exists()) {
            return "This slot is no longer available.";
        }

        // Calculate price
        $price = Booking::calculatePrice($court, $start, $end, []);

        // Set pending booking state
        $this->pendingBooking = [
            'court_id' => $court->id,
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
        // The facility list is the same for everyone and changes rarely; rebuilding it on every request is wasted work.
        $facilitiesText = Cache::remember('chatbot.facilities-prompt', now()->addMinutes(5), function () {
            $facilitiesText = "";
            foreach (Facility::with(['courts', 'amenities', 'rentals'])->get() as $facility) {
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

            $globalRentals = Rental::whereNull('facility_id')->get();
            if ($globalRentals->isNotEmpty()) {
                $facilitiesText .= "Rentals available at every facility:\n" . $globalRentals->map(fn($r) => "- {$r->name}: " . number_format($r->price / 100, 0) . " MKD" . ($r->suitable_for ? " (suitable for " . implode(', ', $r->suitable_for) . ")" : ""))->join("\n") . "\n";
            }

            return $facilitiesText;
        });

        $userText = "";
        if (auth()->check()) {
            $user = auth()->user();
            $userText .= "User Status: Authenticated\n";
            $userText .= "Name: {$user->name}\n";

            $bookings = Booking::where('user_id', $user->id)
                ->with(['court.facility', 'rentals'])
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

        return "You are 'Sporty', the official AI chatbot of Sport Manager (sports court booking platform in Macedonia).\n" .
               "Your character: Helpful, concise, uses emojis where appropriate, talks like a helpful sport facility manager. Keep answers brief (under 3 paragraphs if possible). Use markdown formatting.\n\n" .
               "CURRENT DATETIME INFO (Extremely Important):\n" .
               "- Today's Date: {$todayDate}\n" .
               "- Current Local Time: {$currentTime}\n" .
               "- Day of the week: {$dayOfWeek}\n" .
               "- Timezone: " . config('app.timezone') . "\n\n" .
               "IMPORTANT FOR BOOKINGS:\n" .
               "1. You have tools: checkAvailability and prepareBooking. Use them when users ask to search courts, find available slots, or request a booking.\n" .
               "2. NEVER make up court UUIDs. First call `checkAvailability` to get the list of available courts, their IDs, and times.\n" .
               "3. To prepare a booking, call `prepareBooking(court_id, date, start_time, end_time)`. This will show a physical confirmation card to the user so they can finalize the booking.\n" .
               "4. Explain to the user that they must click the 'Confirm Booking' button that just appeared in the chat window to finalize it.\n" .
               "5. " . $this->surchargeRules() . " Bookings are one-hour slots.\n" .
               "6. Cancellation: allowed only if start time is > 24 hours in the future.\n\n" .
               "CURRENT DATABASE FACILITIES DATA:\n" .
               $facilitiesText . "\n" .
               "CURRENT USER CONTEXT:\n" .
               $userText;
    }

    private function surchargeRules(): string
    {
        return sprintf(
            "Surcharges: +%d%% peak hours (%02d:00 - %02d:00), +%d%% weekends. Stacks: peak is applied first, then weekend.",
            Booking::PEAK_SURCHARGE * 100, Booking::PEAK_START_HOUR, Booking::PEAK_END_HOUR, Booking::WEEKEND_SURCHARGE * 100
        );
    }

    private function getMockResponse(string $userMessage): string
    {
        $msg = strtolower($userMessage);

        // Also used as a fallback when Gemini is configured but unreachable; don't tell those users to set a key.
        $hasKey = (bool) config('services.gemini.key');
        $setupNote = $hasKey ? '' : "\n\n*Note: Set `GEMINI_API_KEY` in your `.env` to enable full smart AI chat!*";

        $sports = [
            'Padel' => ['keywords' => ['padel'], 'title' => '🏸 **Padel Courts', 'noun' => 'Padel courts'],
            'Tennis' => ['keywords' => ['tennis'], 'title' => '🎾 **Tennis Courts', 'noun' => 'Tennis courts'],
            'Football' => ['keywords' => ['football', 'soccer'], 'title' => '⚽ **Football Courts', 'noun' => 'Football courts'],
            'Swimming' => ['keywords' => ['pool', 'swim'], 'title' => '🏊 **Swimming Pools', 'noun' => 'Swimming pools'],
        ];

        foreach ($sports as $type => $sport) {
            if (!Str::contains($msg, $sport['keywords'])) {
                continue;
            }

            $matching = Facility::whereHas('courts', fn ($q) => $q->where('type', $type))
                ->with(['courts' => fn ($q) => $q->where('type', $type)])
                ->get();
            if ($matching->isEmpty()) {
                return "{$sport['title']}:** No facilities with {$sport['noun']} registered.";
            }

            $res = "{$sport['title']} Available:**\n\n";
            foreach ($matching as $f) {
                $res .= "- **{$f->name}** in {$f->city} ({$f->address})\n";
                foreach ($f->courts as $c) {
                    $res .= "  - {$c->name}: " . number_format($c->base_price_per_hour / 100, 0) . " MKD/hour\n";
                }
            }
            return rtrim($res) . $setupNote;
        }

        if (str_contains($msg, 'cancel') || str_contains($msg, 'rules') || str_contains($msg, 'cancellation')) {
            return "📅 **Booking & Cancellation Rules:**\n\n" .
                   "- **Slots:** 1-hour increments during facility opening hours.\n" .
                   "- **Cancellations:** Only allowed if the booking start time is **more than 24 hours in the future**.\n" .
                   "- " . $this->surchargeRules() .
                   $setupNote;
        }

        if (preg_match('/\b(hello|hi|hey)\b/', $msg)) {
            return "👋 **Hello! I am Sporty, your AI assistant.**\n\n" .
                   "I can help you find facilities, courts, check prices, rentals, and understand cancellation rules.\n\n" .
                   ($hasKey ? '' : "To enable the full AI experience with real-time conversations, please set the `GEMINI_API_KEY` key in your `.env` file.\n\n") .
                   "Ask me about: **Padel, Tennis, Football, Swimming, or Booking Rules**!";
        }

        if ($hasKey) {
            return "I couldn't reach my AI service just now. Please try again in a moment.";
        }

        return "🤖 **Sporty (Demo Mode)**\n\n" .
               "I received your message: *\"" . e($userMessage) . "\"*\n\n" .
               "To get a real AI response using LLMs, please add your `GEMINI_API_KEY` to the `.env` file in the project root.\n\n" .
               "You can test me by typing keywords like: **Padel**, **Tennis**, **Football**, **Swimming**, or **Cancel**!";
    }

    public static function parseMarkdown(string $text): string
    {
        $safeText = e($text);

        // Links to pages on this site only, e.g. [Sign In](/login). Backslashes are rejected too: browsers read "/\host" as "//host", another site.
        $safeText = preg_replace('#\[([^\]]+)\]\((/(?!/)[^\s)*\\\\]*)\)#', '<a href="$2" class="underline font-bold">$1</a>', $safeText);

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
