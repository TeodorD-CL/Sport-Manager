<?php

namespace Tests\Feature;

use App\Livewire\Chatbot;
use App\Models\Facility;
use App\Models\User;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => null]);
    }

    public function test_chatbot_component_renders_on_page(): void
    {
        $user = User::factory()->create();

        // The layout has the chatbot component embedded.
        $this->actingAs($user)
            ->get('/')
            ->assertSeeLivewire(Chatbot::class);
    }

    public function test_chatbot_can_be_toggled(): void
    {
        Livewire::test(Chatbot::class)
            ->assertSet('isOpen', false)
            ->call('toggleChat')
            ->assertSet('isOpen', true)
            ->call('toggleChat')
            ->assertSet('isOpen', false);
    }

    public function test_chatbot_renders_preset_suggestions(): void
    {
        Livewire::test(Chatbot::class)
            ->assertSee("Where can I play Padel?")
            ->assertSee("Do you have Tennis courts?");
    }

    public function test_chatbot_sends_user_message_and_receives_reply(): void
    {
        Livewire::test(Chatbot::class)
            ->set('message', 'Hello')
            ->call('sendMessage')
            ->assertSet('message', '')
            ->assertCount('messages', 3) // includes typing placeholder
            ->call('handleBotResponse', 'Hello')
            ->assertCount('messages', 3) // typing replaced with bot message
            ->assertSee('Hello')
            ->assertSee('AI assistant');
    }

    public function test_chatbot_preset_clicks_populate_and_send_message(): void
    {
        Livewire::test(Chatbot::class)
            ->call('sendPreset', 'cancellation')
            ->call('handleBotResponse', 'cancellation')
            ->assertCount('messages', 3)
            ->assertSee('cancellation')
            ->assertSee('Booking & Cancellation Rules');
    }

    public function test_chatbot_can_be_cleared(): void
    {
        Livewire::test(Chatbot::class)
            ->set('message', 'Hello')
            ->call('sendMessage')
            ->call('handleBotResponse', 'Hello')
            ->call('clearChat')
            ->assertCount('messages', 1)
            ->assertSee('Chat cleared!');
    }

    public function test_markdown_parser_converts_bold_and_italic(): void
    {
        $parsed = Chatbot::parseMarkdown('This is **bold** and *italic* text.');
        
        $this->assertStringContainsString('This is <strong class="font-bold">bold</strong> and <em class="italic">italic</em> text.', $parsed);
    }

    public function test_chatbot_can_prepare_pending_booking(): void
    {
        Livewire::test(Chatbot::class)
            ->set('pendingBooking', [
                'court_id' => 'court-uuid-123',
                'court_name' => 'Court 1',
                'court_type' => 'Tennis',
                'facility_id' => 'facility-uuid-123',
                'facility_name' => 'Facility A',
                'date' => '2026-07-01',
                'start_time' => '14:00',
                'end_time' => '15:00',
                'total_price' => 120000,
                'price_formatted' => '1,200 MKD',
            ])
            ->assertSee('Confirmation Request')
            ->assertSee('Court 1')
            ->assertSee('Facility A')
            ->assertSee('1,200 MKD');
    }

    public function test_chatbot_can_cancel_pending_booking(): void
    {
        Livewire::test(Chatbot::class)
            ->set('pendingBooking', [
                'court_id' => 'court-uuid-123',
                'court_name' => 'Court 1',
                'court_type' => 'Tennis',
                'facility_id' => 'facility-uuid-123',
                'facility_name' => 'Facility A',
                'date' => '2026-07-01',
                'start_time' => '14:00',
                'end_time' => '15:00',
                'total_price' => 120000,
                'price_formatted' => '1,200 MKD',
            ])
            ->call('cancelPendingBooking')
            ->assertSet('pendingBooking', null)
            ->assertDontSee('Confirmation Request');
    }

    public function test_chatbot_can_confirm_pending_booking(): void
    {
        $user = User::factory()->create();
        $facility = Facility::create([
            'name' => 'Test Facility',
            'description' => 'Test',
            'city' => 'Skopje',
            'address' => 'Test St 1',
        ]);
        $court = Court::create([
            'facility_id' => $facility->id,
            'name' => 'Test Court',
            'type' => 'Tennis',
            'base_price_per_hour' => 10000,
        ]);

        Livewire::actingAs($user)
            ->test(Chatbot::class)
            ->set('pendingBooking', [
                'court_id' => $court->id,
                'court_name' => $court->name,
                'court_type' => $court->type,
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'date' => Carbon::parse('next monday')->format('Y-m-d'),
                'start_time' => '14:00',
                'end_time' => '15:00',
                'total_price' => 10000,
                'price_formatted' => '100 MKD',
            ])
            ->call('confirmPendingBooking')
            ->assertSet('pendingBooking', null)
            ->assertSee('Booking Confirmed!');

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }
}
