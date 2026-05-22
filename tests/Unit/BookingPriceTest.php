<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Court;
use Carbon\Carbon;
use Tests\TestCase;

class BookingPriceTest extends TestCase
{
    private function court(int $pricePerHour): Court
    {
        $court = new Court();
        $court->base_price_per_hour = $pricePerHour;
        return $court;
    }

    // Helpers to make clear, fixed dates for assertions
    private function monday(string $time): Carbon { return Carbon::parse("2026-06-01 $time"); } // weekday
    private function saturday(string $time): Carbon { return Carbon::parse("2026-06-06 $time"); } // weekend

    public function test_base_price_for_one_hour(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('10:00'),
            $this->monday('11:00'),
        );

        $this->assertSame(10000, $price);
    }

    public function test_minimum_charge_is_one_hour(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('10:00'),
            $this->monday('10:30'), // 30 minutes → still billed as 1 hour
        );

        $this->assertSame(10000, $price);
    }

    public function test_multi_hour_scales_linearly(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('10:00'),
            $this->monday('13:00'), // 3 hours
        );

        $this->assertSame(30000, $price);
    }

    public function test_peak_surcharge_at_18(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('18:00'),
            $this->monday('19:00'),
        );

        $this->assertSame(12000, $price); // +20%
    }

    public function test_peak_surcharge_at_21(): void
    {
        // 21:00 is still within peak (< 22)
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('21:00'),
            $this->monday('22:00'),
        );

        $this->assertSame(12000, $price);
    }

    public function test_no_peak_surcharge_at_17(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('17:00'),
            $this->monday('18:00'),
        );

        $this->assertSame(10000, $price);
    }

    public function test_weekend_surcharge_on_saturday(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->saturday('10:00'),
            $this->saturday('11:00'),
        );

        $this->assertSame(11000, $price); // +10%
    }

    public function test_weekend_surcharge_on_sunday(): void
    {
        $price = Booking::calculatePrice(
            $this->court(10000),
            Carbon::parse('2026-06-07 10:00'), // Sunday
            Carbon::parse('2026-06-07 11:00'),
        );

        $this->assertSame(11000, $price);
    }

    public function test_peak_and_weekend_surcharges_stack(): void
    {
        // Saturday 18:00: peak first (+20% = 12000), then weekend (+10% = 13200)
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->saturday('18:00'),
            $this->saturday('19:00'),
        );

        $this->assertSame(13200, $price);
    }

    public function test_multi_hour_with_peak_surcharge(): void
    {
        // 2 hours at peak: 20000 * 1.2 = 24000
        $price = Booking::calculatePrice(
            $this->court(10000),
            $this->monday('18:00'),
            $this->monday('20:00'),
        );

        $this->assertSame(24000, $price);
    }
}
